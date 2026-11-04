<?php

namespace App\Http\Controllers;

use App\Company\PrivateFileStore;
use App\Company\SuggestedFinancialYear;
use App\Http\Requests\UpdateCompanyProfileRequest;
use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyProfileController extends Controller
{
    /**
     * @var list<string>
     */
    private const AUDIT_KEYS = [
        'legal_name',
        'trading_name',
        'rjsc_registration_number',
        'incorporation_date',
        'business_type',
        'tin',
        'bin',
        'trade_licence_number',
        'trade_licence_issuing_authority',
        'trade_licence_issued_on',
        'trade_licence_expires_on',
        'financial_year_starts_on',
        'financial_year_ends_on',
        'currency_code',
        'website',
        'email',
        'phone',
        'description',
    ];

    public function show(): View
    {
        $profile = CompanyProfile::primary();
        $profile?->load(['addresses' => fn ($query) => $query->orderByDesc('is_active')->orderBy('address_type')->orderBy('id')]);

        return view('company.profile', [
            'profile' => $profile,
        ]);
    }

    public function edit(): View
    {
        return view('company.edit', [
            'profile' => CompanyProfile::primary(),
            'suggestedYear' => SuggestedFinancialYear::julyToJune(),
        ]);
    }

    public function update(UpdateCompanyProfileRequest $request, AuditLogger $audit, PrivateFileStore $files): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $attributes = $request->safe()->except(['logo', 'remove_logo']);
        $before = [];

        $profile = DB::transaction(function () use ($attributes, $actor, &$before): CompanyProfile {
            $profile = CompanyProfile::query()->lockForUpdate()->orderBy('id')->first();

            if (! $profile instanceof CompanyProfile) {
                $profile = new CompanyProfile(['singleton_key' => 'primary']);
            } else {
                $before = Arr::only($profile->toArray(), self::AUDIT_KEYS);
            }

            $profile->fill($attributes);
            $profile->updated_by = $actor->id;
            $profile->save();

            return $profile;
        });

        $logoChange = 'unchanged';

        if ($request->boolean('remove_logo') && is_string($profile->logo_path) && $profile->logo_path !== '') {
            $files->delete($profile->logo_path);
            $profile->logo_path = null;
            $logoChange = 'removed';
        }

        $upload = $request->file('logo');

        if ($upload instanceof UploadedFile) {
            $stored = $files->store($upload, 'company-logo', logo: true);
            $previous = $profile->logo_path;
            $profile->logo_path = $stored->path;
            $logoChange = 'replaced';

            if (is_string($previous) && $previous !== '' && $previous !== $stored->path) {
                $files->delete($previous);
            }
        }

        if ($profile->isDirty('logo_path')) {
            $profile->save();
        }

        $audit->record('company_profile_updated', $profile, oldValues: $before, newValues: [
            ...Arr::only($profile->toArray(), self::AUDIT_KEYS),
            'logo' => $logoChange,
        ], actor: $actor);

        return redirect()->route('company.show')->with('status', 'Company profile saved.');
    }

    public function logo(): StreamedResponse
    {
        $user = $this->actor(request()->user());
        abort_unless($user->hasPermission('company.view'), 403);

        $profile = CompanyProfile::primary();
        $path = $profile?->logo_path;
        abort_unless(is_string($path) && str_starts_with($path, 'company-logo/') && ! str_contains($path, '..'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };

        return Storage::disk('local')->response($path, 'company-logo', [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="company-logo"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function actor(mixed $user): User
    {
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
