<?php

namespace App\Http\Requests;

use App\Accounting\BankAccountType;
use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankAccountRequest extends FormRequest
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
        $bank = $this->route('bankAccount');
        $ignore = $bank instanceof BankAccount ? $bank->id : null;

        return [
            'bank_name' => ['required', 'string', 'max:160'],
            'branch' => ['required', 'string', 'max:160'],
            'account_title' => ['required', 'string', 'max:160'],
            'account_number' => [
                'required',
                'string',
                'max:40',
                Rule::unique('bank_accounts', 'account_number')->where(fn ($query) => $query->where('bank_name', $this->input('bank_name')))->ignore($ignore),
            ],
            'account_type' => ['required', Rule::enum(BankAccountType::class)],
            'opened_on' => ['required', 'date'],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
