<?php

namespace App\Http\Requests;

use App\Models\Mushak63Document;
use App\Models\User;
use App\Tax\TaxAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class SaveMushakRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $mushak = $this->route('mushak');

        if ($mushak instanceof Mushak63Document) {
            if (! app(TaxAuthorizer::class)->canViewMushak($user, $mushak)) {
                abort(404);
            }

            return $user->hasPermission('mushak.update');
        }

        return $user->hasPermission('mushak.create') || $user->hasPermission('mushak.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'commercial_invoice_id' => ['required', 'integer'],
            'document_id' => ['nullable', 'integer'],
            'document_date' => ['required', 'date'],
            'buyer_source' => ['required', 'in:party,manual'],
            'party_id' => ['nullable', 'integer'],
            'buyer_legal_name' => ['nullable', 'string', 'max:200'],
            'buyer_trading_name' => ['nullable', 'string', 'max:200'],
            'buyer_bin' => ['nullable', 'string', 'max:30'],
            'buyer_tin' => ['nullable', 'string', 'max:30'],
            'buyer_phone' => ['nullable', 'string', 'max:40'],
            'buyer_email' => ['nullable', 'string', 'max:255'],
            'buyer_address' => ['nullable', 'string', 'max:2000'],
            'tax_rule_version_id' => ['nullable', 'integer'],
            'confirm_treatment' => ['nullable', 'boolean'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.commercial_invoice_line_id' => ['required', 'integer'],
            'lines.*.description' => ['required', 'string', 'max:2000'],
            'lines.*.unit' => ['required', 'string', 'max:40'],
            'lines.*.quantity' => ['required', 'string', 'max:20'],
            'lines.*.unit_price' => ['required', 'string', 'max:24'],
            'lines.*.base_amount' => ['required', 'string', 'max:24'],
            'lines.*.vat_rate' => ['nullable', 'string', 'max:20'],
            'lines.*.inclusive_amount' => ['nullable', 'string', 'max:24'],
            'lines.*.supplementary_duty_amount' => ['nullable', 'string', 'max:24'],
            'lines.*.driver' => ['nullable', 'string', 'max:20'],
        ];
    }
}
