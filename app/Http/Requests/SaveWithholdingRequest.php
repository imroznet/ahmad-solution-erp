<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SaveWithholdingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return $this->routeIs('vds.store')
            ? $user->hasPermission('vds.create')
            : $user->hasPermission('ait.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', 'in:withheld_from_us,deducted_by_us'],
            'record_date' => ['required', 'date'],
            'party_id' => ['nullable', 'integer'],
            'counterparty_name' => ['nullable', 'string', 'max:200'],
            'counterparty_bin' => ['nullable', 'string', 'max:30'],
            'counterparty_tin' => ['nullable', 'string', 'max:30'],
            'commercial_invoice_id' => ['nullable', 'integer'],
            'mushak_63_document_id' => ['nullable', 'integer'],
            'taxable_basis' => ['required', 'string', 'max:24'],
            'tax_amount' => ['required', 'string', 'max:24'],
            'certificate_reference' => ['nullable', 'string', 'max:120'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'tax_rule_version_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
