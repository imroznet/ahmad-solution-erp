<?php

namespace App\Http\Controllers;

use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyDocumentDownloadController extends Controller
{
    public function __invoke(Request $request, CompanyDocument $document, CompanyDocumentVersion $version, AuditLogger $audit): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasPermission('company.documents.download'), 403);
        abort_unless((int) $version->company_document_id === (int) $document->id, 404);

        $prefix = 'company-documents/'.$document->id.'/';
        $path = $version->storage_path;
        abort_unless(str_starts_with($path, $prefix) && ! str_contains($path, '..') && ! str_contains($path, '\\'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $allowed = config('asl.documents.mimes');
        $mime = 'application/octet-stream';

        if (is_array($allowed) && in_array($version->mime_type, $allowed, true)) {
            $mime = $version->mime_type;
        }

        $audit->record('company_document_downloaded', $document, newValues: [
            'version_id' => $version->id,
            'version_number' => $version->version_number,
            'original_filename' => $version->original_filename,
        ], actor: $user);

        return Storage::disk('local')->download($path, $version->original_filename, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
