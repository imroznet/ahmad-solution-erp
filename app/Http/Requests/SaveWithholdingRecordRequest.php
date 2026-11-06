<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SaveWithholdingRecordRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $permission = $this->routeIs('ait.*') ? 'ait.manage' : 'vds.manage';

        return $user->hasPermission($permission);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', 'in:withheld_from_us,deducted_by_us'],
            'counterparty_source' => ['required', 'in:party,manual'],
            'party_id' => ['nullable', 'integer'],
            'counterparty_name' => ['nullable', 'string', 'max:200'],
            'counterparty_bin' => ['nullable', 'string', 'max:30'],
            'counterparty_tin' => ['nullable', 'string', 'max:30'],
            'mushak_document_id' => ['nullable', 'integer'],
            'commercial_invoice_id' => ['nullable', 'integer'],
            'underlying_reference' => ['required', 'string', 'max:200'],
            'basis_amount' => ['nullable', 'string', 'max:24'],
            'deducted_amount' => ['required', 'string', 'max:24'],
            'record_date' => ['required', 'date_format:Y-m-d'],
            'certificate_number' => ['nullable', 'string', 'max:80'],
            'treasury_reference' => ['nullable', 'string', 'max:160'],
            'tax_rule_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'confirmed' => ['nullable', 'in:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'party_id',
            'counterparty_name',
            'counterparty_bin',
            'counterparty_tin',
            'mushak_document_id',
            'commercial_invoice_id',
            'basis_amount',
            'certificate_number',
            'treasury_reference',
            'tax_rule_id',
            'notes',
            'internal_notes',
        ]);
    }
}
