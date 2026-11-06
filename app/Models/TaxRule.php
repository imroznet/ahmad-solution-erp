<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'code',
    'tax_type',
    'name',
    'supply_classification',
    'status',
    'created_by',
    'updated_by',
])]
class TaxRule extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (): bool {
            return false;
        });
    }

    /**
     * @return HasMany<TaxRuleVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TaxRuleVersion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stringAttribute(string $key): string
    {
        $value = $this->getAttribute($key);

        return is_string($value) ? $value : '';
    }

    public function nullableAttribute(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'code' => $this->stringAttribute('code'),
            'tax_type' => $this->stringAttribute('tax_type'),
            'name' => $this->stringAttribute('name'),
            'supply_classification' => $this->nullableAttribute('supply_classification'),
            'status' => $this->stringAttribute('status'),
        ];
    }

    public function assertMutable(): void
    {
        if ($this->stringAttribute('status') === 'retired') {
            throw new LogicException('A retired tax rule cannot be changed.');
        }
    }
}
