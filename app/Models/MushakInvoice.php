<?php

namespace App\Models;

use App\Documents\DocumentStatus;
use App\Models\Concerns\ReadsStoredValues;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'number',
    'commercial_invoice_id',
    'status',
    'document_date',
    'supply_destination',
    'supply_classification',
    'buyer_source',
    'party_id',
    'buyer_name',
    'buyer_bin',
    'buyer_tin',
    'buyer_address',
    'buyer_phone',
    'buyer_email',
    'calculation_precision',
    'rounding_method',
    'currency_code',
    'base_total',
    'vat_total',
    'adjustment_total',
    'final_total',
    'tax_rule_version_id',
    'rule_explanation',
    'tax_review_status',
    'legal_treatment_confirmed',
    'legal_treatment_note',
    'review_reason',
    'rate_notice',
    'company_snapshot',
    'source_snapshot',
    'customer_notes',
    'internal_notes',
    'prepared_by_designation',
    'issued_at',
    'issued_by',
    'voided_at',
    'voided_by',
    'void_reason',
    'created_by',
])]
class MushakInvoice extends Model
{
    use ReadsStoredValues;

    /**
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
        static::updating(function (MushakInvoice $invoice): void {
            $original = $invoice->getOriginal('status');

            if (! is_string($original) || $original === DocumentStatus::DRAFT) {
                return;
            }

            $forbidden = array_values(array_diff(array_keys($invoice->getDirty()), self::MUTABLE_AFTER_ISSUE));

            if ($forbidden !== []) {
                throw new LogicException('An issued Mushak 6.3 cannot change its content.');
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
            'document_date' => 'date',
            'calculation_precision' => 'integer',
            'base_total' => 'decimal:4',
            'vat_total' => 'decimal:4',
            'adjustment_total' => 'decimal:4',
            'final_total' => 'decimal:4',
            'legal_treatment_confirmed' => 'boolean',
            'company_snapshot' => 'array',
            'source_snapshot' => 'array',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CommercialInvoice, $this>
     */
    public function commercialInvoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<TaxRuleVersion, $this>
     */
    public function taxRuleVersion(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class);
    }

    /**
     * @return HasMany<MushakLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(MushakLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<MushakEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(MushakEvent::class)->orderBy('id');
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

    public function storedTotal(string $key): ?string
    {
        $value = $this->getAttribute($key);

        if ($value === null || $value === '') {
            return null;
        }

        return $this->decimalAttribute($key);
    }

    public function hasCalculatedVat(): bool
    {
        return $this->storedTotal('vat_total') !== null
            && Decimal::of($this->decimalAttribute('base_total'))->plus(Decimal::of($this->decimalAttribute('vat_total')))
                ->plus(Decimal::of($this->decimalAttribute('adjustment_total')))
                ->compare(Decimal::of($this->decimalAttribute('final_total'))) === 0;
    }
}
