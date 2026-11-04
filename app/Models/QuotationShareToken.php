<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

#[Fillable([
    'quotation_revision_id',
    'token_hash',
    'expires_at',
    'revoked_at',
    'created_by',
    'created_at',
])]
#[Hidden(['token_hash'])]
class QuotationShareToken extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (QuotationShareToken $token): void {
            $forbidden = array_values(array_diff(array_keys($token->getDirty()), ['revoked_at']));

            if ($forbidden !== []) {
                throw new LogicException('A share token can only be revoked.');
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
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<QuotationRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'quotation_revision_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<QuotationShareAccessLog, $this>
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(QuotationShareAccessLog::class)->orderBy('id');
    }

    public function isUsable(): bool
    {
        if ($this->getAttribute('revoked_at') !== null) {
            return false;
        }

        return $this->expiry() instanceof \DateTimeInterface && $this->expiryIsFuture();
    }

    public function expiryIsFuture(): bool
    {
        $expiresAt = $this->expiry();

        if ($expiresAt instanceof \DateTimeInterface) {
            return Carbon::parse($expiresAt)->greaterThan(now());
        }

        return false;
    }

    private function expiry(): mixed
    {
        return $this->getAttribute('expires_at');
    }
}
