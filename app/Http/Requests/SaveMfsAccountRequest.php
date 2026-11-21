<?php

namespace App\Http\Requests;

use App\Accounting\MfsProvider;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMfsAccountRequest extends FormRequest
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
            'provider' => ['required', Rule::enum(MfsProvider::class)],
            'mobile_number' => ['required', 'string', 'regex:/^01\d{9}$/', 'unique:mfs_accounts,mobile_number'],
            'account_title' => ['required', 'string', 'max:160'],
            'opened_on' => ['required', 'date'],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ];
    }
}
