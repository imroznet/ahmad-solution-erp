<?php

namespace App\Models;

use App\Models\Concerns\ReadsStoredValues;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'tax_rule_id',
    'version_number',
    'effective_from',
    'effective_to',
    'rate_percent',
    'fixed_amount',
    'calculation_basis',
    'rounding_rule',
    'price_treatment',
    'threshold_amount',
    'exemption_note',
    'legal_instrument',
    'section_reference',
    'source_url',
    'publication_date',
    'legally_effective_on',
    'notes',
    'approval_status',
    'approved_by',
    'approved_at',
    'created_by',
    'retired_at',
    'retired_by',
])]
class TaxRuleVersion extends Model
{
    use ReadsStoredValues;

    /**
     * Fields that may change after approval. The rate and the legal text stay.
     *
     * @var list<string>
     */
    public const MUTABLE_AFTER_APPROVAL = [
        'effective_to',
        'approval_status',
        'retired_at',
        'retired_by',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (TaxRuleVersion $version): void {
            $original = $version->getOriginal('approval_status');

            if (! is_string($original) || $original === 'draft') {
                return;
            }

            $forbidden = array_values(array_diff(array_keys($version->getDirty()), self::MUTABLE_AFTER_APPROVAL));

            if ($forbidden !== []) {
                throw new LogicException('An approved tax-rule version cannot be rewritten.');
            }

            if ($version->isDirty('effective_to') && $version->getOriginal('effective_to') !== null) {
                throw new LogicException('A closed tax-rule window cannot be reopened or moved.');
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
            'effective_from' => 'date',
            'effective_to' => 'date',
            'publication_date' => 'date',
            'legally_effective_on' => 'date',
            'rate_percent' => 'decimal:4',
            'fixed_amount' => 'decimal:2',
            'threshold_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TaxRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class, 'tax_rule_id');
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

    public function isApproved(): bool
    {
        return $this->stringAttribute('approval_status') === 'approved';
    }

    public function nullableDecimal(string $key): ?string
    {
        $value = $this->getAttribute($key);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'id' => $this->getKey(),
            'version_number' => (int) $this->getAttribute('version_number'),
            'effective_from' => $this->dateString('effective_from'),
            'effective_to' => $this->dateString('effective_to'),
            'rate_percent' => $this->nullableDecimal('rate_percent'),
            'fixed_amount' => $this->nullableDecimal('fixed_amount'),
            'calculation_basis' => $this->stringAttribute('calculation_basis'),
            'rounding_rule' => $this->stringAttribute('rounding_rule'),
            'price_treatment' => $this->stringAttribute('price_treatment'),
            'legal_instrument' => $this->stringAttribute('legal_instrument'),
            'section_reference' => $this->nullableAttribute('section_reference'),
            'source_url' => $this->nullableAttribute('source_url'),
            'publication_date' => $this->dateString('publication_date'),
            'legally_effective_on' => $this->dateString('legally_effective_on'),
            'approval_status' => $this->stringAttribute('approval_status'),
        ];
    }

    public function nullableAttribute(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
