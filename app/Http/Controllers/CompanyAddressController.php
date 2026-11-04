<?php

namespace App\Http\Controllers;

use App\Company\AddressType;
use App\Company\CompanyAddressWriter;
use App\Http\Controllers\Concerns\RequiresCompanyProfile;
use App\Http\Requests\SaveCompanyAddressRequest;
use App\Models\CompanyAddress;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyAddressController extends Controller
{
    use RequiresCompanyProfile;

    /**
     * @var list<string>
     */
    private const AUDIT_KEYS = [
        'address_type',
        'line_1',
        'line_2',
        'area',
        'upazila',
        'district',
        'division',
        'postcode',
        'country',
        'is_active',
        'effective_from',
        'effective_until',
        'is_default_correspondence',
        'is_default_billing',
        'remarks',
        'supersedes_address_id',
    ];

    public function index(): View
    {
        $profile = $this->requireProfile();

        return view('company.addresses.index', [
            'profile' => $profile,
            'addresses' => $profile->addresses()->orderByDesc('is_active')->orderBy('address_type')->orderBy('id')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $profile = $this->requireProfile();
        $supersedes = null;

        if ($request->filled('supersedes')) {
            $supersedes = $profile->addresses()->whereKey($request->integer('supersedes'))->firstOrFail();
        }

        return view('company.addresses.form', [
            'profile' => $profile,
            'address' => null,
            'supersedes' => $supersedes,
            'types' => AddressType::options(),
        ]);
    }

    public function store(SaveCompanyAddressRequest $request, CompanyAddressWriter $writer, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $profile = $this->requireProfile();
        $supersedesId = $request->validated('supersedes_address_id');

        if ($supersedesId !== null) {
            $previous = $profile->addresses()->whereKey($supersedesId)->first();

            if (! $previous instanceof CompanyAddress) {
                throw ValidationException::withMessages([
                    'supersedes_address_id' => 'The address being replaced was not found.',
                ]);
            }

            $before = Arr::only($previous->toArray(), self::AUDIT_KEYS);
            $created = $writer->supersede($previous, $request->validated(), $actor);
            $audit->record('company_address_closed', $previous, oldValues: $before, newValues: Arr::only($previous->fresh()->toArray(), self::AUDIT_KEYS), actor: $actor);
            $audit->record('company_address_created', $created, newValues: Arr::only($created->toArray(), self::AUDIT_KEYS), actor: $actor);

            return redirect()->route('company.addresses.index')->with('status', 'The previous address was closed and the new address was saved.');
        }

        $created = $writer->create($profile, $request->validated(), $actor);
        $audit->record('company_address_created', $created, newValues: Arr::only($created->toArray(), self::AUDIT_KEYS), actor: $actor);

        return redirect()->route('company.addresses.index')->with('status', 'Address saved.');
    }

    public function edit(CompanyAddress $address): View
    {
        $profile = $this->requireProfile();
        abort_unless($address->company_profile_id === $profile->id, 404);

        return view('company.addresses.form', [
            'profile' => $profile,
            'address' => $address,
            'supersedes' => null,
            'types' => AddressType::options(),
        ]);
    }

    public function update(SaveCompanyAddressRequest $request, CompanyAddress $address, CompanyAddressWriter $writer, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $profile = $this->requireProfile();
        abort_unless($address->company_profile_id === $profile->id, 404);

        $before = Arr::only($address->toArray(), self::AUDIT_KEYS);
        $updated = $writer->update($address, $request->validated(), $actor);
        $audit->record('company_address_updated', $updated, oldValues: $before, newValues: Arr::only($updated->toArray(), self::AUDIT_KEYS), actor: $actor);

        return redirect()->route('company.addresses.index')->with('status', 'Address updated.');
    }

    public function close(Request $request, CompanyAddress $address, CompanyAddressWriter $writer, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $profile = $this->requireProfile();
        abort_unless($address->company_profile_id === $profile->id, 404);

        $request->validate([
            'confirm_close' => ['accepted'],
        ]);

        $before = Arr::only($address->toArray(), self::AUDIT_KEYS);
        $closed = $writer->close($address, $actor);
        $audit->record('company_address_closed', $closed, oldValues: $before, newValues: Arr::only($closed->toArray(), self::AUDIT_KEYS), actor: $actor);

        return redirect()->route('company.addresses.index')->with('status', 'Address closed. The record remains in the history.');
    }

    private function actor(mixed $user): User
    {
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
