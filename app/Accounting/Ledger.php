<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\JournalLine;
use App\Models\Party;
use App\Support\Money;

/**
 * Trial balance and general ledger from posted journals.
 * Drafts are excluded. Amounts stay on Money.
 */
final class Ledger
{
    /**
     * @return array{
     *     rows: list<array{code: string, name: string, type: string, debit: string, credit: string, active: bool}>,
     *     debit_total: string,
     *     credit_total: string,
     *     difference: string
     * }
     */
    public function trialBalance(): array
    {
        $sums = $this->postedSums();
        $rows = [];
        $debitTotal = Money::zero();
        $creditTotal = Money::zero();

        foreach (Account::query()->orderBy('code')->get() as $account) {
            $pair = $sums[$account->id] ?? [Money::zero(), Money::zero()];
            $debitTotal = $debitTotal->plus($pair[0]);
            $creditTotal = $creditTotal->plus($pair[1]);
            $rows[] = [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->label(),
                'debit' => $pair[0]->amount(),
                'credit' => $pair[1]->amount(),
                'active' => (bool) $account->is_active,
            ];
        }

        return [
            'rows' => $rows,
            'debit_total' => $debitTotal->amount(),
            'credit_total' => $creditTotal->amount(),
            'difference' => $debitTotal->minus($creditTotal)->amount(),
        ];
    }

    /**
     * @return list<array{date: string, number: string, journal_id: int, party: string, memo: string, debit: string, credit: string, balance: string}>
     */
    public function generalLedger(Account $account, ?string $from, ?string $to, ?int $partyId): array
    {
        $query = JournalLine::query()
            ->select('journal_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_id')
            ->where('journal_lines.account_id', $account->id)
            ->where('journal_entries.status', JournalStatus::Posted->value)
            ->orderBy('journal_entries.journal_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.position')
            ->orderBy('journal_lines.id')
            ->with(['journal', 'party']);

        if ($from !== null && $from !== '') {
            $query->whereDate('journal_entries.journal_date', '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->whereDate('journal_entries.journal_date', '<=', $to);
        }

        if ($partyId !== null) {
            $query->where('journal_lines.party_id', $partyId);
        }

        $running = Money::zero();
        $rows = [];

        foreach ($query->get() as $line) {
            $debit = Money::of($line->decimalAttribute('debit'));
            $credit = Money::of($line->decimalAttribute('credit'));
            $movement = $account->type->increasesOnDebit()
                ? $debit->minus($credit)
                : $credit->minus($debit);
            $running = $running->plus($movement);
            $journal = $line->journal;
            $party = $line->party;
            $rows[] = [
                'date' => $journal->journalDateString(),
                'number' => (string) $journal->number,
                'journal_id' => (int) $journal->id,
                'party' => $party instanceof Party ? $party->legal_name : '',
                'memo' => $this->memo($line),
                'debit' => $debit->amount(),
                'credit' => $credit->amount(),
                'balance' => $running->amount(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{0: Money, 1: Money}>
     */
    private function postedSums(): array
    {
        $lines = JournalLine::query()
            ->select('journal_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_id')
            ->where('journal_entries.status', JournalStatus::Posted->value)
            ->get();
        $sums = [];

        foreach ($lines as $line) {
            $id = (int) $line->account_id;
            $sums[$id] ??= [Money::zero(), Money::zero()];
            $sums[$id][0] = $sums[$id][0]->plus(Money::of($line->decimalAttribute('debit')));
            $sums[$id][1] = $sums[$id][1]->plus(Money::of($line->decimalAttribute('credit')));
        }

        return $sums;
    }

    private function memo(JournalLine $line): string
    {
        $parts = [];
        $journalMemo = $line->journal?->memo;
        $lineMemo = $line->memo;

        if (is_string($journalMemo) && $journalMemo !== '') {
            $parts[] = $journalMemo;
        }

        if (is_string($lineMemo) && $lineMemo !== '') {
            $parts[] = $lineMemo;
        }

        return implode(' — ', $parts);
    }
}
