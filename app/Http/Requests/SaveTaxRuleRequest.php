<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SaveTaxRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && ($user->hasPermission('tax_rules.create') || $user->hasPermission('tax_rules.update'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:40'],
            'tax_type' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:160'],
            'supply_classification' => ['nullable', 'string', 'max:160'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date'],
            'rate_percent' => ['nullable', 'string', 'max:20'],
            'fixed_amount' => ['nullable', 'string', 'max:24'],
            'calculation_basis' => ['nullable', 'string', 'max:40'],
            'price_treatment' => ['nullable', 'string', 'max:20'],
            'threshold_amount' => ['nullable', 'string', 'max:24'],
            'exemption_note' => ['nullable', 'string', 'max:2000'],
            'legal_instrument' => ['required', 'string', 'max:200'],
            'section_reference' => ['nullable', 'string', 'max:200'],
            'source_url' => ['nullable', 'string', 'max:500'],
            'publication_date' => ['nullable', 'date'],
            'legally_effective_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
