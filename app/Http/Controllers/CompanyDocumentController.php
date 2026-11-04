<?php

namespace App\Http\Controllers;

use App\Company\CompanyDocumentRecorder;
use App\Company\DocumentExpiry;
use App\Company\VerificationStatus;
use App\Http\Controllers\Concerns\RequiresCompanyProfile;
use App\Http\Requests\StoreCompanyDocumentRequest;
use App\Http\Requests\StoreDocumentVersionRequest;
use App\Http\Requests\UpdateCompanyDocumentRequest;
use App\Models\CompanyDocument;
use App\Models\DocumentCategory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class CompanyDocumentController extends Controller
{
    use RequiresCompanyProfile;

    public function index(Request $request, DocumentExpiry $expiry): View
    {
        $this->requireProfile();
        $search = $this->searchTerm($request);
        $categoryId = $request->integer('category');
        $status = (string) $request->query('status', '');
        $window = (string) $request->query('window', '');

        if (! array_key_exists($status, VerificationStatus::options())) {
            $status = '';
        }

        if (! in_array($window, DocumentExpiry::WINDOWS, true)) {
            $window = '';
        }

        $documents = CompanyDocument::query()->with(['category', 'currentVersion']);

        if ($search !== '') {
            $documents->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('document_number', 'like', '%'.$search.'%');
            });
        }

        if ($categoryId > 0) {
            $documents->where('document_category_id', $categoryId);
        }

        if ($status !== '') {
            $documents->where('verification_status', $status);
        }

        $expiry->apply($documents, $window === '' ? null : $window);

        return view('company.documents.index', [
            'documents' => $documents->orderByDesc('id')->paginate(15)->withQueryString(),
            'categories' => DocumentCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'search' => $search,
            'categoryId' => $categoryId,
            'status' => $status,
            'window' => $window,
            'statuses' => VerificationStatus::options(),
        ]);
    }

    public function expiring(Request $request, DocumentExpiry $expiry): View
    {
        $this->requireProfile();
        $window = (string) $request->query('window', '30');

        if (! in_array($window, DocumentExpiry::WINDOWS, true)) {
            $window = '30';
        }

        $documents = $expiry->apply(CompanyDocument::query()->with('category'), $window)
            ->orderBy('expires_on')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('company.documents.expiring', [
            'documents' => $documents,
            'window' => $window,
            'counts' => $expiry->counts(),
        ]);
    }

    public function create(): View
    {
        $this->requireProfile();

        return view('company.documents.form', [
            'document' => null,
            'categories' => DocumentCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'statuses' => VerificationStatus::options(),
        ]);
    }

    public function store(StoreCompanyDocumentRequest $request, CompanyDocumentRecorder $recorder, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $profile = $this->requireProfile();
        $document = $recorder->create(
            $profile,
            $request->safe()->except(['files', 'note']),
            $this->uploads($request),
            $actor,
            $request->validated('note'),
        );

        $document->load('versions');
        $audit->record('company_document_created', $document, newValues: $this->auditValues($document), actor: $actor);

        return redirect()->route('company.documents.show', $document)->with('status', 'Document saved.');
    }

    public function show(CompanyDocument $document): View
    {
        $profile = $this->requireProfile();
        abort_unless($document->company_profile_id === $profile->id, 404);
        $document->load(['category', 'versions.uploader', 'creator', 'editor']);

        return view('company.documents.show', [
            'document' => $document,
        ]);
    }

    public function edit(CompanyDocument $document): View
    {
        $profile = $this->requireProfile();
        abort_unless($document->company_profile_id === $profile->id, 404);

        return view('company.documents.form', [
            'document' => $document,
            'categories' => DocumentCategory::query()
                ->where('is_active', true)
                ->orWhere('id', $document->document_category_id)
                ->orderBy('sort_order')
                ->get(),
            'statuses' => VerificationStatus::options(),
        ]);
    }

    public function update(UpdateCompanyDocumentRequest $request, CompanyDocument $document, CompanyDocumentRecorder $recorder, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $profile = $this->requireProfile();
        abort_unless($document->company_profile_id === $profile->id, 404);

        $before = $this->auditValues($document);
        $updated = $recorder->update($document, $request->validated(), $actor);
        $audit->record('company_document_updated', $updated, oldValues: $before, newValues: $this->auditValues($updated), actor: $actor);

        return redirect()->route('company.documents.show', $updated)->with('status', 'Document updated. Existing files were not replaced.');
    }

    public function storeVersion(StoreDocumentVersionRequest $request, CompanyDocument $document, CompanyDocumentRecorder $recorder, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $profile = $this->requireProfile();
        abort_unless($document->company_profile_id === $profile->id, 404);

        $recorder->addVersions($document, $this->uploads($request), $actor, $request->validated('note'));
        $document->load('versions');
        $audit->record('company_document_version_added', $document, newValues: [
            'title' => $document->title,
            'versions' => $document->versions->pluck('original_filename', 'version_number')->all(),
        ], actor: $actor);

        return redirect()->route('company.documents.show', $document)->with('status', 'A new file version was stored. Earlier versions were kept.');
    }

    public function destroy(Request $request, CompanyDocument $document, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $profile = $this->requireProfile();
        abort_unless($document->company_profile_id === $profile->id, 404);

        $request->validate([
            'confirm_delete' => ['accepted'],
        ]);

        $title = $document->title;
        $document->delete();
        $audit->record('company_document_deleted', $document, newValues: [
            'title' => $title,
        ], actor: $actor);

        return redirect()->route('company.documents.index')->with('status', 'Document retired. The file versions remain in private storage.');
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(CompanyDocument $document): array
    {
        $document->loadMissing('category');

        return [
            'title' => $document->title,
            'category' => $document->category?->slug,
            'document_number' => $document->document_number,
            'issuing_authority' => $document->issuing_authority,
            'issued_on' => $this->dateString($document->getAttribute('issued_on')),
            'expires_on' => $this->dateString($document->getAttribute('expires_on')),
            'verification_status' => $document->verification_status,
            'remarks' => $document->remarks,
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploads(Request $request): array
    {
        $files = $request->file('files', []);

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return array_values($files);
    }

    private function dateString(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return substr($value, 0, 10);
    }

    private function searchTerm(Request $request): string
    {
        return trim(str_replace(['%', '_'], '', (string) $request->query('search', '')));
    }

    private function actor(mixed $user): User
    {
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
