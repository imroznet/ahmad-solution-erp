<?php

namespace App\Http\Requests;

use App\Models\MushakInvoice;
use App\Models\User;
use App\Tax\TaxAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class IssueMushakRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $mushak = $this->route('mushak');

        if (! $user instanceof User || ! $mushak instanceof MushakInvoice) {
            return false;
        }

        $authorizer = app(TaxAuthorizer::class);

        if (! $authorizer->canView($user, $mushak)) {
            abort(404);
        }

        return $authorizer->canIssue($user, $mushak);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_treatment_confirmed' => ['accepted'],
            'legal_treatment_note' => ['required', 'string', 'max:2000'],
            'review_reason' => ['nullable', 'string', 'max:2000'],
            'prepared_by_designation' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'legal_treatment_confirmed.accepted' => 'Confirm that you accept this manual tax treatment before issue.',
        ];
    }
}
