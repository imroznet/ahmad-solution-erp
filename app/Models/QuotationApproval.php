<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quotation_revision_id',
    'action',
    'user_id',
    'remarks',
    'created_at',
])]
class QuotationApproval extends Model
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->getAttribute('action')) {
            'submitted' => 'Submitted for approval',
            'approved' => 'Approved',
            'returned' => 'Returned to draft',
            default => 'Recorded',
        };
    }
}
