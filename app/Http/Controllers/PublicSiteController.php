<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePublicSiteRequest;
use App\Models\User;
use App\PublicSite\PublicSiteMaintenance;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicSiteController extends Controller
{
    public function edit(PublicSiteMaintenance $maintenance): View
    {
        return view('public-site.edit', [
            'enabled' => $maintenance->enabled(),
            'contactFormEnabled' => $maintenance->contactFormEnabled(),
            'recaptchaEnabled' => $maintenance->recaptchaEnabled(),
            'recaptchaKeysPresent' => $maintenance->recaptchaKeysPresent(),
            'path' => $maintenance->path(),
            'contactFormPath' => $maintenance->contactFormPath(),
            'recaptchaPath' => $maintenance->recaptchaPath(),
            'shared' => $maintenance->sharedWithPublicSite(),
        ]);
    }

    public function update(
        SavePublicSiteRequest $request,
        PublicSiteMaintenance $maintenance,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = [
            'maintenance_enabled' => $maintenance->enabled(),
            'contact_form_enabled' => $maintenance->contactFormEnabled(),
            'recaptcha_enabled' => $maintenance->recaptchaEnabled(),
        ];
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $result = $maintenance->save(
            $request->boolean('maintenance_enabled'),
            $request->boolean('contact_form_enabled'),
            $request->boolean('recaptcha_enabled'),
            $actor,
        );

        $audit->record('public_site_updated', null, $before, [
            'maintenance_enabled' => $result['maintenance_enabled'],
            'contact_form_enabled' => $result['contact_form_enabled'],
            'recaptcha_enabled' => $result['recaptcha_enabled'],
            'recaptcha_keys_present' => $result['recaptcha_keys_present'],
            'flag_written' => $result['written'],
            'keys_written' => $result['keys_written'],
            'flag_path' => $maintenance->path(),
        ]);

        if (! $result['written']) {
            $status = 'The choice was saved here, but the flag file could not be written. The public site was not changed.';
        } elseif ($result['recaptcha_enabled'] && ! $result['recaptcha_keys_present']) {
            $status = 'The public site flag was saved. reCAPTCHA keys missing.';
        } elseif ($result['recaptcha_enabled'] && ! $result['keys_written']) {
            $status = 'The public site flag was saved. The reCAPTCHA keys could not be written for the public site.';
        } else {
            $status = 'The public site flag was saved.';
        }

        return redirect()->route('public-site.edit')->with('status', $status);
    }
}
