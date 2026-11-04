<?php

namespace App\Models;

use App\Quotations\QuotationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'party_id',
    'party_role',
    'current_revision_id',
    'status',
    'created_by',
])]
class Quotation extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (): bool {
            return false;
        });
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<QuotationRevision, $this>
     */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'current_revision_id');
    }

    /**
     * @return HasMany<QuotationRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(QuotationRevision::class)->orderBy('revision_number');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return QuotationStatus::label($this->stringAttribute('status'));
    }

    public function stringAttribute(string $key): string
    {
        $value = $this->getAttribute($key);

        return is_string($value) ? $value : '';
    }
}
