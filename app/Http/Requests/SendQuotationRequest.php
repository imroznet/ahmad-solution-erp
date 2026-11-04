<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\Quotation;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class SendQuotationRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();
        $quotation = $this->route('quotation');

        if (! $user instanceof User || ! $quotation instanceof Quotation) {
            return false;
        }

        $authorizer = app(QuotationAuthorizer::class);

        if (! $authorizer->canView($user, $quotation)) {
            abort(404);
        }

        return $authorizer->canSend($user, $quotation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_label' => ['nullable', 'string', 'max:200'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['recipient_label', 'remarks']);
    }
}
