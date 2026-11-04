<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\Party;
use App\Models\User;
use App\Parties\PartyAuthorizer;
use App\Parties\PartyDuplicateFinder;
use App\Parties\PartyKind;
use App\Parties\PartyRoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePartyRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $party = $this->route('party');
        $authorizer = app(PartyAuthorizer::class);

        if ($party instanceof Party) {
            if (! $authorizer->canView($user, $party)) {
                abort(404);
            }

            return $authorizer->canUpdate($user, $party);
        }

        $roles = $this->input('roles', []);

        return is_array($roles) && $authorizer->canCreate($user, array_values(array_filter($roles, 'is_string')));
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'trading_name',
            'legal_name_bn',
            'trading_name_bn',
            'registration_number',
            'tin',
            'bin',
            'vat_registration_number',
            'email',
            'phone',
            'website',
            'notes',
        ]);

        $email = $this->input('email');

        $this->merge([
            'legal_name' => trim((string) $this->input('legal_name')),
            'email' => is_string($email) ? mb_strtolower($email) : $email,
            'confirm_duplicate' => $this->boolean('confirm_duplicate'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'legal_name' => ['required', 'string', 'max:200'],
            'trading_name' => ['nullable', 'string', 'max:200'],
            'legal_name_bn' => ['nullable', 'string', 'max:200'],
            'trading_name_bn' => ['nullable', 'string', 'max:200'],
            'party_kind' => ['required', Rule::in(array_keys(PartyKind::options()))],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'tin' => ['nullable', 'string', 'max:30'],
            'bin' => ['nullable', 'string', 'max:30'],
            'vat_registration_number' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url:http,https', 'max:200'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'roles' => ['required', 'array', 'min:1', 'max:2'],
            'roles.*' => ['distinct', Rule::in(array_keys(PartyRoleName::options()))],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ];

        if ($this->route('party') instanceof Party) {
            $rules['status'] = ['required', Rule::in(['active', 'inactive'])];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->boolean('confirm_duplicate')) {
                return;
            }

            $party = $this->route('party');
            $trading = $this->input('trading_name');
            $tin = $this->input('tin');
            $bin = $this->input('bin');
            $email = $this->input('email');
            $phone = $this->input('phone');
            $matches = app(PartyDuplicateFinder::class)->find(
                (string) $this->input('legal_name'),
                is_string($trading) ? $trading : null,
                is_string($tin) ? $tin : null,
                is_string($bin) ? $bin : null,
                is_string($email) ? $email : null,
                is_string($phone) ? $phone : null,
                $party instanceof Party ? $party->id : null,
            );

            if ($matches === []) {
                return;
            }

            $parts = [];

            foreach ($matches as $match) {
                $parts[] = $match['legal_name'].' ('.implode(', ', $match['reasons']).')';
            }

            $validator->errors()->add(
                'confirm_duplicate',
                'Possible duplicate: '.implode('; ', $parts).'. Tick the confirmation to save a separate record. Records are not merged.',
            );
        });
    }
}
