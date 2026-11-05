<?php

namespace App\Models;

use App\Documents\DocumentStatus;
use App\Models\Concerns\ReadsStoredValues;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'number',
    'quotation_id',
    'quotation_revision_id',
    'status',
    'invoice_date',
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
    'customer_notes',
    'internal_notes',
    'tax_notice',
    'tax_treatment',
    'subtotal',
    'total',
    'currency_code',
    'company_snapshot',
    'issued_at',
    'issued_by',
    'voided_at',
    'voided_by',
    'void_reason',
    'created_by',
])]
class CommercialInvoice extends Model
{
    use ReadsStoredValues;

    /**
     * Columns that may change after an invoice leaves draft.
     *
     * @var list<string>
     */
    public const MUTABLE_AFTER_ISSUE = [
        'status',
        'updated_at',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected static function booted(): void
    {
        static::updating(function (CommercialInvoice $invoice): void {
            $original = $invoice->getOriginal('status');

            if (! is_string($original) || $original === DocumentStatus::DRAFT) {
                return;
            }

            $forbidden = array_values(array_diff(array_keys($invoice->getDirty()), self::MUTABLE_AFTER_ISSUE));

            if ($forbidden !== []) {
                throw new LogicException('An issued commercial invoice cannot change its content.');
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
            'invoice_date' => 'date',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'company_snapshot' => 'array',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<QuotationRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'quotation_revision_id');
    }

    /**
     * @return HasMany<CommercialInvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CommercialInvoiceLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<CommercialInvoiceEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CommercialInvoiceEvent::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function statusLabel(): string
    {
        return DocumentStatus::label($this->stringAttribute('status'));
    }

    public function displayNumber(): string
    {
        $number = $this->stringAttribute('number');

        return $number !== '' ? $number : 'Draft';
    }

    public function totalMoney(): Money
    {
        return Money::of($this->decimalAttribute('total'), $this->stringAttribute('currency_code') ?: Money::DEFAULT_CURRENCY);
    }
}
