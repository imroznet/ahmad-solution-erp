<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mushak_document_id',
    'event',
    'actor_id',
    'remarks',
    'created_at',
])]
class MushakDocumentEvent extends Model
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const ISSUED = 'issued';

    public const VOIDED = 'voided';

    public const TREATMENT_RECORDED = 'treatment_recorded';

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
     * @return BelongsTo<MushakDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(MushakDocument::class, 'mushak_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
