<?php

namespace App\Http\Requests;

use App\Documents\DocumentAuthorizer;
use App\Models\DeliveryChallan;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeChallanRequest extends FormRequest
{
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

        return $user->hasPermission('challans.acknowledge');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'acknowledgment_name' => ['required', 'string', 'max:160'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
