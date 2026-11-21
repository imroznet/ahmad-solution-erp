<?php

namespace App\Accounting;

use App\Models\Journal;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Decimal;
use App\Support\Money;

/**
 * Outstanding on a posted manual journal: Accounts Payable credited to the supplier, minus recorded allocations.
 */
final class PaymentBalance
{
    public function __construct(private DocumentPosting $posting) {}

    public function obligation(Journal $journal, int $partyId): Money
    {
        $accountId = (int) $this->posting->account('accounts_payable')->id;
        $amount = Money::zero();

        foreach ($journal->lines()->get() as $line) {
            if ((int) $line->account_id !== $accountId || (int) $line->party_id !== $partyId) {
                continue;
            }

            $amount = $amount
                ->plus(Money::of($line->decimalAttribute('credit')))
                ->minus(Money::of($line->decimalAttribute('debit')));
        }

        return $amount;
    }

    public function allocated(Journal $journal, int $partyId, bool $lock = false, ?string $onOrBefore = null): Money
    {
        $allocated = Money::zero();

        // A locking read is a current read. Under REPEATABLE READ a plain select
        // would keep the snapshot from the earlier party read and miss an
        // allocation that committed while this transaction waited for the journal.
        $query = PaymentAllocation::query()->where('journal_id', $journal->id);

        if ($lock) {
            $query->orderBy('id')->lockForUpdate();
        } else {
            $query->whereHas('payment', fn ($payments) => $payments->where('status', PaymentStatus::Recorded->value))
                ->with('payment');
        }

        foreach ($query->get() as $row) {
            $payment = $lock
                ? Payment::query()->whereKey($row->payment_id)->lockForUpdate()->first()
                : $row->payment;

            if (! $payment instanceof Payment || $payment->status !== PaymentStatus::Recorded) {
                continue;
            }

            if ((int) $payment->party_id !== $partyId) {
                continue;
            }

            if ($onOrBefore !== null && $payment->paymentDateString() > $onOrBefore) {
                continue;
            }

            $allocated = $allocated->plus(Money::of($row->decimalAttribute('amount')));
        }

        return $allocated;
    }

    public function outstanding(Journal $journal, int $partyId, bool $lock = false): Money
    {
        $remaining = $this->obligation($journal, $partyId)->minus($this->allocated($journal, $partyId, $lock));

        if (Decimal::of($remaining->amount())->isNegative()) {
            return Money::zero();
        }

        return $remaining;
    }
}
