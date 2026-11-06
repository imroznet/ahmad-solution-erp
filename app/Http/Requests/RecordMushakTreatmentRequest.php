<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RecordMushakTreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission('mushak.record_treatment');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tax_rule_id' => ['nullable', 'integer'],
            'treatment_note' => ['required', 'string', 'max:2000'],
            'confirmed' => ['required', 'in:1'],
        ];
    }
}
