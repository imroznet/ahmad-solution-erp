<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePublicSiteRequest;
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
            'path' => $maintenance->path(),
            'shared' => $maintenance->sharedWithPublicSite(),
        ]);
    }

    public function update(
        SavePublicSiteRequest $request,
        PublicSiteMaintenance $maintenance,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $maintenance->enabled();
        $enabled = $request->boolean('maintenance_enabled');
        $result = $maintenance->save($enabled, $request->user());

        $audit->record('public_site_updated', null, [
            'maintenance_enabled' => $before,
        ], [
            'maintenance_enabled' => $result['enabled'],
            'flag_written' => $result['written'],
            'flag_path' => $maintenance->path(),
        ]);

        $status = $result['written']
            ? 'The public site flag was saved.'
            : 'The choice was saved here, but the flag file could not be written. The public site was not changed.';

        return redirect()->route('public-site.edit')->with('status', $status);
    }
}
