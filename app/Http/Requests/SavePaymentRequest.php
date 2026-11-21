<?php

namespace App\Http\Requests;

use App\Accounting\CashFlowCategory;
use App\Accounting\PaymentWriter;
use Illuminate\Foundation\Http\FormRequest;

class SavePaymentRequest extends FormRequest
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
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'string', 'max:20'],
            'mode' => ['required', 'string', 'in:'.implode(',', PaymentWriter::MODES)],
            'reference' => ['nullable', 'string', 'max:80'],
            'cash_flow_category' => ['nullable', 'string', 'in:'.implode(',', array_column(CashFlowCategory::cases(), 'value'))],
            'allocations' => ['nullable', 'array'],
            'allocations.*.journal_id' => ['nullable', 'integer'],
            'allocations.*.amount' => ['nullable', 'string', 'max:20'],
        ];
    }
}
