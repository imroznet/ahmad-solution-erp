<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\PublicSite\PublicSiteMaintenance;
use App\Tax\MushakSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_site_stays_visible_until_an_administrator_hides_it(): void
    {
        $path = storage_path('framework/testing-public-site-maintenance');
        config(['asl.public_site.maintenance_file' => $path]);
        @unlink($path);

        $maintenance = app(PublicSiteMaintenance::class);
        $this->assertFalse($maintenance->enabled());
        $this->assertSame($path, $maintenance->path());

        $viewer = $this->userWithRole('viewer');
        $this->actingAs($viewer)->get(route('public-site.edit'))->assertForbidden();

        $admin = $this->userWithRole('administrator');
        $this->actingAs($admin)->get(route('public-site.edit'))->assertOk()->assertSee('Hide the public site for maintenance');

        $this->actingAs($admin)->put(route('public-site.update'), [
            'maintenance_enabled' => '1',
        ])->assertRedirect(route('public-site.edit'));

        $this->assertTrue($maintenance->enabled());
        $this->assertSame("1\n", file_get_contents($path));
        $this->assertTrue(AuditLog::query()->where('event', 'public_site_updated')->where('user_id', $admin->id)->exists());

        $this->actingAs($admin)->put(route('public-site.update'), [
            'maintenance_enabled' => '0',
        ])->assertRedirect(route('public-site.edit'));

        $this->assertFalse($maintenance->enabled());
        $this->assertSame("0\n", file_get_contents($path));
        @unlink($path);
    }

    public function test_the_mushak_title_shows_english_and_bangla_together(): void
    {
        $html = view('tax.mushak.pdf', (new MushakSample)->viewData())->render();

        $this->assertStringContainsString('Tax Invoice', $html);
        $this->assertStringContainsString('কর চালানপত্র', $html);
        $this->assertStringContainsString('[Rule 40, sub-rule (1), clauses (ga) and (cha)]', $html);
        $this->assertStringContainsString('বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও দফা (চ) দ্রষ্টব্য', $html);
        $this->assertStringNotContainsString('does not draw Bengali', $html);
    }
}
