<?php

namespace App\Http\Requests;

use App\Documents\DocumentAuthorizer;
use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\DeliveryChallan;
use App\Models\Quotation;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class SaveChallanRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $challan = $this->route('challan');
        $documents = app(DocumentAuthorizer::class);

        if ($challan instanceof DeliveryChallan) {
            if (! $documents->canViewChallan($user, $challan)) {
                abort(404);
            }

            return $documents->canUpdateChallan($user, $challan);
        }

        $quotation = Quotation::query()->find($this->input('quotation_id'));

        if (! $quotation instanceof Quotation || ! app(QuotationAuthorizer::class)->canView($user, $quotation)) {
            abort(404);
        }

        return $user->hasPermission('challans.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxLines = (int) config('asl.quotations.max_lines', 50);

        return [
            'quotation_id' => ['required', 'integer'],
            'challan_date' => ['required', 'date_format:Y-m-d'],
            'delivery_location' => ['nullable', 'string', 'max:200'],
            'recipient_name' => ['nullable', 'string', 'max:160'],
            'recipient_phone' => ['nullable', 'string', 'max:40'],
            'vehicle_or_carrier' => ['nullable', 'string', 'max:160'],
            'carrier_reference' => ['nullable', 'string', 'max:120'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:'.$maxLines],
            'lines.*.quotation_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'delivery_location',
            'recipient_name',
            'recipient_phone',
            'vehicle_or_carrier',
            'carrier_reference',
            'customer_notes',
            'internal_notes',
        ]);
    }
}
