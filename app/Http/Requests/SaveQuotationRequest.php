<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\Quotation;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use App\Quotations\QuotationCalculator;
use App\Quotations\QuotationRuleException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveQuotationRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $quotation = $this->route('quotation');
        $authorizer = app(QuotationAuthorizer::class);

        if ($quotation instanceof Quotation) {
            if (! $authorizer->canView($user, $quotation)) {
                abort(404);
            }

            return $this->routeIs('quotations.revisions.store')
                ? $authorizer->canRevise($user, $quotation)
                : $authorizer->canUpdate($user, $quotation);
        }

        return $authorizer->canCreate($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxLines = (int) config('asl.quotations.max_lines', 50);

        return [
            'party_id' => ['required', 'integer'],
            'quotation_date' => ['required', 'date_format:Y-m-d'],
            'valid_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:quotation_date'],
            'contact_id' => ['nullable', 'integer'],
            'billing_address_id' => ['nullable', 'integer'],
            'shipping_address_id' => ['nullable', 'integer'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:'.$maxLines],
            'lines.*.description' => ['required', 'string', 'max:2000'],
            'lines.*.unit' => ['required', 'string', 'max:40'],
            'lines.*.quantity' => ['required', 'string', 'max:20'],
            'lines.*.unit_price' => ['required', 'string', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lines = $this->input('lines', []);

            if (! is_array($lines)) {
                return;
            }

            try {
                app(QuotationCalculator::class)->normalizeLines(array_values(array_filter($lines, 'is_array')));
            } catch (QuotationRuleException $exception) {
                $validator->errors()->add($exception->field, $exception->getMessage());
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'contact_id',
            'billing_address_id',
            'shipping_address_id',
            'terms',
            'customer_notes',
            'internal_notes',
        ]);

        $lines = $this->input('lines', []);

        if (! is_array($lines)) {
            return;
        }

        $normalized = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $description = trim((string) ($line['description'] ?? ''));
            $unit = trim((string) ($line['unit'] ?? ''));
            $quantity = trim((string) ($line['quantity'] ?? ''));
            $price = trim((string) ($line['unit_price'] ?? ''));

            if ($description === '' && $unit === '' && $quantity === '' && $price === '') {
                continue;
            }

            $normalized[] = [
                'description' => $description,
                'unit' => $unit,
                'quantity' => $quantity,
                'unit_price' => $price,
            ];
        }

        $this->merge(['lines' => $normalized]);
    }
}
