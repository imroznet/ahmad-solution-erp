<?php

namespace App\Http\Requests;

use App\Documents\DocumentAuthorizer;
use App\Models\CommercialInvoice;
use App\Models\MushakInvoice;
use App\Models\User;
use App\Tax\RoundingMethod;
use App\Tax\TaxAuthorizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMushakRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $authorizer = app(TaxAuthorizer::class);
        $mushak = $this->route('mushak');

        if ($mushak instanceof MushakInvoice) {
            if (! $authorizer->canView($user, $mushak)) {
                abort(404);
            }

            return $authorizer->canUpdate($user, $mushak);
        }

        $invoice = CommercialInvoice::query()->find($this->input('commercial_invoice_id'));

        if (! $invoice instanceof CommercialInvoice || ! app(DocumentAuthorizer::class)->canViewInvoice($user, $invoice)) {
            abort(404);
        }

        return $authorizer->canCreate($user, $invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'commercial_invoice_id' => ['required', 'integer'],
            'document_date' => ['required', 'date'],
            'buyer_source' => ['required', 'in:party,manual'],
            'party_id' => ['nullable', 'integer'],
            'buyer_name' => ['nullable', 'string', 'max:200'],
            'buyer_bin' => ['nullable', 'string', 'max:40'],
            'buyer_tin' => ['nullable', 'string', 'max:40'],
            'buyer_address' => ['nullable', 'string', 'max:2000'],
            'buyer_phone' => ['nullable', 'string', 'max:40'],
            'buyer_email' => ['nullable', 'string', 'max:160'],
            'supply_destination' => ['nullable', 'string', 'max:200'],
            'supply_classification' => ['nullable', 'string', 'max:80'],
            'calculation_precision' => ['required', 'integer', 'in:2,3,4'],
            'rounding_method' => ['required', Rule::in(RoundingMethod::all())],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.commercial_invoice_line_id' => ['required', 'integer'],
            'lines.*.description' => ['required', 'string', 'max:2000'],
            'lines.*.unit' => ['required', 'string', 'max:40'],
            'lines.*.quantity' => ['required', 'string', 'max:20'],
            'lines.*.base_amount' => ['required', 'string', 'max:24'],
            'lines.*.vat_rate' => ['nullable', 'string', 'max:12'],
            'lines.*.adjustment_amount' => ['nullable', 'string', 'max:24'],
            'lines.*.adjustment_reason' => ['nullable', 'string', 'max:500'],
            'lines.*.final_amount' => ['nullable', 'string', 'max:24'],
            'lines.*.edited' => ['required', 'in:base,final,adjustment'],
        ];
    }
}
