<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quotation_share_token_id',
    'event',
    'ip_address',
    'user_agent',
    'created_at',
])]
class QuotationShareAccessLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): bool {
            return false;
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    /**
     * @return BelongsTo<QuotationShareToken, $this>
     */
    public function shareToken(): BelongsTo
    {
        return $this->belongsTo(QuotationShareToken::class, 'quotation_share_token_id');
    }
}
