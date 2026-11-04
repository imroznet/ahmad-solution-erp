<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArchivePartyRequest;
use App\Http\Requests\SavePartyRequest;
use App\Models\Party;
use App\Models\User;
use App\Parties\PartyAuthorizer;
use App\Parties\PartyKind;
use App\Parties\PartyListing;
use App\Parties\PartyWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartyController extends Controller
{
    public function __construct(private readonly PartyAuthorizer $authorizer) {}

    public function index(Request $request, PartyListing $listing): View
    {
        return $this->renderIndex($request, $listing, null, 'All parties', 'parties.index');
    }

    public function customers(Request $request, PartyListing $listing): View
    {
        return $this->renderIndex($request, $listing, 'customer', 'Customers', 'customers.index');
    }

    public function suppliers(Request $request, PartyListing $listing): View
    {
        return $this->renderIndex($request, $listing, 'supplier', 'Suppliers', 'suppliers.index');
    }

    public function create(): View
    {
        return $this->form(null, 'party');
    }

    public function createCustomer(): View
    {
        return $this->form(null, 'customer');
    }

    public function createSupplier(): View
    {
        return $this->form(null, 'supplier');
    }

    public function store(SavePartyRequest $request, PartyWriter $writer): RedirectResponse
    {
        $actor = $this->actor();
        $party = $writer->create($request->validated(), $actor);

        return redirect()->route('parties.show', $party)->with('status', 'Party saved.');
    }

    public function show(Party $party): View
    {
        $party = $this->visibleParty($party);
        $user = $this->actor();
        $party->load([
            'roles',
            'contacts' => fn ($query) => $query->orderByDesc('is_active')->orderByDesc('is_primary')->orderBy('name'),
            'addresses' => fn ($query) => $query->orderByDesc('is_active')->orderBy('address_type')->orderBy('id'),
        ]);

        return view('parties.show', [
            'party' => $party,
            'visibleRoles' => $this->authorizer->visibleRoles($user, $party),
            'canUpdate' => $this->authorizer->canUpdate($user, $party),
            'canArchive' => $this->authorizer->canArchive($user, $party),
            'canManageContacts' => $this->authorizer->canManageContacts($user, $party),
            'canManageAddresses' => $this->authorizer->canManageAddresses($user, $party),
        ]);
    }

    public function edit(Party $party): View
    {
        return $this->form($this->visibleParty($party), 'party');
    }

    public function update(SavePartyRequest $request, Party $party, PartyWriter $writer): RedirectResponse
    {
        $writer->update($party, $request->validated(), $this->actor());

        return redirect()->route('parties.show', $party)->with('status', 'Party updated.');
    }

    public function archive(ArchivePartyRequest $request, Party $party, PartyWriter $writer): RedirectResponse
    {
        if ($party->isArchived()) {
            $writer->reactivate($party, $this->actor());
            $message = 'Party reactivated.';
        } else {
            $writer->archive($party, $this->actor());
            $message = 'Party archived.';
        }

        return redirect()->route('parties.show', $party)->with('status', $message);
    }

    private function renderIndex(Request $request, PartyListing $listing, ?string $role, string $title, string $route): View
    {
        $status = (string) $request->query('status', 'active');
        $sort = (string) $request->query('sort', 'legal_name');

        return view('parties.index', [
            'parties' => $listing->paginate($request, $role),
            'districts' => $listing->districts(),
            'title' => $title,
            'fixedRole' => $role,
            'listRoute' => $route,
            'filters' => [
                'search' => trim((string) $request->query('search', '')),
                'status' => in_array($status, ['active', 'inactive', 'archived', 'all'], true) ? $status : 'active',
                'role' => (string) $request->query('role', ''),
                'district' => trim((string) $request->query('district', '')),
                'sort' => in_array($sort, ['legal_name', 'status', 'created_at', 'district'], true) ? $sort : 'legal_name',
                'direction' => $request->query('direction') === 'desc' ? 'desc' : 'asc',
            ],
        ]);
    }

    private function form(?Party $party, string $mode): View
    {
        $user = $this->actor();

        if ($party instanceof Party) {
            abort_unless($this->authorizer->canUpdate($user, $party), 403);
            $party->loadMissing('roles');
        }

        return view('parties.form', [
            'party' => $party,
            'mode' => $mode,
            'kinds' => PartyKind::options(),
            'canAssignCustomer' => $user->hasPermission('parties.update') || $user->hasPermission('parties.create') || $user->hasPermission('customers.manage'),
            'canAssignSupplier' => $user->hasPermission('parties.update') || $user->hasPermission('parties.create') || $user->hasPermission('suppliers.manage'),
        ]);
    }

    private function visibleParty(Party $party): Party
    {
        abort_unless($this->authorizer->canView($this->actor(), $party), 404);

        return $party;
    }

    private function actor(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
