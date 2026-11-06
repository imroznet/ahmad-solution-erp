<?php

namespace App\Models;

use App\Documents\DocumentStatus;
use App\Models\Concerns\ReadsStoredValues;
use App\Support\Money;
use App\Tax\TaxReviewStatus;
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
    'buyer_source',
    'party_id',
    'buyer_legal_name',
    'buyer_trading_name',
    'buyer_bin',
    'buyer_tin',
    'buyer_phone',
    'buyer_email',
    'buyer_address',
    'company_snapshot',
    'invoice_number_snapshot',
    'invoice_date_snapshot',
    'tax_rule_version_id',
    'tax_rule_snapshot',
    'review_status',
    'treatment_confirmed_at',
    'treatment_confirmed_by',
    'vat_notice',
    'form_source_url',
    'form_sha256',
    'base_total',
    'vat_total',
    'inclusive_total',
    'supplementary_duty_total',
    'duties_total',
    'currency_code',
    'customer_notes',
    'internal_notes',
    'issued_at',
    'issued_by',
    'voided_at',
    'voided_by',
    'void_reason',
    'created_by',
])]
class Mushak63Document extends Model
{
    use ReadsStoredValues;

    protected $table = 'mushak_63_documents';

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
        static::updating(function (Mushak63Document $document): void {
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
            'document_date' => 'date',
            'invoice_date_snapshot' => 'date',
            'buyer_address' => 'array',
            'company_snapshot' => 'array',
            'tax_rule_snapshot' => 'array',
            'base_total' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'inclusive_total' => 'decimal:2',
            'supplementary_duty_total' => 'decimal:2',
            'duties_total' => 'decimal:2',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'treatment_confirmed_at' => 'datetime',
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
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return HasMany<Mushak63Line, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(Mushak63Line::class, 'mushak_63_document_id')->orderBy('position');
    }

    /**
     * @return HasMany<Mushak63Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Mushak63Event::class, 'mushak_63_document_id');
    }

    /**
     * @return BelongsTo<TaxRuleVersion, $this>
     */
    public function taxRuleVersion(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'tax_rule_version_id');
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function treatmentConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'treatment_confirmed_by');
    }

    public function displayNumber(): string
    {
        $number = $this->stringAttribute('number');

        return $number !== '' ? $number : 'Draft';
    }

    public function statusLabel(): string
    {
        return DocumentStatus::label($this->stringAttribute('status'));
    }

    public function reviewLabel(): string
    {
        return TaxReviewStatus::label($this->stringAttribute('review_status'));
    }

    public function nullableDecimal(string $key): ?string
    {
        $value = $this->getAttribute($key);

        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : (is_int($value) ? (string) $value : null);
    }

    public function money(string $key): ?Money
    {
        $amount = $this->nullableDecimal($key);

        return $amount === null ? null : Money::of($amount);
    }

    /**
     * @return list<string>
     */
    public function buyerLines(): array
    {
        return $this->addressLines('buyer_address');
    }
}
