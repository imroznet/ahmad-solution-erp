<?php

namespace App\Models;

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
    'quotation_id',
    'status',
    'buyer_source',
    'party_id',
    'document_date',
    'destination',
    'calculation_precision',
    'rounding_method',
    'tax_review_status',
    'tax_review_note',
    'tax_review_confirmed_at',
    'tax_review_confirmed_by',
    'tax_rule_id',
    'rule_snapshot',
    'seller_legal_name',
    'seller_bin',
    'seller_tin',
    'seller_address',
    'seller_phone',
    'seller_email',
    'seller_snapshot',
    'buyer_name',
    'buyer_bin',
    'buyer_tin',
    'buyer_address',
    'buyer_phone',
    'buyer_email',
    'buyer_snapshot',
    'responsible_name',
    'responsible_designation',
    'subtotal_base',
    'total_vat',
    'total_adjustment',
    'total_final',
    'total_supplementary_duty',
    'currency_code',
    'calculation_snapshot',
    'internal_notes',
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

            if (! is_string($original) || $original === 'draft') {
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
            'tax_review_confirmed_at' => 'datetime',
            'rule_snapshot' => 'array',
            'seller_snapshot' => 'array',
            'buyer_snapshot' => 'array',
            'subtotal_base' => 'decimal:4',
            'total_vat' => 'decimal:4',
            'total_adjustment' => 'decimal:4',
            'total_final' => 'decimal:4',
            'total_supplementary_duty' => 'decimal:4',
            'calculation_snapshot' => 'array',
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
     * @return BelongsTo<TaxRule, $this>
     */
    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class);
    }

    /**
     * @return HasMany<MushakInvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(MushakInvoiceLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<MushakInvoiceEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(MushakInvoiceEvent::class)->orderBy('id');
    }

    public function displayNumber(): string
    {
        $number = $this->stringAttribute('number');

        return $number !== '' ? $number : 'Draft';
    }

    public function statusLabel(): string
    {
        return match ($this->stringAttribute('status')) {
            'draft' => 'Draft',
            'issued' => 'Issued',
            'void' => 'Void',
            default => $this->stringAttribute('status'),
        };
    }

    public function reviewLabel(): string
    {
        return $this->stringAttribute('tax_review_status') === 'treatment_confirmed'
            ? 'Treatment confirmed by a user'
            : 'Review required';
    }

    public function amountsReconcile(): bool
    {
        $final = $this->getAttribute('total_final');
        $base = $this->getAttribute('subtotal_base');
        $vat = $this->getAttribute('total_vat');
        $adjustment = $this->getAttribute('total_adjustment');

        if (! is_string($final) || ! is_string($base) || ! is_string($vat) || ! is_string($adjustment)) {
            return false;
        }

        $sum = Decimal::of($base)->plus(Decimal::of($vat))->plus(Decimal::of($adjustment));

        return $sum->compare(Decimal::of($final)) === 0;
    }
}
