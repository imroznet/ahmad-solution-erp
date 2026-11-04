<?php

namespace App\Http\Requests;

use App\Company\AddressType;
use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyAddressRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission('company.address.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'line_2',
            'area',
            'upazila',
            'division',
            'postcode',
            'effective_from',
            'effective_until',
            'remarks',
            'supersedes_address_id',
        ]);

        $this->merge([
            'line_1' => trim((string) $this->input('line_1')),
            'district' => trim((string) $this->input('district')),
            'country' => trim((string) $this->input('country', 'Bangladesh')),
            'is_active' => $this->boolean('is_active'),
            'is_default_correspondence' => $this->boolean('is_default_correspondence'),
            'is_default_billing' => $this->boolean('is_default_billing'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'address_type' => ['required', Rule::in(array_keys(AddressType::options()))],
            'line_1' => ['required', 'string', 'max:200'],
            'line_2' => ['nullable', 'string', 'max:200'],
            'area' => ['nullable', 'string', 'max:120'],
            'upazila' => ['nullable', 'string', 'max:120'],
            'district' => ['required', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:80'],
            'is_active' => ['boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_default_correspondence' => ['boolean'],
            'is_default_billing' => ['boolean'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'supersedes_address_id' => ['nullable', 'integer', Rule::exists('company_addresses', 'id')],
        ];
    }
}
