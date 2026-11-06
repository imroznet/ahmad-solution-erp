<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'tax_rule_version_id',
    'event',
    'actor_id',
    'remarks',
])]
class TaxRuleEvent extends Model
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const AMENDED = 'amended';

    public const APPROVED = 'approved';

    public const CLOSED = 'closed';

    public const RETIRED = 'retired';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('A tax rule event cannot be changed.');
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
     * @return BelongsTo<TaxRuleVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'tax_rule_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
