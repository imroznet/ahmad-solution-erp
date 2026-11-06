<?php

namespace App\Http\Requests;

use App\Models\MushakInvoice;
use App\Models\User;
use App\Tax\TaxAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class VoidMushakRequest extends FormRequest
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

        return $authorizer->canVoid($user, $mushak);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'max:500'],
        ];
    }
}
