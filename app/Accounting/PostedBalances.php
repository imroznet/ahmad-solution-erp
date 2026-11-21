<?php

namespace App\Accounting;

use App\Models\JournalLine;
use App\Support\Money;

/**
 * Debit and credit totals of posted journals, optionally inside a date range.
 */
final class PostedBalances
{
    /**
     * @param  list<string>|null  $types
     * @return array<int, array{0: Money, 1: Money}>
     */
    public function sums(?string $from, ?string $to, ?array $types = null, bool $excludeClosing = false): array
    {
        $query = JournalLine::query()
            ->select('journal_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_id')
            ->where('journal_entries.status', JournalStatus::Posted->value);

        if ($from !== null && $from !== '') {
            $query->whereDate('journal_entries.journal_date', '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->whereDate('journal_entries.journal_date', '<=', $to);
        }

        if ($types !== null) {
            $query->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.account_id')
                ->whereIn('chart_of_accounts.type', $types);
        }

        if ($excludeClosing) {
            $query->where('journal_entries.source', '!=', JournalSource::CarryForward->value)
                ->where(function ($entries): void {
                    $entries->where('journal_entries.source', '!=', JournalSource::Reversal->value)
                        ->orWhereNotExists(function ($original): void {
                            $original->selectRaw('1')
                                ->from('journal_entries as closed_original')
                                ->whereColumn('closed_original.id', 'journal_entries.reverses_journal_id')
                                ->where('closed_original.source', JournalSource::CarryForward->value);
                        });
                });
        }

        $sums = [];

        foreach ($query->get() as $line) {
            $id = (int) $line->account_id;
            $sums[$id] ??= [Money::zero(), Money::zero()];
            $sums[$id][0] = $sums[$id][0]->plus(Money::of($line->decimalAttribute('debit')));
            $sums[$id][1] = $sums[$id][1]->plus(Money::of($line->decimalAttribute('credit')));
        }

        return $sums;
    }
}
