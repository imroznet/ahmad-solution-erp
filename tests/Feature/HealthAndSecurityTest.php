<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Tests\TestCase;

class HealthAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_reveals_nothing_sensitive(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()->assertExactJson(['status' => 'ok']);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $plain = $this->get('/up');
        $plain->assertOk();
        $plain->assertSee('Service is available.');
        $plain->assertDontSee('SQLSTATE');
        $plain->assertDontSee('APP_KEY');
        $plain->assertDontSee('stack');
    }

    public function test_health_route_does_not_start_a_session(): void
    {
        $middleware = collect(app('router')->getRoutes()->getByName('health')->gatherMiddleware())
            ->map(fn ($name) => is_string($name) ? $name : '')
            ->implode(' ');

        $this->assertStringNotContainsString(StartSession::class, $middleware);
        $this->assertStringNotContainsString('StartSession', $middleware);
    }

    public function test_guest_is_sent_to_sign_in_and_registration_does_not_exist(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/register')->assertNotFound();
    }

    public function test_login_page_includes_a_csrf_field_and_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('name="_token"', false);
        $response->assertSee('Ahmad Solution Ltd.');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_private_storage_is_not_publicly_routed(): void
    {
        $this->get('/storage/secret.pdf')->assertNotFound();
    }

    public function test_environment_example_contains_placeholders_only(): void
    {
        $contents = (string) file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^APP_KEY=$/m', $contents);
        $this->assertMatchesRegularExpression('/^DB_PASSWORD=$/m', $contents);
        $this->assertMatchesRegularExpression('/^DB_DATABASE=ahmadcom_asl_erp$/m', $contents);
        $this->assertMatchesRegularExpression('/^DB_USERNAME=ahmadcom_asl_user$/m', $contents);
        $this->assertDoesNotMatchRegularExpression('/^MAIL_PASSWORD=.+$/m', $contents);
        $this->assertStringNotContainsString('Correct-Horse', $contents);
    }

    public function test_inactive_session_is_signed_out(): void
    {
        $user = $this->userWithRole('viewer', ['is_active' => false]);

        $this->actingAs($user)->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_system_status_hides_credentials(): void
    {
        $admin = $this->userWithRole('administrator');

        $response = $this->actingAs($admin)->get('/system');

        $response->assertOk();
        $response->assertSee('intl');
        $response->assertSee('bcmath');
        $response->assertSee('connected');
        $response->assertDontSee(':memory:');
        $response->assertDontSee('DB_PASSWORD');
        $response->assertDontSee((string) config('app.key'));
    }

    public function test_viewer_cannot_open_system_status(): void
    {
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($viewer)->get('/system')->assertForbidden();
    }

    public function test_user_factory_can_be_created(): void
    {
        $this->seedAccess();
        $this->assertInstanceOf(User::class, User::factory()->create());
    }
}
