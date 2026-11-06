<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'delivery_challan_id',
    'event',
    'actor_id',
    'recipient_name',
    'remarks',
    'created_at',
])]
class DeliveryChallanEvent extends Model
{
    public const ISSUED = 'issued';

    public const DISPATCHED = 'dispatched';

    public const ACKNOWLEDGED = 'acknowledged';

    public const VOIDED = 'voided';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('A delivery challan event cannot be changed.');
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
     * @return BelongsTo<DeliveryChallan, $this>
     */
    public function challan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class, 'delivery_challan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
