<?php

namespace App\Models;

use App\Documents\DocumentStatus;
use App\Models\Concerns\ReadsStoredValues;
use App\Tax\TaxReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'commercial_invoice_id',
    'quotation_id',
    'status',
    'number',
    'buyer_source',
    'party_id',
    'buyer_name',
    'buyer_bin',
    'buyer_tin',
    'buyer_address',
    'buyer_phone',
    'buyer_email',
    'destination',
    'responsible_name',
    'responsible_designation',
    'issue_date',
    'issue_time',
    'currency_code',
    'total_value',
    'total_supplementary_duty',
    'total_vat',
    'total_inclusive',
    'tax_review_status',
    'treatment_rule_id',
    'treatment_rule_snapshot',
    'treatment_note',
    'treatment_recorded_by',
    'treatment_recorded_at',
    'company_snapshot',
    'customer_snapshot',
    'source_references',
    'internal_notes',
    'manual_vat_notice',
    'issued_at',
    'issued_by',
    'voided_at',
    'voided_by',
    'void_reason',
    'created_by',
    'updated_by',
])]
class MushakDocument extends Model
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
        'tax_review_status',
        'treatment_rule_id',
        'treatment_rule_snapshot',
        'treatment_note',
        'treatment_recorded_by',
        'treatment_recorded_at',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (MushakDocument $document): void {
            if ($document->getAttribute('rate_determined_by_erp') === true) {
                throw new LogicException('The ERP must not be recorded as the source of a VAT rate.');
            }
        });

        static::updating(function (MushakDocument $document): void {
            $original = $document->getOriginal('status');

            if (! is_string($original) || $original === DocumentStatus::DRAFT) {
                return;
            }

            $forbidden = array_values(array_diff(array_keys($document->getDirty()), self::MUTABLE_AFTER_ISSUE));

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
            'issue_date' => 'date',
            'total_value' => 'decimal:2',
            'total_supplementary_duty' => 'decimal:2',
            'total_vat' => 'decimal:2',
            'total_inclusive' => 'decimal:2',
            'rate_determined_by_erp' => 'boolean',
            'treatment_rule_snapshot' => 'array',
            'company_snapshot' => 'array',
            'customer_snapshot' => 'array',
            'source_references' => 'array',
            'treatment_recorded_at' => 'datetime',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CommercialInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'commercial_invoice_id');
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return HasMany<MushakDocumentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(MushakDocumentLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<MushakDocumentLine, $this>
     */
    public function allLines(): HasMany
    {
        return $this->hasMany(MushakDocumentLine::class)->withoutGlobalScope('current')->orderBy('id');
    }

    /**
     * @return HasMany<MushakDocumentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(MushakDocumentEvent::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<TaxRule, $this>
     */
    public function treatmentRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class, 'treatment_rule_id');
    }

    /**
     * @return HasMany<VdsRecord, $this>
     */
    public function vdsRecords(): HasMany
    {
        return $this->hasMany(VdsRecord::class);
    }

    /**
     * @return HasMany<AitRecord, $this>
     */
    public function aitRecords(): HasMany
    {
        return $this->hasMany(AitRecord::class);
    }

    public function displayNumber(): string
    {
        $number = $this->stringAttribute('number');

        return $number !== '' ? $number : 'Draft Mushak 6.3';
    }

    public function statusLabel(): string
    {
        return DocumentStatus::label($this->stringAttribute('status'));
    }

    public function reviewLabel(): string
    {
        return TaxReviewStatus::label($this->stringAttribute('tax_review_status'));
    }

    public function nullableDecimal(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function nullableAttribute(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
