<?php

namespace App\Http\Requests;

use App\Models\Party;
use App\Models\PartyContact;
use App\Models\User;
use App\Parties\PartyAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class ArchivePartyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $party = $this->route('party');
        $contact = $this->route('contact');

        if (! $user instanceof User || ! $party instanceof Party || ! $contact instanceof PartyContact) {
            return false;
        }

        if ($contact->party_id !== $party->id) {
            abort(404);
        }

        $authorizer = app(PartyAuthorizer::class);

        if (! $authorizer->canView($user, $party)) {
            abort(404);
        }

        return $authorizer->canManageContacts($user, $party);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirm_archive' => ['accepted'],
        ];
    }
}
