<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_sign_in_and_the_event_is_audited(): void
    {
        $user = $this->userWithRole('viewer', [
            'email' => 'viewer@example.com',
            'password' => 'Correct-Horse-1',
        ]);

        $this->post('/login', [
            'email' => 'Viewer@Example.com',
            'password' => 'Correct-Horse-1',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login',
            'user_id' => $user->id,
        ]);
    }

    public function test_failed_sign_in_does_not_record_the_password(): void
    {
        $this->post('/login', [
            'email' => 'missing@example.com',
            'password' => 'SuperSecret-99',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('audit_logs', ['event' => 'login_failed']);
        $this->assertStringNotContainsString('SuperSecret-99', AuditLog::query()->get()->toJson());
    }

    public function test_inactive_user_with_the_right_password_cannot_sign_in(): void
    {
        $user = $this->userWithRole('accountant', [
            'email' => 'accounts@example.com',
            'password' => 'Correct-Horse-1',
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Correct-Horse-1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'login_inactive']);
        $this->assertStringNotContainsString('Correct-Horse-1', AuditLog::query()->get()->toJson());
    }

    public function test_sign_in_is_throttled_after_five_failures(): void
    {
        $user = $this->userWithRole('viewer', [
            'email' => 'viewer@example.com',
            'password' => 'Correct-Horse-1',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Correct-Horse-1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'login_lockout']);
    }

    public function test_password_reset_does_not_reveal_whether_the_account_exists(): void
    {
        Notification::fake();
        $user = $this->userWithRole('viewer', ['email' => 'viewer@example.com']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', 'If an account exists for that email, a reset link has been sent.');

        $this->post('/forgot-password', ['email' => 'missing@example.com'])
            ->assertSessionHas('status', 'If an account exists for that email, a reset link has been sent.');

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_password_can_be_reset_and_audited_without_storing_it(): void
    {
        Notification::fake();
        $user = $this->userWithRole('viewer', ['email' => 'viewer@example.com']);

        $this->post('/forgot-password', ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'New-Password-22',
            'password_confirmation' => 'New-Password-22',
        ])->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'New-Password-22',
        ])->assertRedirect(route('dashboard'));

        $this->assertStringNotContainsString('New-Password-22', AuditLog::query()->get()->toJson());
    }

    public function test_sign_out_is_audited(): void
    {
        $user = $this->userWithRole('viewer');

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'logout',
            'user_id' => $user->id,
        ]);
    }

    public function test_audit_rows_cannot_be_changed_or_deleted(): void
    {
        $this->seedAccess();
        $log = AuditLog::query()->create([
            'event' => 'login',
            'new_values' => ['email' => 'viewer@example.com'],
        ]);

        $log->event = 'tampered';

        $this->assertFalse($log->save());
        $this->assertFalse($log->delete());
        $this->assertSame('login', $log->fresh()->event);
        $this->assertInstanceOf(User::class, User::factory()->make());
    }
}
