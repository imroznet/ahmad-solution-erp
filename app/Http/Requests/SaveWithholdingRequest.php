<?php

namespace App\Http\Requests;

use App\Tax\TaxDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWithholdingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(TaxDirection::all())],
            'party_id' => ['required', 'integer'],
            'commercial_invoice_id' => ['nullable', 'integer'],
            'underlying_reference' => ['nullable', 'string', 'max:200'],
            'supply_classification' => ['required', 'string', 'max:160'],
            'rule_code' => ['nullable', 'string', 'max:80'],
            'basis_amount' => ['required', 'string', 'max:24'],
            'record_date' => ['required', 'date_format:Y-m-d'],
            'pinned_tax_rule_version_id' => ['nullable', 'integer'],
            'certificate_reference' => ['nullable', 'string', 'max:120'],
            'treasury_reference' => ['nullable', 'string', 'max:120'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
