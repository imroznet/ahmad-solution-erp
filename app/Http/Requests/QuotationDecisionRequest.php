<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\Quotation;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class QuotationDecisionRequest extends FormRequest
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

        return $this->routeIs('quotations.return')
            ? $authorizer->canReturn($user, $quotation)
            : $authorizer->canApprove($user, $quotation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'remarks' => [$this->routeIs('quotations.return') ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['remarks']);
    }
}
