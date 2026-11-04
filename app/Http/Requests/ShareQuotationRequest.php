<?php

namespace App\Http\Requests;

use App\Models\Quotation;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class ShareQuotationRequest extends FormRequest
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

        return $authorizer->canShare($user, $quotation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = (int) config('asl.quotations.share_max_days', 90);

        return [
            'days' => ['nullable', 'integer', 'min:1', 'max:'.$max],
        ];
    }
}
