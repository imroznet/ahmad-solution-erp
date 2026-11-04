<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CompanyProfile;
use Illuminate\Http\Exceptions\HttpResponseException;

trait RequiresCompanyProfile
{
    protected function requireProfile(): CompanyProfile
    {
        $profile = CompanyProfile::primary();

        if ($profile instanceof CompanyProfile) {
            return $profile;
        }

        throw new HttpResponseException(
            redirect()->route('company.show')->with('status', 'Save the company profile before continuing.')
        );
    }
}
