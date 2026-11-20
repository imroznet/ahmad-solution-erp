<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePublicSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('public_site.manage') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'maintenance_enabled' => ['required', 'boolean'],
            'contact_form_enabled' => ['required', 'boolean'],
            'recaptcha_enabled' => ['required', 'boolean'],
        ];
    }
}
