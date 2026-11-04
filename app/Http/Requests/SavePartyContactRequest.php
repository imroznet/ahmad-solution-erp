<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\Party;
use App\Models\PartyContact;
use App\Models\User;
use App\Parties\PartyAuthorizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SavePartyContactRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();
        $party = $this->route('party');
        $contact = $this->route('contact');

        if (! $user instanceof User || ! $party instanceof Party) {
            return false;
        }

        if ($contact instanceof PartyContact && $contact->party_id !== $party->id) {
            abort(404);
        }

        $authorizer = app(PartyAuthorizer::class);

        if (! $authorizer->canView($user, $party)) {
            abort(404);
        }

        return $authorizer->canManageContacts($user, $party);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'designation',
            'department',
            'email',
            'phone',
            'alternative_phone',
            'whatsapp_number',
            'notes',
        ]);

        $email = $this->input('email');

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => is_string($email) ? mb_strtolower($email) : $email,
            'is_primary' => $this->boolean('is_primary'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'designation' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'alternative_phone' => ['nullable', 'string', 'max:40'],
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('is_primary') && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_primary', 'A primary contact must be active.');
            }
        });
    }
}
