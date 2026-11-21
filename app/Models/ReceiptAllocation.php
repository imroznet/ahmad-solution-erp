<?php

namespace App\Models;

use App\Accounting\AccountingException;
use App\Accounting\ReceiptStatus;
use App\Models\Concerns\ReadsStoredValues;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'receipt_id',
    'commercial_invoice_id',
    'amount',
])]
class ReceiptAllocation extends Model
{
    use ReadsStoredValues;

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new AccountingException('A receipt allocation cannot be changed.');
        });

        static::deleting(function (ReceiptAllocation $allocation): void {
            $status = $allocation->receipt?->status;

            if ($status !== ReceiptStatus::Void) {
                throw new AccountingException('A receipt allocation cannot be deleted.');
            }
        });
    }

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * @return BelongsTo<CommercialInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'commercial_invoice_id');
    }
}
