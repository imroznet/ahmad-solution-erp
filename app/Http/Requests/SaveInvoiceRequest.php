<?php

namespace App\Http\Requests;

use App\Documents\DocumentAuthorizer;
use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\CommercialInvoice;
use App\Models\Quotation;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class SaveInvoiceRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $invoice = $this->route('invoice');
        $documents = app(DocumentAuthorizer::class);

        if ($invoice instanceof CommercialInvoice) {
            if (! $documents->canViewInvoice($user, $invoice)) {
                abort(404);
            }

            return $documents->canUpdateInvoice($user, $invoice);
        }

        $quotation = Quotation::query()->find($this->input('quotation_id'));

        if (! $quotation instanceof Quotation || ! app(QuotationAuthorizer::class)->canView($user, $quotation)) {
            abort(404);
        }

        return $user->hasPermission('invoices.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxLines = (int) config('asl.quotations.max_lines', 50);

        return [
            'quotation_id' => ['required', 'integer'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:'.$maxLines],
            'lines.*.delivery_challan_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['customer_notes', 'internal_notes']);
    }
}
