<?php

namespace App\Models;

use App\Models\Concerns\ReadsStoredValues;
use App\Tax\TaxRuleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'tax_rule_id',
    'version_number',
    'status',
    'rate_kind',
    'rate_value',
    'fixed_amount',
    'calculation_basis',
    'threshold_note',
    'exemption_note',
    'legal_instrument',
    'section_reference',
    'source_url',
    'publication_date',
    'effective_from',
    'effective_to',
    'notes',
    'created_by',
    'approved_by',
    'approved_at',
    'retired_by',
    'retired_at',
    'retirement_reason',
])]
class TaxRuleVersion extends Model
{
    use ReadsStoredValues;

    /**
     * @var list<string>
     */
    public const RETIREMENT_FIELDS = [
        'status',
        'effective_to',
        'retired_by',
        'retired_at',
        'retirement_reason',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (TaxRuleVersion $version): void {
            $original = $version->getOriginal('status');

            if (! is_string($original) || $original === TaxRuleStatus::DRAFT) {
                return;
            }

            if ($original === TaxRuleStatus::RETIRED) {
                throw new LogicException('A retired tax rule version cannot be changed.');
            }

            $forbidden = array_values(array_diff(array_keys($version->getDirty()), self::RETIREMENT_FIELDS));

            if ($forbidden !== []) {
                throw new LogicException('An approved tax rule version cannot be rewritten.');
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
            'rate_value' => 'decimal:4',
            'fixed_amount' => 'decimal:4',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by');
    }

    public function statusLabel(): string
    {
        return TaxRuleStatus::label($this->stringAttribute('status'));
    }
}
