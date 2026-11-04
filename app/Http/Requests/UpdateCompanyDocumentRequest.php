<?php

namespace App\Http\Requests;

use App\Company\VerificationStatus;
use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\CompanyDocument;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyDocumentRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission('company.documents.update');
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'document_number',
            'issuing_authority',
            'issued_on',
            'expires_on',
            'remarks',
        ]);

        $this->merge([
            'title' => trim((string) $this->input('title')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $document = $this->route('document');
        $currentCategory = $document instanceof CompanyDocument ? $document->document_category_id : null;

        return [
            'title' => ['required', 'string', 'max:200'],
            'document_category_id' => [
                'required',
                'integer',
                Rule::exists('document_categories', 'id')->where(function ($query) use ($currentCategory): void {
                    $query->where(function ($query) use ($currentCategory): void {
                        $query->where('is_active', true);

                        if ($currentCategory !== null) {
                            $query->orWhere('id', $currentCategory);
                        }
                    });
                }),
            ],
            'document_number' => ['nullable', 'string', 'max:80'],
            'issuing_authority' => ['nullable', 'string', 'max:160'],
            'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_on' => ['nullable', 'date'],
            'verification_status' => ['required', Rule::in(array_keys(VerificationStatus::options()))],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $issued = $this->date('issued_on');
            $expires = $this->date('expires_on');

            if ($issued !== null && $expires !== null && $expires->lt($issued)) {
                $validator->errors()->add('expires_on', 'The expiry date must be on or after the issue date.');
            }
        });
    }
}
