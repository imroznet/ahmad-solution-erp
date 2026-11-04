<?php

namespace App\Models;

use App\Quotations\QuotationStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable([
    'quotation_id',
    'revision_number',
    'status',
    'quotation_date',
    'valid_until',
    'currency_code',
    'party_legal_name',
    'party_trading_name',
    'party_kind',
    'party_tin',
    'party_bin',
    'party_vat_registration_number',
    'party_email',
    'party_phone',
    'contact_name',
    'contact_designation',
    'contact_email',
    'contact_phone',
    'billing_address',
    'shipping_address',
    'terms',
    'customer_notes',
    'internal_notes',
    'tax_notice',
    'tax_treatment',
    'subtotal',
    'total',
    'cancel_reason',
    'cancelled_at',
    'cancelled_by',
    'expired_at',
    'expired_by',
    'created_by',
])]
class QuotationRevision extends Model
{
    /**
     * Columns that may change after a revision leaves draft.
     * Commercial content, the customer snapshot, and the totals are not in this list.
     *
     * @var list<string>
     */
    public const STATUS_COLUMNS = [
        'status',
        'updated_at',
        'cancel_reason',
        'cancelled_at',
        'cancelled_by',
        'expired_at',
        'expired_by',
    ];

    protected static function booted(): void
    {
        static::updating(function (QuotationRevision $revision): void {
            $original = $revision->getOriginal('status');

            if (! is_string($original) || $original === QuotationStatus::DRAFT) {
                return;
            }

            $forbidden = array_values(array_diff(array_keys($revision->getDirty()), self::STATUS_COLUMNS));

            if ($forbidden !== []) {
                throw new LogicException('A quotation revision that has left draft cannot change its content.');
            }
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'valid_until' => 'date',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'revision_number' => 'integer',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return HasMany<QuotationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<QuotationApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(QuotationApproval::class)->orderBy('id');
    }

    /**
     * @return HasMany<QuotationSend, $this>
     */
    public function sends(): HasMany
    {
        return $this->hasMany(QuotationSend::class)->orderBy('id');
    }

    /**
     * @return HasOne<QuotationResponse, $this>
     */
    public function response(): HasOne
    {
        return $this->hasOne(QuotationResponse::class);
    }

    /**
     * @return HasMany<QuotationShareToken, $this>
     */
    public function shareTokens(): HasMany
    {
        return $this->hasMany(QuotationShareToken::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return QuotationStatus::label($this->stringAttribute('status'));
    }

    public function reference(): string
    {
        $this->loadMissing('quotation');
        $number = $this->quotation?->stringAttribute('number') ?? '';

        return $number.'-R'.$this->revision_number;
    }

    public function totalMoney(): Money
    {
        return Money::of($this->decimalAttribute('total'), $this->stringAttribute('currency_code') ?: Money::DEFAULT_CURRENCY);
    }

    /**
     * @return list<string>
     */
    public function addressLines(string $attribute): array
    {
        $address = $this->getAttribute($attribute);

        if (! is_array($address)) {
            return [];
        }

        $lines = $address['lines'] ?? null;

        if (! is_array($lines)) {
            return [];
        }

        $result = [];

        foreach ($lines as $line) {
            if (is_string($line) && $line !== '') {
                $result[] = $line;
            }
        }

        return $result;
    }

    public function isPastValidity(): bool
    {
        $validUntil = $this->getAttribute('valid_until');
        $date = null;

        if ($validUntil instanceof \DateTimeInterface) {
            $date = $validUntil->format('Y-m-d');
        } elseif (is_string($validUntil) && preg_match('/^\d{4}-\d{2}-\d{2}/', $validUntil) === 1) {
            $date = substr($validUntil, 0, 10);
        }

        if ($date === null) {
            return false;
        }

        return $date < now()->timezone('Asia/Dhaka')->toDateString();
    }

    public function stringAttribute(string $key): string
    {
        $value = $this->getAttribute($key);

        return is_string($value) ? $value : '';
    }

    public function decimalAttribute(string $key): string
    {
        $value = $this->getAttribute($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return '0.00';
    }
}
