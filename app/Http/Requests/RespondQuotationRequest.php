<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\Quotation;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondQuotationRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        if ($this->routeIs('quotations.public.respond')) {
            return true;
        }

        $user = $this->user();
        $quotation = $this->route('quotation');

        if (! $user instanceof User || ! $quotation instanceof Quotation) {
            return false;
        }

        $authorizer = app(QuotationAuthorizer::class);

        if (! $authorizer->canView($user, $quotation)) {
            abort(404);
        }

        return $authorizer->canRespond($user, $quotation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'respondent_name' => ['required', 'string', 'max:160'],
            'respondent_designation' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'confirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Confirm the response before saving it.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['respondent_designation', 'remarks']);
    }
}
