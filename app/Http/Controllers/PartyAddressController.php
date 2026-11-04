<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClosePartyAddressRequest;
use App\Http\Requests\SavePartyAddressRequest;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\User;
use App\Parties\PartyAddressType;
use App\Parties\PartyAddressWriter;
use App\Parties\PartyAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PartyAddressController extends Controller
{
    public function __construct(private readonly PartyAuthorizer $authorizer) {}

    public function create(Party $party): View
    {
        $party = $this->manageableParty($party);
        $supersedes = $this->superseded($party);

        return view('parties.addresses.form', [
            'party' => $party,
            'address' => null,
            'supersedes' => $supersedes,
            'types' => PartyAddressType::options(),
        ]);
    }

    public function store(SavePartyAddressRequest $request, Party $party, PartyAddressWriter $writer): RedirectResponse
    {
        $actor = $this->actor();
        $supersedes = $request->validated('supersedes_address_id');

        if (is_numeric($supersedes)) {
            $previous = PartyAddress::query()
                ->where('party_id', $party->id)
                ->find($supersedes);
            abort_unless($previous instanceof PartyAddress, 404);
            $writer->supersede($previous, $request->validated(), $actor);
        } else {
            $writer->create($party, $request->validated(), $actor);
        }

        return redirect()->route('parties.show', $party)->with('status', 'Address saved.');
    }

    public function edit(Party $party, PartyAddress $address): View
    {
        $this->manageableParty($party);
        abort_unless($address->party_id === $party->id, 404);

        return view('parties.addresses.form', [
            'party' => $party,
            'address' => $address,
            'supersedes' => null,
            'types' => PartyAddressType::options(),
        ]);
    }

    public function update(SavePartyAddressRequest $request, Party $party, PartyAddress $address, PartyAddressWriter $writer): RedirectResponse
    {
        abort_unless($address->party_id === $party->id, 404);
        $writer->update($address, $request->validated(), $this->actor());

        return redirect()->route('parties.show', $party)->with('status', 'Address updated.');
    }

    public function close(ClosePartyAddressRequest $request, Party $party, PartyAddress $address, PartyAddressWriter $writer): RedirectResponse
    {
        $writer->close($address, $this->actor());

        return redirect()->route('parties.show', $party)->with('status', 'Address closed.');
    }

    private function superseded(Party $party): ?PartyAddress
    {
        $id = request()->query('supersedes');

        if (! is_numeric($id)) {
            return null;
        }

        $address = PartyAddress::query()->where('party_id', $party->id)->find($id);

        return $address instanceof PartyAddress && $address->is_active ? $address : null;
    }

    private function manageableParty(Party $party): Party
    {
        $user = $this->actor();
        abort_unless($this->authorizer->canView($user, $party), 404);
        abort_unless($this->authorizer->canManageAddresses($user, $party), 403);

        return $party;
    }

    private function actor(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
