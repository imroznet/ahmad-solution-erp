<?php

namespace App\Accounting;

use App\Documents\DocumentStatus;
use App\Models\CommercialInvoice;
use App\Models\MushakInvoice;
use App\Models\ReceiptAllocation;
use App\Support\Decimal;
use App\Support\Money;

/**
 * Outstanding on an issued invoice: invoice total plus issued Mushak VAT, minus recorded allocations.
 */
final class InvoiceBalance
{
    public function invoiced(CommercialInvoice $invoice): Money
    {
        $total = Money::of($invoice->decimalAttribute('total'));
        $mushak = MushakInvoice::query()
            ->where('commercial_invoice_id', $invoice->id)
            ->where('status', 'issued')
            ->first();

        if (! $mushak instanceof MushakInvoice) {
            return $total;
        }

        $stored = $mushak->getAttribute('total_vat');
        $vat = Money::of(is_string($stored) || is_int($stored) ? $stored : 0);

        return $total->plus($vat);
    }

    public function allocated(CommercialInvoice $invoice): Money
    {
        $allocated = Money::zero();
        $rows = ReceiptAllocation::query()
            ->where('commercial_invoice_id', $invoice->id)
            ->whereHas('receipt', fn ($query) => $query->where('status', ReceiptStatus::Recorded->value))
            ->get();

        foreach ($rows as $row) {
            $allocated = $allocated->plus(Money::of($row->decimalAttribute('amount')));
        }

        return $allocated;
    }

    public function outstanding(CommercialInvoice $invoice): Money
    {
        $remaining = $this->invoiced($invoice)->minus($this->allocated($invoice));

        if (Decimal::of($remaining->amount())->isNegative()) {
            return Money::zero();
        }

        return $remaining;
    }

    public function isIssued(CommercialInvoice $invoice): bool
    {
        return $invoice->stringAttribute('status') === DocumentStatus::ISSUED;
    }
}
