<?php

namespace App\Accounting;

use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\Party;
use App\Models\Payment;
use App\Support\Decimal;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Payables by supplier, aged from the journal date against Asia/Dhaka today.
 * An unallocated payment remainder is an advance. It reduces the party total.
 * There is no separate advance account.
 */
final class PayablesAging
{
    public function __construct(
        private PaymentBalance $balances,
        private DocumentPosting $posting,
    ) {}

    /**
     * @return array{as_of: string, parties: list<array{party_id: int, name: string, lines: list<array{label: string, date: string, invoiced: string, allocated: string, outstanding: string, bucket: string}>, advance: string, total: string, payable: string}>, total: string}
     */
    public function build(): array
    {
        $today = now()->timezone('Asia/Dhaka')->startOfDay();
        $asOf = $today->toDateString();
        /** @var array<int, list<array{label: string, date: string, invoiced: string, allocated: string, outstanding: string, bucket: string}>> $lines */
        $lines = [];

        foreach ($this->obligations($asOf) as $obligation) {
            $lines[$obligation['party_id']][] = $this->line(
                $obligation['label'],
                $obligation['date'],
                $obligation['amount'],
                $obligation['allocated'],
                $obligation['amount']->minus($obligation['allocated']),
                $today,
            );
        }

        foreach ($this->openings($asOf) as $opening) {
            $lines[$opening['party_id']][] = $this->line(
                'Opening balance',
                $opening['date'],
                $opening['amount'],
                Money::zero(),
                $opening['amount'],
                $today,
            );
        }

        foreach ($this->adjustments($asOf) as $adjustment) {
            $lines[$adjustment['party_id']][] = $this->line(
                $adjustment['label'],
                $adjustment['date'],
                $adjustment['amount'],
                Money::zero(),
                $adjustment['amount'],
                $today,
            );
        }

        $advances = $this->advances($asOf);
        $payables = $this->payables($asOf);
        $partyIds = array_keys($lines);

        foreach (array_keys($advances) as $partyId) {
            $partyIds[] = $partyId;
        }

        foreach ($payables as $partyId => $amount) {
            if (! Decimal::of($amount)->isZero()) {
                $partyIds[] = $partyId;
            }
        }

        $partyIds = array_values(array_unique($partyIds));
        $rows = [];
        $grand = Money::zero();

        foreach (Party::query()->whereIn('id', $partyIds)->orderBy('legal_name')->orderBy('id')->get() as $party) {
            $id = (int) $party->id;
            $partyLines = $lines[$id] ?? [];
            $outstanding = Money::zero();

            foreach ($partyLines as $partyLine) {
                $outstanding = $outstanding->plus(Money::of($partyLine['outstanding']));
            }

            $advance = Money::of($advances[$id] ?? '0.00');
            $total = $outstanding->minus($advance);
            $grand = $grand->plus($total);
            $rows[] = [
                'party_id' => $id,
                'name' => $party->legal_name,
                'lines' => $partyLines,
                'advance' => $advance->amount(),
                'total' => $total->amount(),
                'payable' => Money::of($payables[$id] ?? '0.00')->amount(),
            ];
        }

        return [
            'as_of' => $asOf,
            'parties' => $rows,
            'total' => $grand->amount(),
        ];
    }

    /**
     * @return array{label: string, date: string, invoiced: string, allocated: string, outstanding: string, bucket: string}
     */
    private function line(string $label, string $date, Money $invoiced, Money $allocated, Money $outstanding, Carbon $today): array
    {
        return [
            'label' => $label,
            'date' => $date,
            'invoiced' => $invoiced->amount(),
            'allocated' => $allocated->amount(),
            'outstanding' => $outstanding->amount(),
            'bucket' => $this->bucket($date, $today),
        ];
    }

    /**
     * @return list<array{party_id: int, label: string, date: string, amount: Money, allocated: Money}>
     */
    private function obligations(string $asOf): array
    {
        $account = $this->posting->account('accounts_payable');
        $rows = [];

        foreach (Journal::query()
            ->where('status', JournalStatus::Posted->value)
            ->where('source', JournalSource::Manual->value)
            ->whereDate('journal_date', '<=', $asOf)
            ->whereHas('lines', fn ($query) => $query->where('account_id', $account->id)->whereNotNull('party_id'))
            ->whereDoesntHave('reversal', fn ($reversal) => $reversal
                ->where('status', JournalStatus::Posted->value)
                ->whereDate('journal_date', '<=', $asOf))
            ->with('lines')
            ->orderBy('journal_date')
            ->orderBy('id')
            ->get() as $journal) {
            $nets = [];

            foreach ($journal->lines as $line) {
                if ((int) $line->account_id !== (int) $account->id || ! is_numeric($line->party_id)) {
                    continue;
                }

                $partyId = (int) $line->party_id;
                $nets[$partyId] = ($nets[$partyId] ?? Money::zero())
                    ->plus(Money::of($line->decimalAttribute('credit')))
                    ->minus(Money::of($line->decimalAttribute('debit')));
            }

            foreach ($nets as $partyId => $net) {
                if (Decimal::of($net->amount())->isZero()) {
                    continue;
                }

                $positive = Decimal::of($net->amount())->compare(Decimal::of('0.00')) > 0;
                $rows[] = [
                    'party_id' => $partyId,
                    'label' => $positive ? (string) $journal->number : 'Adjustment '.$journal->number,
                    'date' => $journal->journalDateString(),
                    'amount' => $net,
                    'allocated' => $positive ? $this->balances->allocated($journal, $partyId, false, $asOf) : Money::zero(),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{party_id: int, date: string, amount: Money}>
     */
    private function openings(string $asOf): array
    {
        $account = $this->posting->account('accounts_payable');
        $rows = [];

        foreach (JournalLine::query()
            ->where('account_id', $account->id)
            ->whereNotNull('party_id')
            ->whereHas('journal', fn ($query) => $query
                ->where('status', JournalStatus::Posted->value)
                ->where('source', JournalSource::Opening->value)
                ->whereDate('journal_date', '<=', $asOf)
                ->whereDoesntHave('reversal', fn ($reversal) => $reversal
                    ->where('status', JournalStatus::Posted->value)
                    ->whereDate('journal_date', '<=', $asOf)))
            ->with('journal')
            ->orderBy('id')
            ->get() as $line) {
            if (! is_numeric($line->party_id) || ! $line->journal instanceof Journal) {
                continue;
            }

            $rows[] = [
                'party_id' => (int) $line->party_id,
                'date' => $line->journal->journalDateString(),
                'amount' => Money::of($line->decimalAttribute('credit'))->minus(Money::of($line->decimalAttribute('debit'))),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{party_id: int, label: string, date: string, amount: Money}>
     */
    private function adjustments(string $asOf): array
    {
        $account = $this->posting->account('accounts_payable');
        $rows = [];

        foreach (JournalLine::query()
            ->where('account_id', $account->id)
            ->whereNotNull('party_id')
            ->whereHas('journal', fn ($query) => $query
                ->where('status', JournalStatus::Posted->value)
                ->whereDate('journal_date', '<=', $asOf)
                ->where('source', JournalSource::Reversal->value)
                ->whereHas('reverses', fn ($original) => $original
                    ->where('source', JournalSource::Manual->value)
                    ->whereDate('journal_date', '>', $asOf)))
            ->with('journal')
            ->orderBy('id')
            ->get() as $line) {
            if (! is_numeric($line->party_id) || ! $line->journal instanceof Journal) {
                continue;
            }

            $amount = Money::of($line->decimalAttribute('credit'))->minus(Money::of($line->decimalAttribute('debit')));

            if (Decimal::of($amount->amount())->isZero()) {
                continue;
            }

            $label = 'Reversal ';
            $rows[] = [
                'party_id' => (int) $line->party_id,
                'label' => $label.$line->journal->number,
                'date' => $line->journal->journalDateString(),
                'amount' => $amount,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function advances(string $asOf): array
    {
        $amounts = [];

        foreach (Payment::query()
            ->where('status', PaymentStatus::Recorded->value)
            ->whereDate('payment_date', '<=', $asOf)
            ->with('allocations.journal')
            ->orderBy('id')
            ->get() as $payment) {
            $paid = Money::of($payment->decimalAttribute('amount'));
            $allocated = Money::zero();

            foreach ($payment->allocations as $allocation) {
                $journal = $allocation->journal;
                $journalDay = $journal instanceof Journal ? $journal->journalDateString() : '';

                if ($journalDay !== '' && $journalDay > $asOf) {
                    continue;
                }

                $allocated = $allocated->plus(Money::of($allocation->decimalAttribute('amount')));
            }

            $partyId = (int) $payment->party_id;
            $amounts[$partyId] = Money::of($amounts[$partyId] ?? '0.00')->plus($paid->minus($allocated))->amount();
        }

        return $amounts;
    }

    /**
     * @return array<int, string>
     */
    private function payables(string $asOf): array
    {
        $account = $this->posting->account('accounts_payable');
        $amounts = [];

        foreach (JournalLine::query()
            ->where('account_id', $account->id)
            ->whereNotNull('party_id')
            ->whereHas('journal', fn ($query) => $query
                ->where('status', JournalStatus::Posted->value)
                ->whereDate('journal_date', '<=', $asOf))
            ->orderBy('id')
            ->get() as $line) {
            if (! is_numeric($line->party_id)) {
                continue;
            }

            $partyId = (int) $line->party_id;
            $signed = Money::of($line->decimalAttribute('credit'))->minus(Money::of($line->decimalAttribute('debit')));
            $amounts[$partyId] = Money::of($amounts[$partyId] ?? '0.00')->plus($signed)->amount();
        }

        return $amounts;
    }

    private function bucket(string $date, Carbon $today): string
    {
        if ($date === '') {
            return 'current';
        }

        $days = (int) Carbon::parse($date, 'Asia/Dhaka')->startOfDay()->diffInDays($today, false);

        if ($days <= 0) {
            return 'current';
        }

        if ($days <= 30) {
            return '1_30';
        }

        if ($days <= 60) {
            return '31_60';
        }

        if ($days <= 90) {
            return '61_90';
        }

        return '90_plus';
    }
}
