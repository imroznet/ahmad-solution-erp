<?php

namespace App\Http\Requests;

use App\Models\Party;
use App\Models\User;
use App\Parties\PartyAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class ArchivePartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $party = $this->route('party');

        if (! $user instanceof User || ! $party instanceof Party) {
            return false;
        }

        $authorizer = app(PartyAuthorizer::class);

        if (! $authorizer->canView($user, $party)) {
            abort(404);
        }

        return $authorizer->canArchive($user, $party);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $party = $this->route('party');
        $field = $party instanceof Party && $party->isArchived() ? 'confirm_reactivate' : 'confirm_archive';

        return [
            $field => ['accepted'],
        ];
    }
}
