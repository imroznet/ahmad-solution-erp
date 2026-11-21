<?php

namespace App\Accounting;

use App\Documents\DocumentStatus;
use App\Models\CommercialInvoice;
use App\Models\MushakInvoice;
use App\Models\Receipt;
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

    public function allocated(CommercialInvoice $invoice, bool $lock = false, ?string $onOrBefore = null): Money
    {
        $allocated = Money::zero();

        // A locking read is a current read. Under REPEATABLE READ a plain select
        // would keep the snapshot from the earlier party read and miss an
        // allocation that committed while this transaction waited for the invoice.
        $query = ReceiptAllocation::query()->where('commercial_invoice_id', $invoice->id);

        if ($lock) {
            $query->orderBy('id')->lockForUpdate();
        } else {
            $query->whereHas('receipt', fn ($receipts) => $receipts->where('status', ReceiptStatus::Recorded->value))
                ->with('receipt');
        }

        foreach ($query->get() as $row) {
            $receipt = $lock
                ? Receipt::query()->whereKey($row->receipt_id)->lockForUpdate()->first()
                : $row->receipt;

            if (! $receipt instanceof Receipt || $receipt->status !== ReceiptStatus::Recorded) {
                continue;
            }

            if ($onOrBefore !== null && $receipt->receiptDateString() > $onOrBefore) {
                continue;
            }

            $allocated = $allocated->plus(Money::of($row->decimalAttribute('amount')));
        }

        return $allocated;
    }

    public function outstanding(CommercialInvoice $invoice, bool $lock = false): Money
    {
        $remaining = $this->invoiced($invoice)->minus($this->allocated($invoice, $lock));

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
