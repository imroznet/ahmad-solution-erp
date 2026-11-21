<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\MfsAccount;
use App\Models\User;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Draft, post, and reverse journals. A posted journal is not edited or deleted.
 * A reversal is a new posted journal that swaps every debit and credit.
 */
final class JournalWriter
{
    public function __construct(
        private JournalNumberAllocator $numbers,
        private JournalLineSet $lines,
        private FinancialYearCalendar $years,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{journal_date: string, memo?: string|null, lines: list<mixed>}  $input
     */
    public function createDraft(array $input, User $actor): Journal
    {
        $write = function () use ($input, $actor): Journal {
            $prepared = $this->prepare($input, false);
            $journal = $this->store(
                $this->numbers->allocate(),
                JournalStatus::Draft,
                JournalSource::Manual,
                $prepared,
                $actor,
                false,
                null,
                null,
                null,
            );
            $this->audit->record('journal_created', $journal, newValues: $this->snapshot($journal), actor: $actor);

            return $journal;
        };

        return $this->within($write);
    }

    /**
     * @param  array{journal_date: string, memo?: string|null, lines: list<mixed>}  $input
     */
    public function updateDraft(Journal $journal, array $input, User $actor): Journal
    {
        $write = function () use ($journal, $input, $actor): Journal {
            $locked = $this->lock($journal);
            $this->guardDraft($locked);
            $before = $this->snapshot($locked);
            $prepared = $this->prepare($input, false);
            $locked->journal_date = Carbon::parse($prepared['date']);
            $locked->financial_year_starts_on = Carbon::parse($prepared['year_starts']);
            $locked->financial_year_ends_on = Carbon::parse($prepared['year_ends']);
            $locked->memo = $prepared['memo'];
            $locked->save();
            $locked->lines()->delete();
            $this->storeLines($locked, $prepared['lines']);
            $locked->load('lines');
            $this->audit->record('journal_updated', $locked, oldValues: $before, newValues: $this->snapshot($locked), actor: $actor);

            return $locked;
        };

        return $this->within($write);
    }

    public function deleteDraft(Journal $journal, User $actor): void
    {
        $write = function () use ($journal, $actor): void {
            $locked = $this->lock($journal);
            $this->guardDraft($locked);
            $before = $this->snapshot($locked);
            $locked->lines()->delete();
            $locked->delete();
            $this->audit->record('journal_deleted', $locked, oldValues: $before, actor: $actor);
        };

        $this->within($write);
    }

    public function post(Journal $journal, User $actor): Journal
    {
        $write = function () use ($journal, $actor): Journal {
            $locked = $this->lock($journal);
            $this->guardDraft($locked);
            $stored = $this->storedInput($locked);
            $this->prepare($stored, true);
            $locked->status = JournalStatus::Posted;
            $locked->posted_at = now();
            $locked->posted_by = $actor->id;
            $locked->save();
            $locked->load('lines');
            $this->audit->record('journal_posted', $locked, newValues: $this->snapshot($locked), actor: $actor);

            return $locked;
        };

        return $this->within($write);
    }

    public function reverse(Journal $journal, User $actor): Journal
    {
        $write = function () use ($journal, $actor): Journal {
            $locked = $this->lock($journal);

            if ($locked->status !== JournalStatus::Posted) {
                throw new AccountingException('Only a posted journal can be reversed.');
            }

            if ($locked->reversal()->exists()) {
                throw new AccountingException('This journal already has a reversal.');
            }

            $swapped = [];

            foreach ($locked->lines()->orderBy('position')->orderBy('id')->get() as $line) {
                $swapped[] = [
                    'account_id' => $line->account_id,
                    'debit' => $line->decimalAttribute('credit'),
                    'credit' => $line->decimalAttribute('debit'),
                    'party_id' => $line->party_id,
                    'memo' => $line->memo,
                ];
            }

            $date = $this->reversalDate($locked);
            $prepared = $this->prepare([
                'journal_date' => $date,
                'memo' => 'Reversal of '.$locked->number,
                'lines' => $swapped,
            ], true, false, true);
            $reversal = $this->store(
                $this->numbers->allocate(),
                JournalStatus::Posted,
                JournalSource::Reversal,
                $prepared,
                $actor,
                true,
                'journal',
                (int) $locked->id,
                (int) $locked->id,
            );
            $this->audit->record('journal_created', $reversal, newValues: $this->snapshot($reversal), actor: $actor);
            $this->audit->record('journal_posted', $reversal, newValues: $this->snapshot($reversal), actor: $actor);
            $this->audit->record('journal_reversed', $locked, newValues: [
                'reversal_number' => $reversal->number,
                'reversal_id' => $reversal->id,
            ], actor: $actor);

            return $reversal;
        };

        return $this->within($write);
    }

    /**
     * @param  array{journal_date: string, memo?: string|null, lines: list<mixed>}  $input
     */
    public function postOpening(array $input, User $actor): Journal
    {
        $write = function () use ($input, $actor): Journal {
            $prepared = $this->prepare($input, true, true);
            $this->guardOpeningAccounts($prepared['date'], $prepared['lines']);
            $journal = $this->store(
                $this->numbers->allocate(),
                JournalStatus::Posted,
                JournalSource::Opening,
                $prepared,
                $actor,
                true,
                null,
                null,
                null,
            );
            $this->audit->record('journal_created', $journal, newValues: $this->snapshot($journal), actor: $actor);
            $this->audit->record('journal_posted', $journal, newValues: $this->snapshot($journal), actor: $actor);

            return $journal;
        };

        return $this->within($write);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function within(Closure $callback): mixed
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction($callback, 5);
        }

        return $callback();
    }

    private function lock(Journal $journal): Journal
    {
        $locked = Journal::query()->whereKey($journal->id)->lockForUpdate()->first();

        if (! $locked instanceof Journal) {
            throw new AccountingException('That journal is no longer available.');
        }

        return $locked;
    }

    private function guardDraft(Journal $journal): void
    {
        if ($journal->status !== JournalStatus::Draft) {
            throw new AccountingException('A posted journal cannot be changed or deleted.');
        }
    }

    /**
     * @param  array{journal_date: string, memo?: string|null, lines: list<mixed>}  $input
     * @return array{date: string, year_starts: string, year_ends: string, memo: string|null, lines: list<array{account_id: int, debit: string, credit: string, party_id: int|null, memo: string|null}>}
     */
    private function prepare(array $input, bool $requireBalance, bool $skipIdleRows = false, bool $allowInactive = false): array
    {
        $date = $input['journal_date'];
        $year = $this->financialYear($date);
        $memo = trim((string) ($input['memo'] ?? ''));

        if (mb_strlen($memo) > 2000) {
            throw new AccountingException('The memo must be 2000 characters or fewer.');
        }

        return [
            'date' => $date,
            'year_starts' => $year['starts'],
            'year_ends' => $year['ends'],
            'memo' => $memo === '' ? null : $memo,
            'lines' => $this->lines->parse($input['lines'], $requireBalance, $skipIdleRows, $allowInactive),
        ];
    }

    /**
     * @return array{starts: string, ends: string}
     */
    private function financialYear(string $date): array
    {
        return $this->years->boundsFor($date);
    }

    /**
     * @param  list<array{account_id: int, debit: string, credit: string, party_id: int|null, memo: string|null}>  $lines
     */
    private function guardOpeningAccounts(string $date, array $lines): void
    {
        $ids = array_values(array_unique(array_column($lines, 'account_id')));

        if ($ids === []) {
            return;
        }

        // The row lock is taken before the opening check. A second post for the same account waits, then sees the first journal.
        Account::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id']);

        $already = JournalLine::query()
            ->whereIn('account_id', $ids)
            ->whereHas('journal', fn ($query) => $query->where('source', JournalSource::Opening))
            ->exists();

        if ($already) {
            throw new AccountingException('Opening balances for one of these accounts have already been entered.');
        }

        foreach (BankAccount::query()->whereIn('account_id', $ids)->get() as $bank) {
            if ($bank->openedOn() === '' || $date < $bank->openedOn()) {
                throw new AccountingException('A bank opening balance cannot be dated before the account was opened.');
            }
        }

        foreach (MfsAccount::query()->whereIn('account_id', $ids)->get() as $wallet) {
            if ($wallet->openedOn() === '' || $date < $wallet->openedOn()) {
                throw new AccountingException('An MFS opening balance cannot be dated before the account was opened.');
            }
        }
    }

    private function reversalDate(Journal $journal): string
    {
        $today = now()->timezone('Asia/Dhaka')->toDateString();

        try {
            $this->financialYear($today);

            return $today;
        } catch (AccountingException) {
            $original = $journal->journalDateString();
            $this->financialYear($original);

            return $original;
        }
    }

    /**
     * @return array{journal_date: string, memo: string|null, lines: list<array{account_id: int, debit: string, credit: string, party_id: int|null, memo: string|null}>}
     */
    private function storedInput(Journal $journal): array
    {
        $lines = [];

        foreach ($journal->lines()->orderBy('position')->orderBy('id')->get() as $line) {
            $lines[] = [
                'account_id' => $line->account_id,
                'debit' => $line->decimalAttribute('debit'),
                'credit' => $line->decimalAttribute('credit'),
                'party_id' => $line->party_id,
                'memo' => $line->memo,
            ];
        }

        return [
            'journal_date' => $journal->journalDateString(),
            'memo' => $journal->memo,
            'lines' => $lines,
        ];
    }

    /**
     * @param  array{date: string, year_starts: string, year_ends: string, memo: string|null, lines: list<array{account_id: int, debit: string, credit: string, party_id: int|null, memo: string|null}>}  $prepared
     */
    private function store(
        string $number,
        JournalStatus $status,
        JournalSource $source,
        array $prepared,
        User $actor,
        bool $posted,
        ?string $sourceType,
        ?int $sourceId,
        ?int $reversesJournalId,
    ): Journal {
        $journal = new Journal([
            'number' => $number,
            'status' => $status,
            'journal_date' => $prepared['date'],
            'financial_year_starts_on' => $prepared['year_starts'],
            'financial_year_ends_on' => $prepared['year_ends'],
            'memo' => $prepared['memo'],
            'source' => $source,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reverses_journal_id' => $reversesJournalId,
            'posted_at' => $posted ? now() : null,
            'posted_by' => $posted ? $actor->id : null,
            'created_by' => $actor->id,
        ]);
        $journal->save();
        $this->storeLines($journal, $prepared['lines']);

        if ($source === JournalSource::Opening) {
            $this->recordOpenings($journal, $prepared['lines']);
        }

        $journal->load('lines');

        return $journal;
    }

    /**
     * @param  list<array{account_id: int, debit: string, credit: string, party_id: int|null, memo: string|null}>  $lines
     */
    private function recordOpenings(Journal $journal, array $lines): void
    {
        foreach (array_unique(array_column($lines, 'account_id')) as $accountId) {
            try {
                DB::table('account_openings')->insert([
                    'account_id' => $accountId,
                    'journal_id' => $journal->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new AccountingException('Opening balances for one of these accounts have already been entered.');
            }
        }
    }

    /**
     * @param  list<array{account_id: int, debit: string, credit: string, party_id: int|null, memo: string|null}>  $lines
     */
    private function storeLines(Journal $journal, array $lines): void
    {
        foreach ($lines as $position => $line) {
            $journal->lines()->create([
                'account_id' => $line['account_id'],
                'party_id' => $line['party_id'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'memo' => $line['memo'],
                'position' => $position + 1,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Journal $journal): array
    {
        $journal->loadMissing('lines');

        return [
            'number' => $journal->number,
            'status' => $journal->status->value,
            'journal_date' => $journal->journalDateString(),
            'financial_year_starts_on' => $this->dateString($journal->financial_year_starts_on),
            'financial_year_ends_on' => $this->dateString($journal->financial_year_ends_on),
            'memo' => $journal->memo,
            'source' => $journal->source->value,
            'source_type' => $journal->source_type,
            'source_id' => $journal->source_id,
            'reverses_journal_id' => $journal->reverses_journal_id,
            'lines' => $journal->lines->map(fn (JournalLine $line): array => [
                'account_id' => $line->account_id,
                'party_id' => $line->party_id,
                'debit' => $line->decimalAttribute('debit'),
                'credit' => $line->decimalAttribute('credit'),
                'memo' => $line->memo,
            ])->all(),
        ];
    }

    private function dateString(mixed $value): string
    {
        $date = $this->asDate($value);

        return $date instanceof Carbon ? $date->toDateString() : '';
    }

    private function asDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
