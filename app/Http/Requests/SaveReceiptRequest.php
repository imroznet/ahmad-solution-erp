<?php

namespace App\Http\Requests;

use App\Accounting\ReceiptWriter;
use Illuminate\Foundation\Http\FormRequest;

class SaveReceiptRequest extends FormRequest
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
            'party_id' => ['required', 'integer'],
            'account_id' => ['required', 'integer'],
            'receipt_date' => ['required', 'date_format:Y-m-d'],
            'amount_received' => ['nullable', 'string', 'max:20'],
            'vds_deducted' => ['nullable', 'string', 'max:20'],
            'ait_deducted' => ['nullable', 'string', 'max:20'],
            'mode' => ['required', 'string', 'in:'.implode(',', ReceiptWriter::MODES)],
            'reference' => ['nullable', 'string', 'max:80'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.commercial_invoice_id' => ['nullable', 'integer'],
            'allocations.*.amount' => ['nullable', 'string', 'max:20'],
        ];
    }
}
