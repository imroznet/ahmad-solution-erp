<?php

namespace App\Models;

use App\Accounting\AccountingException;
use App\Accounting\PaymentStatus;
use App\Models\Concerns\ReadsStoredValues;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id',
    'journal_id',
    'amount',
])]
class PaymentAllocation extends Model
{
    use ReadsStoredValues;

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new AccountingException('A payment allocation cannot be changed.');
        });

        static::deleting(function (PaymentAllocation $allocation): void {
            $status = $allocation->payment?->status;

            if ($status !== PaymentStatus::Void) {
                throw new AccountingException('A payment allocation cannot be deleted.');
            }
        });
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
