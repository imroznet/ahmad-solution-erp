<?php

namespace App\Http\Controllers;

use App\Accounting\AccountingException;
use App\Accounting\JournalStatus;
use App\Accounting\JournalWriter;
use App\Http\Controllers\Concerns\RedirectsAccountingErrors;
use App\Http\Requests\SaveJournalRequest;
use App\Models\Account;
use App\Models\Journal;
use App\Models\Party;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JournalController extends Controller
{
    use RedirectsAccountingErrors;

    public function index(): View
    {
        $actor = $this->actor(request()->user());

        return view('accounting.journals.index', [
            'journals' => Journal::query()->orderByDesc('journal_date')->orderByDesc('id')->paginate(20),
            'canCreate' => $actor->hasPermission('journals.create'),
        ]);
    }

    public function create(): View
    {
        return view('accounting.journals.form', $this->formData(null));
    }

    public function store(SaveJournalRequest $request, JournalWriter $writer): RedirectResponse
    {
        try {
            $journal = $writer->createDraft($this->input($request), $this->actor($request->user()));
        } catch (AccountingException $exception) {
            return $this->accountingError($exception);
        }

        return redirect()->route('journals.show', $journal)->with('status', 'Draft journal saved.');
    }

    public function show(Journal $journal): View
    {
        $actor = $this->actor(request()->user());
        $journal->load(['lines.account', 'lines.party', 'reversal', 'reverses']);

        return view('accounting.journals.show', [
            'journal' => $journal,
            'canCreate' => $actor->hasPermission('journals.create'),
            'canPost' => $actor->hasPermission('journals.post'),
            'canReverse' => $actor->hasPermission('journals.reverse'),
        ]);
    }

    public function edit(Journal $journal): View
    {
        $this->guardDraft($journal);

        return view('accounting.journals.form', $this->formData($journal));
    }

    public function update(SaveJournalRequest $request, Journal $journal, JournalWriter $writer): RedirectResponse
    {
        $this->guardDraft($journal);

        try {
            $writer->updateDraft($journal, $this->input($request), $this->actor($request->user()));
        } catch (AccountingException $exception) {
            return $this->accountingError($exception);
        }

        return redirect()->route('journals.show', $journal)->with('status', 'Draft journal updated.');
    }

    public function destroy(Journal $journal, JournalWriter $writer): RedirectResponse
    {
        $actor = $this->actor(request()->user());
        abort_unless($actor->hasPermission('journals.create'), 403);
        $this->guardDraft($journal);

        try {
            $writer->deleteDraft($journal, $actor);
        } catch (AccountingException $exception) {
            return $this->accountingError($exception);
        }

        return redirect()->route('journals.index')->with('status', 'Draft journal deleted.');
    }

    public function post(Journal $journal, JournalWriter $writer): RedirectResponse
    {
        $actor = $this->actor(request()->user());
        abort_unless($actor->hasPermission('journals.post'), 403);

        try {
            $writer->post($journal, $actor);
        } catch (AccountingException $exception) {
            return $this->accountingError($exception);
        }

        return redirect()->route('journals.show', $journal)->with('status', 'Journal posted.');
    }

    public function reverse(Journal $journal, JournalWriter $writer): RedirectResponse
    {
        $actor = $this->actor(request()->user());
        abort_unless($actor->hasPermission('journals.reverse'), 403);

        try {
            $reversal = $writer->reverse($journal, $actor);
        } catch (AccountingException $exception) {
            return $this->accountingError($exception);
        }

        return redirect()->route('journals.show', $reversal)->with('status', 'Reversal posted.');
    }

    /**
     * @return array{journal_date: string, memo: string|null, lines: list<mixed>}
     */
    private function input(SaveJournalRequest $request): array
    {
        $lines = $request->input('lines');

        return [
            'journal_date' => $request->string('journal_date')->toString(),
            'memo' => $request->input('memo'),
            'lines' => is_array($lines) ? array_values($lines) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Journal $journal): array
    {
        $accounts = Account::query()->where('is_active', true)->orderBy('code')->get();
        $parties = Party::query()->where('status', 'active')->orderBy('legal_name')->get(['id', 'legal_name']);

        if ($journal instanceof Journal) {
            $journal->load('lines');
            $usedAccounts = $journal->lines->pluck('account_id')->filter()->all();
            $inactive = Account::query()->whereIn('id', $usedAccounts)->where('is_active', false)->get();
            $accounts = $accounts->concat($inactive)->sortBy('code')->values();
            $usedParties = $journal->lines->pluck('party_id')->filter()->all();
            $extraParties = Party::query()->whereIn('id', $usedParties)->where('status', '!=', 'active')->get(['id', 'legal_name']);
            $parties = $parties->concat($extraParties)->sortBy('legal_name')->values();
        }

        return [
            'journal' => $journal,
            'accounts' => $accounts,
            'parties' => $parties,
            'rows' => $this->rows($journal),
            'defaultDate' => now()->timezone('Asia/Dhaka')->toDateString(),
        ];
    }

    /**
     * @return list<array{account_id: string, debit: string, credit: string, party_id: string, memo: string}>
     */
    private function rows(?Journal $journal): array
    {
        $rows = [];

        if ($journal instanceof Journal) {
            foreach ($journal->lines as $line) {
                $rows[] = [
                    'account_id' => (string) $line->account_id,
                    'debit' => $this->amount($line->decimalAttribute('debit')),
                    'credit' => $this->amount($line->decimalAttribute('credit')),
                    'party_id' => $line->party_id === null ? '' : (string) $line->party_id,
                    'memo' => (string) $line->memo,
                ];
            }
        }

        while (count($rows) < 12) {
            $rows[] = ['account_id' => '', 'debit' => '', 'credit' => '', 'party_id' => '', 'memo' => ''];
        }

        return array_slice($rows, 0, 12);
    }

    private function amount(string $value): string
    {
        return Decimal::of($value)->isZero() ? '' : Money::of($value)->amount();
    }

    private function guardDraft(Journal $journal): void
    {
        if ($journal->status !== JournalStatus::Draft) {
            abort(403);
        }
    }

    private function actor(mixed $user): User
    {
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
