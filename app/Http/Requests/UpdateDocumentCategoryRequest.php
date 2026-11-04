<?php

namespace App\Http\Requests;

use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission('company.document_categories.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof DocumentCategory ? $category->id : null;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique(DocumentCategory::class, 'name')->ignore($categoryId)],
            'is_active' => ['boolean'],
        ];
    }
}
