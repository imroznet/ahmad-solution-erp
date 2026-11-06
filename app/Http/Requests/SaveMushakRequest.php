<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMushakRequest extends FormRequest
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
            'commercial_invoice_id' => ['sometimes', 'integer'],
            'supply_classification' => ['required', 'string', 'max:160'],
            'rule_code' => ['nullable', 'string', 'max:80'],
            'pinned_tax_rule_version_id' => ['nullable', 'integer'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
