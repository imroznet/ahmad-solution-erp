<?php

namespace App\Models;

use App\Models\Concerns\ReadsStoredValues;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'tax_type',
    'rule_code',
    'supply_classification',
    'classification_key',
    'created_by',
])]
class TaxRule extends Model
{
    use ReadsStoredValues;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('A tax rule identity cannot be rewritten. Add a version instead.');
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    /**
     * @return HasMany<TaxRuleVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TaxRuleVersion::class)->orderBy('version_number');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
