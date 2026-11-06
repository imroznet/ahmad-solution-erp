<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mushak_63_document_id',
    'event',
    'actor_id',
    'remarks',
    'created_at',
])]
class Mushak63Event extends Model
{
    protected $table = 'mushak_63_events';

    public const ISSUED = 'issued';

    public const VOIDED = 'voided';

    public $timestamps = false;

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
     * @return BelongsTo<Mushak63Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Mushak63Document::class, 'mushak_63_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
