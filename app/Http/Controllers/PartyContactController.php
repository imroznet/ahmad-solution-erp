<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArchivePartyContactRequest;
use App\Http\Requests\SavePartyContactRequest;
use App\Models\Party;
use App\Models\PartyContact;
use App\Models\User;
use App\Parties\PartyAuthorizer;
use App\Parties\PartyContactWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PartyContactController extends Controller
{
    public function __construct(private readonly PartyAuthorizer $authorizer) {}

    public function create(Party $party): View
    {
        $party = $this->manageableParty($party);

        return view('parties.contacts.form', [
            'party' => $party,
            'contact' => null,
            'suggestPrimary' => ! $party->contacts()->where('is_primary', true)->where('is_active', true)->exists(),
        ]);
    }

    public function store(SavePartyContactRequest $request, Party $party, PartyContactWriter $writer): RedirectResponse
    {
        $writer->create($party, $request->validated(), $this->actor());

        return redirect()->route('parties.show', $party)->with('status', 'Contact saved.');
    }

    public function edit(Party $party, PartyContact $contact): View
    {
        $this->manageableParty($party);
        abort_unless($contact->party_id === $party->id, 404);

        return view('parties.contacts.form', [
            'party' => $party,
            'contact' => $contact,
            'suggestPrimary' => false,
        ]);
    }

    public function update(SavePartyContactRequest $request, Party $party, PartyContact $contact, PartyContactWriter $writer): RedirectResponse
    {
        $writer->update($contact, $request->validated(), $this->actor());

        return redirect()->route('parties.show', $party)->with('status', 'Contact updated.');
    }

    public function archive(ArchivePartyContactRequest $request, Party $party, PartyContact $contact, PartyContactWriter $writer): RedirectResponse
    {
        $writer->archive($contact, $this->actor());

        return redirect()->route('parties.show', $party)->with('status', 'Contact archived.');
    }

    private function manageableParty(Party $party): Party
    {
        $user = $this->actor();
        abort_unless($this->authorizer->canView($user, $party), 404);
        abort_unless($this->authorizer->canManageContacts($user, $party), 403);

        return $party;
    }

    private function actor(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
