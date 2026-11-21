<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SaveOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission('opening_balances.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'journal_date' => ['required', 'date'],
            'memo' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['nullable', 'string', 'max:20', 'regex:/^(\d{1,16}(\.\d{1,2})?)?$/'],
            'lines.*.credit' => ['nullable', 'string', 'max:20', 'regex:/^(\d{1,16}(\.\d{1,2})?)?$/'],
            'lines.*.party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'lines.*.memo' => ['nullable', 'string', 'max:200'],
        ];
    }
}
