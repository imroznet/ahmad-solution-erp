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
        $directory = storage_path('framework/testing-public-site');
        $path = $directory.'/public-site-maintenance';
        config(['asl.public_site.maintenance_file' => $path]);
        @unlink($path);
        @unlink($directory.'/public-site-contact-form');
        @unlink($directory.'/public-site-recaptcha');
        @unlink($directory.'/public-site-recaptcha-keys');

        $maintenance = app(PublicSiteMaintenance::class);
        $this->assertFalse($maintenance->enabled());
        $this->assertSame($path, $maintenance->path());

        $viewer = $this->userWithRole('viewer');
        $this->actingAs($viewer)->get(route('public-site.edit'))->assertForbidden();

        $admin = $this->userWithRole('administrator');
        $this->actingAs($admin)->get(route('public-site.edit'))->assertOk()->assertSee('Hide the public site for maintenance');

        $this->actingAs($admin)->put(route('public-site.update'), [
            'maintenance_enabled' => '1',
            'contact_form_enabled' => '1',
            'recaptcha_enabled' => '0',
        ])->assertRedirect(route('public-site.edit'));

        $this->assertTrue($maintenance->enabled());
        $this->assertTrue($maintenance->contactFormEnabled());
        $this->assertFalse($maintenance->recaptchaEnabled());
        $this->assertSame("1\n", file_get_contents($path));
        $this->assertSame("1\n", file_get_contents($directory.'/public-site-contact-form'));
        $this->assertSame("0\n", file_get_contents($directory.'/public-site-recaptcha'));
        $this->assertTrue(AuditLog::query()->where('event', 'public_site_updated')->where('user_id', $admin->id)->exists());

        $this->actingAs($admin)->put(route('public-site.update'), [
            'maintenance_enabled' => '0',
            'contact_form_enabled' => '0',
            'recaptcha_enabled' => '1',
        ])->assertRedirect(route('public-site.edit'));

        $this->assertFalse($maintenance->enabled());
        $this->assertFalse($maintenance->contactFormEnabled());
        $this->assertTrue($maintenance->recaptchaEnabled());
        $this->assertSame("0\n", file_get_contents($path));
        $this->assertSame("0\n", file_get_contents($directory.'/public-site-contact-form'));
        $this->assertSame("1\n", file_get_contents($directory.'/public-site-recaptcha'));
        $this->actingAs($admin)->get(route('public-site.edit'))->assertOk()->assertSee('reCAPTCHA keys missing');

        config([
            'asl.public_site.recaptcha_site_key' => 'site-key-for-test',
            'asl.public_site.recaptcha_secret_key' => 'secret-key-for-test',
        ]);
        $this->actingAs($admin)->put(route('public-site.update'), [
            'maintenance_enabled' => '0',
            'contact_form_enabled' => '1',
            'recaptcha_enabled' => '1',
        ])->assertRedirect(route('public-site.edit'));

        $keys = file_get_contents($directory.'/public-site-recaptcha-keys');
        $this->assertIsString($keys);
        $this->assertStringContainsString('site-key-for-test', $keys);
        $this->assertStringContainsString('secret-key-for-test', $keys);
        $this->assertSame(0600, fileperms($directory.'/public-site-recaptcha-keys') & 0777);
        $this->assertStringNotContainsString('secret-key-for-test', (string) json_encode(AuditLog::query()->latest('id')->first()?->new_values));
        $this->actingAs($admin)->get(route('public-site.edit'))->assertOk()->assertDontSee('reCAPTCHA keys missing');

        @unlink($path);
        @unlink($directory.'/public-site-contact-form');
        @unlink($directory.'/public-site-recaptcha');
        @unlink($directory.'/public-site-recaptcha-keys');
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
