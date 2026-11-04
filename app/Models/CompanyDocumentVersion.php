<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_document_id',
    'version_number',
    'storage_path',
    'original_filename',
    'mime_type',
    'extension',
    'size_bytes',
    'note',
    'uploaded_by',
])]
class CompanyDocumentVersion extends Model
{
    /**
     * @return BelongsTo<CompanyDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(CompanyDocument::class, 'company_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
