<?php

namespace App\Http\Requests;

use App\Documents\DocumentAuthorizer;
use App\Models\CommercialInvoice;
use App\Models\DeliveryChallan;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class VoidDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $authorizer = app(DocumentAuthorizer::class);
        $challan = $this->route('challan');
        $invoice = $this->route('invoice');

        if ($challan instanceof DeliveryChallan) {
            if (! $authorizer->canViewChallan($user, $challan)) {
                abort(404);
            }

            return $user->hasPermission('challans.void');
        }

        if ($invoice instanceof CommercialInvoice) {
            if (! $authorizer->canViewInvoice($user, $invoice)) {
                abort(404);
            }

            return $user->hasPermission('invoices.void');
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
