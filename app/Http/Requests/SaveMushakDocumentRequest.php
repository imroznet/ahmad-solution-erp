<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\MushakDocument;
use App\Models\User;
use App\Tax\MushakAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class SaveMushakDocumentRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $authorizer = app(MushakAuthorizer::class);
        $document = $this->route('mushak');

        if ($document instanceof MushakDocument) {
            if (! $authorizer->canView($user, $document)) {
                abort(404);
            }

            return $authorizer->canUpdate($user, $document);
        }

        return $user->hasPermission('mushak.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxLines = (int) config('asl.mushak.max_lines', 30);

        return [
            'buyer_source' => ['required', 'in:party,manual'],
            'party_id' => ['nullable', 'integer'],
            'commercial_invoice_id' => ['nullable', 'integer'],
            'buyer_name' => ['nullable', 'string', 'max:200'],
            'buyer_bin' => ['nullable', 'string', 'max:30'],
            'buyer_tin' => ['nullable', 'string', 'max:30'],
            'buyer_address' => ['nullable', 'string', 'max:1000'],
            'buyer_phone' => ['nullable', 'string', 'max:40'],
            'buyer_email' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:300'],
            'responsible_name' => ['nullable', 'string', 'max:200'],
            'responsible_designation' => ['nullable', 'string', 'max:120'],
            'issue_date' => ['nullable', 'date_format:Y-m-d'],
            'issue_time' => ['nullable', 'date_format:H:i'],
            'total_value' => ['nullable', 'string', 'max:24'],
            'total_supplementary_duty' => ['nullable', 'string', 'max:24'],
            'total_vat' => ['nullable', 'string', 'max:24'],
            'total_inclusive' => ['nullable', 'string', 'max:24'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'max:'.$maxLines],
            'lines.*.commercial_invoice_line_id' => ['nullable', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:1000'],
            'lines.*.unit' => ['nullable', 'string', 'max:40'],
            'lines.*.quantity' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price' => ['nullable', 'string', 'max:24'],
            'lines.*.line_value' => ['nullable', 'string', 'max:24'],
            'lines.*.supplementary_duty' => ['nullable', 'string', 'max:24'],
            'lines.*.vat_rate' => ['nullable', 'string', 'max:20'],
            'lines.*.specific_tax_note' => ['nullable', 'string', 'max:120'],
            'lines.*.vat_amount' => ['nullable', 'string', 'max:24'],
            'lines.*.inclusive_value' => ['nullable', 'string', 'max:24'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'party_id',
            'commercial_invoice_id',
            'buyer_name',
            'buyer_bin',
            'buyer_tin',
            'buyer_address',
            'buyer_phone',
            'buyer_email',
            'destination',
            'responsible_name',
            'responsible_designation',
            'issue_date',
            'issue_time',
            'total_value',
            'total_supplementary_duty',
            'total_vat',
            'total_inclusive',
            'internal_notes',
        ]);

        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        $clean = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            foreach (['description', 'unit', 'quantity', 'unit_price', 'line_value', 'supplementary_duty', 'vat_rate', 'specific_tax_note', 'vat_amount', 'inclusive_value', 'commercial_invoice_line_id'] as $key) {
                if (! array_key_exists($key, $line) || ! is_string($line[$key])) {
                    continue;
                }

                $trimmed = trim($line[$key]);
                $line[$key] = $trimmed === '' ? null : $trimmed;
            }

            $clean[] = $line;
        }

        $this->merge(['lines' => $clean]);
    }
}
