<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'mushak_invoice_id',
    'event',
    'actor_id',
    'remarks',
    'created_at',
])]
class MushakEvent extends Model
{
    public const ISSUED = 'issued';

    public const VOIDED = 'voided';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('A Mushak event cannot be changed.');
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
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MushakInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(MushakInvoice::class, 'mushak_invoice_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
