<?php

namespace App\Models;

use App\Accounting\AccountingException;
use App\Accounting\PaymentStatus;
use App\Models\Concerns\ReadsStoredValues;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'number',
    'party_id',
    'account_id',
    'payment_date',
    'amount',
    'mode',
    'reference',
    'cash_flow_category',
    'status',
    'void_reason',
    'voided_at',
    'voided_by',
    'created_by',
])]
class Payment extends Model
{
    use ReadsStoredValues;

    /**
     * @var list<string>
     */
    private const VOID_FIELDS = [
        'status',
        'void_reason',
        'voided_at',
        'voided_by',
        'updated_at',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (Payment $payment): void {
            if ($payment->getRawOriginal('status') !== PaymentStatus::Recorded->value) {
                throw new AccountingException('A void payment cannot be changed.');
            }

            foreach (array_keys($payment->getDirty()) as $key) {
                if (! in_array($key, self::VOID_FIELDS, true)) {
                    throw new AccountingException('A recorded payment cannot be changed. Void it and record another.');
                }
            }
        });

        static::deleting(function (): void {
            throw new AccountingException('A payment cannot be deleted.');
        });
    }

    public function paymentDateString(): string
    {
        $date = $this->payment_date;

        return $date instanceof Carbon ? $date->toDateString() : '';
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
