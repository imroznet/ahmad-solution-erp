<?php

namespace App\Http\Requests;

use App\Documents\DocumentAuthorizer;
use App\Http\Requests\Concerns\NormalizesBlankInput;
use App\Models\DeliveryChallan;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DispatchChallanRequest extends FormRequest
{
    use NormalizesBlankInput;

    public function authorize(): bool
    {
        $user = $this->user();
        $challan = $this->route('challan');

        if (! $user instanceof User || ! $challan instanceof DeliveryChallan) {
            return false;
        }

        if (! app(DocumentAuthorizer::class)->canViewChallan($user, $challan)) {
            abort(404);
        }

        return $user->hasPermission('challans.dispatch');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => ['nullable', 'string', 'max:160'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['recipient_name', 'remarks']);
    }
}
