<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyProfileRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission('company.update');
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'trading_name',
            'rjsc_registration_number',
            'incorporation_date',
            'business_type',
            'tin',
            'bin',
            'trade_licence_number',
            'trade_licence_issuing_authority',
            'trade_licence_issued_on',
            'trade_licence_expires_on',
            'website',
            'email',
            'phone',
            'description',
        ]);

        $this->merge([
            'legal_name' => trim((string) $this->input('legal_name')),
            'currency_code' => strtoupper(trim((string) $this->input('currency_code', 'BDT'))),
            'remove_logo' => $this->boolean('remove_logo'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $logoMax = (int) config('asl.documents.logo_max_kilobytes');

        return [
            'legal_name' => ['required', 'string', 'max:200'],
            'trading_name' => ['nullable', 'string', 'max:200'],
            'logo' => ['nullable', 'file', 'max:'.$logoMax, 'mimes:jpg,jpeg,png'],
            'remove_logo' => ['boolean'],
            'rjsc_registration_number' => ['nullable', 'string', 'max:60'],
            'incorporation_date' => ['nullable', 'date', 'before_or_equal:today'],
            'business_type' => ['nullable', 'string', 'max:120'],
            'tin' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'bin' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'trade_licence_number' => ['nullable', 'string', 'max:60'],
            'trade_licence_issuing_authority' => ['nullable', 'string', 'max:160'],
            'trade_licence_issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'trade_licence_expires_on' => ['nullable', 'date'],
            'financial_year_starts_on' => ['required', 'date'],
            'financial_year_ends_on' => ['required', 'date', 'after:financial_year_starts_on'],
            'currency_code' => ['required', 'in:BDT'],
            'website' => ['nullable', 'url', 'max:200', 'starts_with:http://,https://'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tin.regex' => 'Enter the TIN as printed. This field does not check an NBR checksum.',
            'bin.regex' => 'Enter the BIN as printed. This field does not check an NBR checksum.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $start = $this->date('financial_year_starts_on');
            $end = $this->date('financial_year_ends_on');

            if ($start !== null && $end !== null && $start->diffInDays($end) > 366) {
                $validator->errors()->add('financial_year_ends_on', 'The financial year must not be longer than 366 days.');
            }

            $issued = $this->date('trade_licence_issued_on');
            $expires = $this->date('trade_licence_expires_on');

            if ($issued !== null && $expires !== null && $expires->lt($issued)) {
                $validator->errors()->add('trade_licence_expires_on', 'The trade licence expiry must be on or after the issue date.');
            }
        });
    }
}
