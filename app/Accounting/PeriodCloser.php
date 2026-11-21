<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\FinancialYear;
use App\Models\Journal;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Decimal;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Closes a past financial year into Retained Earnings and reopens it.
 * The carry-forward is an opening journal of the next year. It is not an account opening.
 */
final class PeriodCloser
{
    public function __construct(
        private JournalWriter $journals,
        private PostedBalances $balances,
        private AuditLogger $audit,
    ) {}

    public function close(FinancialYear $year, User $actor, string $reason): FinancialYear
    {
        $reason = $this->reason($reason, 'Enter a close reason of 3 to 1000 characters.');

        return DB::transaction(function () use ($year, $actor, $reason): FinancialYear {
            $locked = $this->lock($year);

            if ($locked->isClosed()) {
                throw new AccountingException('This financial year is already closed.');
            }

            $today = now()->timezone('Asia/Dhaka')->toDateString();

            if ($today >= $locked->startsOn() && $today <= $locked->endsOn()) {
                throw new AccountingException('The financial year that contains today stays open.');
            }

            $next = FinancialYear::query()
                ->whereDate('starts_on', '>', $locked->endsOn())
                ->orderBy('starts_on')
                ->orderBy('id')
                ->first();

            if (! $next instanceof FinancialYear) {
                throw new AccountingException('Add the next financial year before closing this one.');
            }

            $this->pauseWhileLocked();
            $journal = $this->carryForward($locked, $next, $actor);
            $locked->closed_at = now();
            $locked->closed_by = $actor->id;
            $locked->close_reason = $reason;
            $locked->carry_forward_journal_id = $journal instanceof Journal ? $journal->id : null;
            $locked->save();
            $this->audit->record('financial_year_closed', $locked, newValues: [
                'starts_on' => $locked->startsOn(),
                'ends_on' => $locked->endsOn(),
                'close_reason' => $reason,
                'carry_forward_journal_id' => $locked->carry_forward_journal_id,
            ], actor: $actor);

            return $locked;
        });
    }

    public function reopen(FinancialYear $year, User $actor, string $reason): FinancialYear
    {
        if (! $actor->isSuperAdministrator()) {
            throw new AccountingException('Only a super administrator can reopen a financial year.');
        }

        $reason = $this->reason($reason, 'Enter a reopen reason of 3 to 1000 characters.');

        return DB::transaction(function () use ($year, $actor, $reason): FinancialYear {
            $locked = $this->lock($year);

            if (! $locked->isClosed()) {
                throw new AccountingException('This financial year is already open.');
            }

            $journalId = $locked->carry_forward_journal_id;

            if (is_numeric($journalId)) {
                $journal = Journal::query()->whereKey((int) $journalId)->first();

                if ($journal instanceof Journal && $journal->status === JournalStatus::Posted && ! $journal->reversal()->exists()) {
                    $this->journals->reverse($journal, $actor);
                }
            }

            $locked->closed_at = null;
            $locked->closed_by = null;
            $locked->close_reason = null;
            $locked->carry_forward_journal_id = null;
            $locked->save();
            $this->audit->record('financial_year_reopened', $locked, newValues: [
                'starts_on' => $locked->startsOn(),
                'ends_on' => $locked->endsOn(),
                'reopen_reason' => $reason,
            ], actor: $actor);

            return $locked;
        });
    }

    private function carryForward(FinancialYear $year, FinancialYear $next, User $actor): ?Journal
    {
        $sums = $this->balances->sums(
            $year->startsOn(),
            $year->endsOn(),
            [AccountType::Income->value, AccountType::Expense->value],
            true,
        );
        $lines = [];
        $debitTotal = Money::zero();
        $creditTotal = Money::zero();

        foreach (Account::query()->whereIn('id', array_keys($sums))->orderBy('code')->get() as $account) {
            $pair = $sums[$account->id] ?? null;

            if ($pair === null) {
                continue;
            }

            $normal = $account->type === AccountType::Income
                ? $pair[1]->minus($pair[0])
                : $pair[0]->minus($pair[1]);

            if ($this->isZero($normal)) {
                continue;
            }

            $absolute = $this->absolute($normal);
            $debit = '0.00';
            $credit = '0.00';

            if ($account->type === AccountType::Income) {
                if (Decimal::of($normal->amount())->isNegative()) {
                    $credit = $absolute->amount();
                    $creditTotal = $creditTotal->plus($absolute);
                } else {
                    $debit = $absolute->amount();
                    $debitTotal = $debitTotal->plus($absolute);
                }
            } elseif (Decimal::of($normal->amount())->isNegative()) {
                $debit = $absolute->amount();
                $debitTotal = $debitTotal->plus($absolute);
            } else {
                $credit = $absolute->amount();
                $creditTotal = $creditTotal->plus($absolute);
            }

            $lines[] = [
                'account_id' => (int) $account->id,
                'debit' => $debit,
                'credit' => $credit,
                'party_id' => null,
                'memo' => '',
            ];
        }

        if ($lines === []) {
            return null;
        }

        $plug = $debitTotal->minus($creditTotal);

        if (! $this->isZero($plug)) {
            $retained = Account::query()->where('system_key', 'retained_earnings')->first();

            if (! $retained instanceof Account) {
                throw new AccountingException('The ledger is missing the retained_earnings account.');
            }

            $absolute = $this->absolute($plug);
            $lines[] = [
                'account_id' => (int) $retained->id,
                'debit' => Decimal::of($plug->amount())->isNegative() ? $absolute->amount() : '0.00',
                'credit' => Decimal::of($plug->amount())->isNegative() ? '0.00' : $absolute->amount(),
                'party_id' => null,
                'memo' => '',
            ];
        }

        return $this->journals->postCarryForward([
            'journal_date' => $next->startsOn(),
            'memo' => 'Close '.$year->startsOn().' to '.$year->endsOn(),
            'lines' => $lines,
        ], $actor, (int) $year->id);
    }

    private function pauseWhileLocked(): void
    {
        // The disposable MySQL rehearsal holds the year lock so a posting waits,
        // then reads closed_at with a current read. Production leaves this unset.
        if (getenv('ASL_PERIOD_CLOSE_DELAY') === '1') {
            sleep(1);
        }
    }

    private function lock(FinancialYear $year): FinancialYear
    {
        $locked = FinancialYear::query()->whereKey($year->id)->lockForUpdate()->first();

        if (! $locked instanceof FinancialYear) {
            throw new AccountingException('That financial year is no longer available.');
        }

        return $locked;
    }

    private function reason(string $reason, string $message): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 1000) {
            throw new AccountingException($message);
        }

        return $reason;
    }

    private function absolute(Money $money): Money
    {
        if (Decimal::of($money->amount())->isNegative()) {
            return Money::zero()->minus($money);
        }

        return $money;
    }

    private function isZero(Money $money): bool
    {
        return Decimal::of($money->amount())->isZero();
    }
}
