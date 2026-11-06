<?php

namespace App\Http\Requests;

use App\Models\AitRecord;
use App\Models\Mushak63Document;
use App\Models\User;
use App\Models\VdsRecord;
use App\Tax\TaxAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class VoidTaxRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $authorizer = app(TaxAuthorizer::class);
        $mushak = $this->route('mushak');
        $vds = $this->route('vds');
        $ait = $this->route('ait');

        if ($mushak instanceof Mushak63Document) {
            if (! $authorizer->canViewMushak($user, $mushak)) {
                abort(404);
            }

            return $user->hasPermission('mushak.void');
        }

        if ($vds instanceof VdsRecord) {
            if (! $authorizer->canViewVds($user, $vds)) {
                abort(404);
            }

            return $user->hasPermission('vds.void');
        }

        if ($ait instanceof AitRecord) {
            if (! $authorizer->canViewAit($user, $ait)) {
                abort(404);
            }

            return $user->hasPermission('ait.void');
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
