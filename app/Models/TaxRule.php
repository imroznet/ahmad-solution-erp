<?php

namespace App\Models;

use App\Models\Concerns\ReadsStoredValues;
use App\Tax\TaxType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

#[Fillable([
    'tax_type',
    'rule_code',
    'version',
    'supersedes_rule_id',
    'supply_classification',
    'documented_rate',
    'rate_kind',
    'calculation_basis',
    'rounding_note',
    'price_treatment',
    'threshold_note',
    'exemption_note',
    'supporting_data_note',
    'effective_from',
    'effective_to',
    'legal_instrument',
    'section_reference',
    'source_url',
    'publication_date',
    'instrument_effective_date',
    'notes',
    'approval_status',
    'activity_status',
    'approved_at',
    'approved_by',
    'retired_at',
    'retired_by',
    'created_by',
    'updated_by',
])]
class TaxRule extends Model
{
    use ReadsStoredValues;

    /**
     * @var list<string>
     */
    public const MUTABLE_WHEN_APPROVED = [
        'effective_to',
        'activity_status',
        'retired_at',
        'retired_by',
        'updated_by',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (TaxRule $rule): void {
            if ($rule->getOriginal('approval_status') !== 'approved') {
                return;
            }

            $forbidden = array_values(array_diff(array_keys($rule->getDirty()), self::MUTABLE_WHEN_APPROVED));

            if ($forbidden !== []) {
                throw new LogicException('An approved tax rule cannot change its legal text.');
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
            'documented_rate' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'publication_date' => 'date',
            'instrument_effective_date' => 'date',
            'approved_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TaxRule, $this>
     */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_rule_id');
    }

    public function dateString(string $key): ?string
    {
        $value = $this->getAttribute($key);

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return substr($value, 0, 10);
    }

    public function typeLabel(): string
    {
        return TaxType::label($this->stringAttribute('tax_type'));
    }
}
