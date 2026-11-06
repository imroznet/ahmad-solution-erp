<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\TaxRule;
use App\Models\User;
use App\Tax\TaxRuleStatus;
use Illuminate\Foundation\Http\FormRequest;

class SaveTaxRuleRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $rule = $this->route('taxRule');

        if ($rule instanceof TaxRule) {
            if ($rule->stringAttribute('status') !== TaxRuleStatus::DRAFT) {
                return false;
            }

            return $user->hasPermission('tax_rules.manage');
        }

        return $user->hasPermission('tax_rules.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tax_type' => ['required', 'in:output_vat,vds,ait'],
            'rule_key' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:200'],
            'supply_classification' => ['nullable', 'string', 'max:120'],
            'rate' => ['nullable', 'string', 'max:20'],
            'rate_kind' => ['nullable', 'in:percentage,fixed_amount,none'],
            'calculation_basis' => ['nullable', 'string', 'max:80'],
            'rounding_rule' => ['nullable', 'string', 'max:80'],
            'price_treatment' => ['nullable', 'in:exclusive,inclusive,unspecified'],
            'threshold_amount' => ['nullable', 'string', 'max:24'],
            'exemption_note' => ['nullable', 'string', 'max:2000'],
            'required_supporting_data' => ['nullable', 'string', 'max:2000'],
            'legal_instrument' => ['required', 'string', 'max:200'],
            'section_reference' => ['nullable', 'string', 'max:200'],
            'source_url' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'publication_date' => ['nullable', 'date_format:Y-m-d'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'supply_classification',
            'rate',
            'rate_kind',
            'calculation_basis',
            'rounding_rule',
            'price_treatment',
            'threshold_amount',
            'exemption_note',
            'required_supporting_data',
            'section_reference',
            'source_url',
            'notes',
            'publication_date',
            'effective_to',
        ]);
    }
}
