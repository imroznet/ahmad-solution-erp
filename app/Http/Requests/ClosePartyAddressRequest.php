<?php

namespace App\Http\Requests;

use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\User;
use App\Parties\PartyAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class ClosePartyAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $party = $this->route('party');
        $address = $this->route('address');

        if (! $user instanceof User || ! $party instanceof Party || ! $address instanceof PartyAddress) {
            return false;
        }

        if ($address->party_id !== $party->id) {
            abort(404);
        }

        $authorizer = app(PartyAuthorizer::class);

        if (! $authorizer->canView($user, $party)) {
            abort(404);
        }

        return $authorizer->canManageAddresses($user, $party);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirm_close' => ['accepted'],
        ];
    }
}
