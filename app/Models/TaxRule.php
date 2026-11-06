<?php

namespace App\Models;

use App\Models\Concerns\ReadsStoredValues;
use App\Tax\TaxRuleStatus;
use App\Tax\TaxType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'tax_type',
    'rule_key',
    'version',
    'supersedes_rule_id',
    'supply_classification',
    'name',
    'rate',
    'rate_kind',
    'calculation_basis',
    'rounding_rule',
    'price_treatment',
    'threshold_amount',
    'exemption_note',
    'required_supporting_data',
    'legal_instrument',
    'section_reference',
    'source_url',
    'notes',
    'publication_date',
    'effective_from',
    'effective_to',
    'status',
    'created_by',
    'updated_by',
    'approved_by',
    'approved_at',
    'retired_by',
    'retired_at',
])]
class TaxRule extends Model
{
    use ReadsStoredValues;

    protected static function booted(): void
    {
        static::updating(function (TaxRule $rule): void {
            $original = $rule->getOriginal('status');

            if (! is_string($original) || $original === TaxRuleStatus::DRAFT) {
                return;
            }

            $allowed = $original === TaxRuleStatus::APPROVED
                ? ['effective_to', 'status', 'retired_by', 'retired_at', 'updated_at', 'updated_by']
                : ['updated_at'];
            $forbidden = array_values(array_diff(array_keys($rule->getDirty()), $allowed));

            if ($forbidden !== []) {
                throw new LogicException('An approved tax rule cannot be rewritten.');
            }

            if ($original === TaxRuleStatus::RETIRED) {
                return;
            }

            $status = $rule->getAttribute('status');

            if ($status !== $original && $status !== TaxRuleStatus::RETIRED) {
                throw new LogicException('An approved tax rule can only be retired.');
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
            'version' => 'integer',
            'rate' => 'decimal:4',
            'threshold_amount' => 'decimal:2',
            'publication_date' => 'date',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TaxRule, $this>
     */
    public function supersededRule(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_rule_id');
    }

    /**
     * @return HasMany<TaxRuleEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(TaxRuleEvent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nullableDecimal(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function typeLabel(): string
    {
        return TaxType::label($this->stringAttribute('tax_type'));
    }

    public function statusLabel(): string
    {
        return TaxRuleStatus::label($this->stringAttribute('status'));
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'id' => $this->getKey(),
            'tax_type' => $this->stringAttribute('tax_type'),
            'rule_key' => $this->stringAttribute('rule_key'),
            'version' => (int) $this->getAttribute('version'),
            'supply_classification' => $this->nullableAttribute('supply_classification'),
            'name' => $this->stringAttribute('name'),
            'rate' => $this->nullableDecimal('rate'),
            'rate_kind' => $this->nullableAttribute('rate_kind'),
            'calculation_basis' => $this->nullableAttribute('calculation_basis'),
            'legal_instrument' => $this->stringAttribute('legal_instrument'),
            'section_reference' => $this->nullableAttribute('section_reference'),
            'source_url' => $this->nullableAttribute('source_url'),
            'effective_from' => $this->dateString('effective_from'),
            'effective_to' => $this->dateString('effective_to'),
            'rate_determined_by_erp' => false,
        ];
    }

    public function nullableAttribute(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function dateString(string $key): ?string
    {
        $value = $this->getAttribute($key);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
