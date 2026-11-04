<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quotation_revision_id',
    'decision',
    'source',
    'respondent_name',
    'respondent_designation',
    'remarks',
    'recorded_by',
    'quotation_share_token_id',
    'ip_address',
    'user_agent',
    'created_at',
])]
class QuotationResponse extends Model
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
     * @return BelongsTo<QuotationRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'quotation_revision_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function decisionLabel(): string
    {
        return $this->getAttribute('decision') === 'accepted' ? 'Accepted' : 'Rejected';
    }
}
