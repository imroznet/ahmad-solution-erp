<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentVersionRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission('company.documents.update');
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['note']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxFiles = (int) config('asl.documents.max_files');
        $maxKilobytes = (int) config('asl.documents.max_kilobytes');

        return [
            'note' => ['nullable', 'string', 'max:500'],
            'files' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'files.*' => ['file', 'max:'.$maxKilobytes, 'mimes:pdf,jpg,jpeg,png'],
        ];
    }
}
