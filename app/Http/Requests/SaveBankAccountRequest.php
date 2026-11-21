<?php

namespace App\Http\Requests;

use App\Accounting\BankAccountType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission('chart_of_accounts.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^1\d{3}$/', 'unique:chart_of_accounts,code'],
            'bank_name' => ['required', 'string', 'max:160'],
            'branch' => ['required', 'string', 'max:160'],
            'account_title' => ['required', 'string', 'max:160'],
            'account_number' => ['required', 'string', 'max:40'],
            'account_type' => ['required', Rule::enum(BankAccountType::class)],
            'opened_on' => ['required', 'date'],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ];
    }
}
