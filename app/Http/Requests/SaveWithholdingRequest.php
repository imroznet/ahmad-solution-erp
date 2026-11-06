<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Tax\WithholdingDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWithholdingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $slug = $this->routeIs('ait.*') ? 'ait.record' : 'vds.record';

        return $user->hasPermission($slug);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(WithholdingDirection::all())],
            'party_id' => ['nullable', 'integer'],
            'party_name' => ['nullable', 'string', 'max:200'],
            'party_bin' => ['nullable', 'string', 'max:40'],
            'party_tin' => ['nullable', 'string', 'max:40'],
            'commercial_invoice_id' => ['nullable', 'integer'],
            'mushak_invoice_id' => ['nullable', 'integer'],
            'tax_rule_version_id' => ['nullable', 'integer'],
            'supply_description' => ['required', 'string', 'max:500'],
            'taxable_basis' => ['nullable', 'string', 'max:24'],
            'amount' => ['required', 'string', 'max:24'],
            'record_date' => ['required', 'date'],
            'certificate_reference' => ['nullable', 'string', 'max:80'],
            'payment_reference' => ['nullable', 'string', 'max:80'],
            'source_note' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
