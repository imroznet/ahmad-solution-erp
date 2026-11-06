<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Tax\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaxRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $version = $this->route('version');

        if ($version !== null) {
            return $user->hasPermission('tax_rules.update') || $user->hasPermission('tax_rules.create');
        }

        return $user->hasPermission('tax_rules.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tax_type' => ['required', Rule::in(TaxType::all())],
            'rule_code' => ['required', 'string', 'max:40'],
            'supply_classification' => ['nullable', 'string', 'max:80'],
            'rate_kind' => ['required', 'in:none,percentage,fixed'],
            'rate_value' => ['nullable', 'string', 'max:12'],
            'fixed_amount' => ['nullable', 'string', 'max:24'],
            'calculation_basis' => ['nullable', 'string', 'max:80'],
            'threshold_note' => ['nullable', 'string', 'max:2000'],
            'exemption_note' => ['nullable', 'string', 'max:2000'],
            'legal_instrument' => ['required', 'string', 'max:200'],
            'section_reference' => ['required', 'string', 'max:200'],
            'source_url' => ['required', 'string', 'max:500'],
            'publication_date' => ['nullable', 'date'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
