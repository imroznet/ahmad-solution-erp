<?php

namespace App\Models;

use App\Models\Concerns\RejectsHistoryChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mushak63_document_id',
    'event',
    'actor_id',
    'remarks',
])]
class Mushak63Event extends Model
{
    use RejectsHistoryChanges;

    public const PREPARED = 'prepared';

    public const UPDATED = 'updated';

    public const ISSUE_BLOCKED = 'issue_blocked';

    public const VOIDED = 'voided';

    public const PDF_DOWNLOADED = 'pdf_downloaded';

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
        return $this->belongsTo(Mushak63Document::class, 'mushak63_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
