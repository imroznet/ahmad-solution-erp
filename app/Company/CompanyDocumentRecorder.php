<?php

namespace App\Company;

use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Models\CompanyProfile;
use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CompanyDocumentRecorder
{
    public function __construct(private PrivateFileStore $files) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<UploadedFile>  $uploads
     */
    public function create(CompanyProfile $profile, array $attributes, array $uploads, User $actor, ?string $note): CompanyDocument
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($profile, $attributes, $uploads, $actor, $note, &$stored): CompanyDocument {
                $category = $this->activeCategory((int) $attributes['document_category_id']);
                $document = CompanyDocument::query()->create([
                    ...$this->payload($attributes),
                    'company_profile_id' => $profile->id,
                    'document_category_id' => $category->id,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $this->addFiles($document, $uploads, $actor, $note, $stored);

                return $document;
            });
        } catch (Throwable $exception) {
            $this->discard($stored);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CompanyDocument $document, array $attributes, User $actor): CompanyDocument
    {
        return DB::transaction(function () use ($document, $attributes, $actor): CompanyDocument {
            $categoryId = (int) $attributes['document_category_id'];
            $category = DocumentCategory::query()->whereKey($categoryId)->first();

            if (! $category instanceof DocumentCategory || (! $category->is_active && $category->id !== $document->document_category_id)) {
                throw ValidationException::withMessages([
                    'document_category_id' => 'Choose an active document category.',
                ]);
            }

            $document->fill([
                ...$this->payload($attributes),
                'document_category_id' => $category->id,
                'updated_by' => $actor->id,
            ]);
            $document->save();

            return $document;
        });
    }

    /**
     * @param  list<UploadedFile>  $uploads
     */
    public function addVersions(CompanyDocument $document, array $uploads, User $actor, ?string $note): CompanyDocument
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($document, $uploads, $actor, $note, &$stored): CompanyDocument {
                CompanyDocument::query()->whereKey($document->id)->lockForUpdate()->first();
                $this->addFiles($document, $uploads, $actor, $note, $stored);
                $document->forceFill(['updated_by' => $actor->id])->save();

                return $document;
            });
        } catch (Throwable $exception) {
            $this->discard($stored);

            throw $exception;
        }
    }

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $stored
     */
    private function addFiles(CompanyDocument $document, array $uploads, User $actor, ?string $note, array &$stored): void
    {
        if ($uploads === []) {
            throw ValidationException::withMessages([
                'files' => 'Choose at least one file.',
            ]);
        }

        $next = (int) CompanyDocumentVersion::query()
            ->where('company_document_id', $document->id)
            ->max('version_number');

        foreach ($uploads as $upload) {
            $next++;
            $file = $this->files->store($upload, 'company-documents/'.$document->id);
            $stored[] = $file->path;

            CompanyDocumentVersion::query()->create([
                'company_document_id' => $document->id,
                'version_number' => $next,
                'storage_path' => $file->path,
                'original_filename' => $file->originalFilename,
                'mime_type' => $file->mimeType,
                'extension' => $file->extension,
                'size_bytes' => $file->sizeBytes,
                'note' => $note,
                'uploaded_by' => $actor->id,
            ]);
        }
    }

    private function activeCategory(int $id): DocumentCategory
    {
        $category = DocumentCategory::query()->whereKey($id)->where('is_active', true)->first();

        if (! $category instanceof DocumentCategory) {
            throw ValidationException::withMessages([
                'document_category_id' => 'Choose an active document category.',
            ]);
        }

        return $category;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function payload(array $attributes): array
    {
        return [
            'title' => (string) $attributes['title'],
            'document_number' => $this->nullableString($attributes['document_number'] ?? null),
            'issuing_authority' => $this->nullableString($attributes['issuing_authority'] ?? null),
            'issued_on' => $this->nullableString($attributes['issued_on'] ?? null),
            'expires_on' => $this->nullableString($attributes['expires_on'] ?? null),
            'verification_status' => (string) $attributes['verification_status'],
            'remarks' => $this->nullableString($attributes['remarks'] ?? null),
        ];
    }

    /**
     * @param  list<string>  $paths
     */
    private function discard(array $paths): void
    {
        foreach ($paths as $path) {
            $this->files->delete($path);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
