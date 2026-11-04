<?php

namespace App\Http\Requests;

use App\Models\Quotation;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class CancelQuotationRequest extends FormRequest
{
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

        return $authorizer->canCancel($user, $quotation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancel_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
