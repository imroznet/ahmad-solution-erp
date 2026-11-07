<?php

namespace App\Http\Requests;

use App\Tax\CalculationBasis;
use App\Tax\RateBasis;
use App\Tax\RoundingRule;
use App\Tax\TaxType;
use App\Tax\ThresholdTreatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaxRuleRequest extends FormRequest
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
            'tax_type' => ['required', Rule::in(TaxType::all())],
            'supply_classification' => ['required', 'string', 'max:160'],
            'rule_code' => ['nullable', 'string', 'max:80'],
            'rate_basis' => ['required', Rule::in(RateBasis::all())],
            'rate_percent' => ['nullable', 'string', 'max:20'],
            'fixed_amount' => ['nullable', 'string', 'max:24'],
            'calculation_basis' => ['required', Rule::in(CalculationBasis::all())],
            'rounding_rule' => ['required', Rule::in(RoundingRule::all())],
            'threshold_amount' => ['nullable', 'string', 'max:24'],
            'threshold_treatment' => ['required', Rule::in(ThresholdTreatment::all())],
            'required_supporting_data' => ['nullable', 'string', 'max:2000'],
            'exemption_note' => ['nullable', 'string', 'max:2000'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d'],
            'legal_instrument' => ['required', 'string', 'max:200'],
            'section_reference' => ['required', 'string', 'max:200'],
            'source_url' => ['required', 'string', 'max:500'],
            'publication_date' => ['nullable', 'date_format:Y-m-d'],
            'legal_effective_date' => ['nullable', 'date_format:Y-m-d'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
