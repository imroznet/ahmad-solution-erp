<?php

namespace App\Accounting;

use App\Documents\DocumentStatus;
use App\Models\CommercialInvoice;
use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\Party;
use App\Models\Receipt;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Receivables by customer, aged from the invoice date against Asia/Dhaka today.
 * An unallocated receipt remainder is an advance. It reduces the party total.
 * There is no separate advance account.
 */
final class ReceivablesAging
{
    public function __construct(
        private InvoiceBalance $balances,
        private DocumentPosting $posting,
    ) {}

    /**
     * @return array{as_of: string, parties: list<array{party_id: int, name: string, lines: list<array{label: string, date: string, invoiced: string, allocated: string, outstanding: string, bucket: string}>, advance: string, total: string, receivable: string}>, total: string}
     */
    public function build(): array
    {
        $today = now()->timezone('Asia/Dhaka')->startOfDay();
        /** @var array<int, list<array{label: string, date: string, invoiced: string, allocated: string, outstanding: string, bucket: string}>> $lines */
        $lines = [];

        foreach ($this->invoices() as $invoice) {
            $partyId = $invoice->quotation?->party_id;

            if (! is_numeric($partyId)) {
                continue;
            }

            $date = $invoice->getAttribute('invoice_date');
            $day = $date instanceof Carbon ? $date->toDateString() : '';
            $invoiced = $this->balances->invoiced($invoice);
            $allocated = $this->balances->allocated($invoice);
            $lines[(int) $partyId][] = $this->line(
                $invoice->displayNumber(),
                $day,
                $invoiced,
                $allocated,
                $invoiced->minus($allocated),
                $today,
            );
        }

        foreach ($this->openings() as $opening) {
            $lines[$opening['party_id']][] = $this->line(
                'Opening balance',
                $opening['date'],
                $opening['amount'],
                Money::zero(),
                $opening['amount'],
                $today,
            );
        }

        $advances = $this->advances();
        $receivables = $this->receivables();
        $partyIds = array_values(array_unique(array_merge(array_keys($lines), array_keys($advances))));
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
                'receivable' => Money::of($receivables[$id] ?? '0.00')->amount(),
            ];
        }

        return [
            'as_of' => $today->toDateString(),
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
     * @return list<CommercialInvoice>
     */
    private function invoices(): array
    {
        return CommercialInvoice::query()
            ->where('status', DocumentStatus::ISSUED)
            ->with('quotation')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @return list<array{party_id: int, date: string, amount: Money}>
     */
    private function openings(): array
    {
        $account = $this->posting->account('accounts_receivable');
        $rows = [];

        foreach (JournalLine::query()
            ->where('account_id', $account->id)
            ->whereNotNull('party_id')
            ->whereHas('journal', fn ($query) => $query
                ->where('status', JournalStatus::Posted->value)
                ->where('source', JournalSource::Opening->value))
            ->with('journal')
            ->orderBy('id')
            ->get() as $line) {
            if (! is_numeric($line->party_id) || ! $line->journal instanceof Journal) {
                continue;
            }

            $rows[] = [
                'party_id' => (int) $line->party_id,
                'date' => $line->journal->journalDateString(),
                'amount' => Money::of($line->decimalAttribute('debit'))->minus(Money::of($line->decimalAttribute('credit'))),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function advances(): array
    {
        $amounts = [];

        foreach (Receipt::query()->where('status', ReceiptStatus::Recorded->value)->with('allocations')->orderBy('id')->get() as $receipt) {
            $credit = Money::of($receipt->decimalAttribute('amount_received'))
                ->plus(Money::of($receipt->decimalAttribute('vds_deducted')))
                ->plus(Money::of($receipt->decimalAttribute('ait_deducted')));
            $allocated = Money::zero();

            foreach ($receipt->allocations as $allocation) {
                $allocated = $allocated->plus(Money::of($allocation->decimalAttribute('amount')));
            }

            $partyId = (int) $receipt->party_id;
            $amounts[$partyId] = Money::of($amounts[$partyId] ?? '0.00')->plus($credit->minus($allocated))->amount();
        }

        return $amounts;
    }

    /**
     * @return array<int, string>
     */
    private function receivables(): array
    {
        $account = $this->posting->account('accounts_receivable');
        $amounts = [];

        foreach (JournalLine::query()
            ->where('account_id', $account->id)
            ->whereNotNull('party_id')
            ->whereHas('journal', fn ($query) => $query->where('status', JournalStatus::Posted->value))
            ->orderBy('id')
            ->get() as $line) {
            if (! is_numeric($line->party_id)) {
                continue;
            }

            $partyId = (int) $line->party_id;
            $signed = Money::of($line->decimalAttribute('debit'))->minus(Money::of($line->decimalAttribute('credit')));
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
