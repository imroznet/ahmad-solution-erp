<?php

namespace App\Models;

use App\Models\Concerns\ReadsStoredValues;
use App\Tax\TaxRuleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'rule_group_id',
    'version_number',
    'tax_type',
    'supply_classification',
    'rule_code',
    'rate_basis',
    'rate_percent',
    'fixed_amount',
    'currency_code',
    'calculation_basis',
    'rounding_rule',
    'threshold_amount',
    'threshold_treatment',
    'required_supporting_data',
    'exemption_note',
    'effective_from',
    'effective_to',
    'legal_instrument',
    'section_reference',
    'source_url',
    'publication_date',
    'legal_effective_date',
    'internal_notes',
    'approval_status',
    'supersedes_version_id',
    'created_by',
    'approved_by',
    'approved_at',
    'retired_by',
    'retired_at',
])]
class TaxRuleVersion extends Model
{
    use ReadsStoredValues;

    /**
     * @var list<string>
     */
    public const MUTABLE_AFTER_APPROVAL = [
        'effective_to',
        'approval_status',
        'retired_by',
        'retired_at',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (TaxRuleVersion $version): void {
            $original = $version->getOriginal('approval_status');

            if (! is_string($original) || $original === TaxRuleStatus::DRAFT) {
                return;
            }

            if ($original === TaxRuleStatus::RETIRED) {
                throw new LogicException('A retired tax rule version cannot be changed.');
            }

            $forbidden = array_values(array_diff(array_keys($version->getDirty()), self::MUTABLE_AFTER_APPROVAL));

            if ($forbidden !== []) {
                throw new LogicException('An approved tax rule version cannot change its rate or legal source.');
            }

            if (array_key_exists('effective_to', $version->getDirty()) && $version->getOriginal('effective_to') !== null) {
                throw new LogicException('A closed tax-rule end date cannot be moved.');
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
            'version_number' => 'integer',
            'rate_percent' => 'decimal:4',
            'fixed_amount' => 'decimal:2',
            'threshold_amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'publication_date' => 'date',
            'legal_effective_date' => 'date',
            'approved_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function supersededVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_version_id');
    }

    /**
     * @return HasMany<TaxRuleEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(TaxRuleEvent::class)->orderBy('id');
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
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dateString(string $key): ?string
    {
        $value = $this->getAttribute($key);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Inputs that a later amendment must not rewrite on a pinned document.
     * The amount is added by the calculator when a base is known.
     *
     * @return array<string, mixed>
     */
    public function calculationSnapshot(): array
    {
        return [
            'version_id' => (int) $this->getKey(),
            'rule_group_id' => $this->stringAttribute('rule_group_id'),
            'version_number' => (int) $this->getAttribute('version_number'),
            'tax_type' => $this->stringAttribute('tax_type'),
            'supply_classification' => $this->stringAttribute('supply_classification'),
            'rule_code' => $this->stringAttribute('rule_code'),
            'rate_basis' => $this->stringAttribute('rate_basis'),
            'rate_percent' => $this->nullableDecimal('rate_percent'),
            'fixed_amount' => $this->nullableDecimal('fixed_amount'),
            'calculation_basis' => $this->stringAttribute('calculation_basis'),
            'rounding_rule' => $this->stringAttribute('rounding_rule'),
            'threshold_amount' => $this->nullableDecimal('threshold_amount'),
            'threshold_treatment' => $this->stringAttribute('threshold_treatment'),
            'effective_from' => $this->dateString('effective_from'),
            'effective_to' => $this->dateString('effective_to'),
            'legal_instrument' => $this->stringAttribute('legal_instrument'),
            'section_reference' => $this->stringAttribute('section_reference'),
            'source_url' => $this->stringAttribute('source_url'),
            'publication_date' => $this->dateString('publication_date'),
            'legal_effective_date' => $this->dateString('legal_effective_date'),
        ];
    }

    public function nullableDecimal(string $key): ?string
    {
        $value = $this->getAttribute($key);

        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
