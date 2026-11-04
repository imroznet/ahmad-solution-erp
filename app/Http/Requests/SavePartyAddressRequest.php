<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\User;
use App\Parties\PartyAddressType;
use App\Parties\PartyAuthorizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePartyAddressRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();
        $party = $this->route('party');
        $address = $this->route('address');

        if (! $user instanceof User || ! $party instanceof Party) {
            return false;
        }

        if ($address instanceof PartyAddress && $address->party_id !== $party->id) {
            abort(404);
        }

        $authorizer = app(PartyAuthorizer::class);

        if (! $authorizer->canView($user, $party)) {
            abort(404);
        }

        return $authorizer->canManageAddresses($user, $party);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'address_line_2',
            'area',
            'upazila',
            'division',
            'postcode',
            'effective_from',
            'effective_until',
            'supersedes_address_id',
        ]);

        $this->merge([
            'address_line_1' => trim((string) $this->input('address_line_1')),
            'district' => trim((string) $this->input('district')),
            'country' => trim((string) $this->input('country', 'Bangladesh')),
            'is_active' => $this->boolean('is_active'),
            'is_default_billing' => $this->boolean('is_default_billing'),
            'is_default_shipping' => $this->boolean('is_default_shipping'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $party = $this->route('party');
        $partyId = $party instanceof Party ? $party->id : 0;

        return [
            'address_type' => ['required', Rule::in(array_keys(PartyAddressType::options()))],
            'address_line_1' => ['required', 'string', 'max:200'],
            'address_line_2' => ['nullable', 'string', 'max:200'],
            'area' => ['nullable', 'string', 'max:120'],
            'upazila' => ['nullable', 'string', 'max:120'],
            'district' => ['required', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:80'],
            'is_default_billing' => ['boolean'],
            'is_default_shipping' => ['boolean'],
            'is_active' => ['boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'supersedes_address_id' => [
                'nullable',
                'integer',
                Rule::exists('party_addresses', 'id')->where('party_id', $partyId),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_active') && ($this->boolean('is_default_billing') || $this->boolean('is_default_shipping'))) {
                $validator->errors()->add('is_active', 'A default billing or shipping address must be active.');
            }
        });
    }
}
