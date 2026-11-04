<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\SuperAdministratorGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_database_counts_and_hides_financial_placeholders(): void
    {
        $viewer = $this->userWithRole('viewer');

        $response = $this->actingAs($viewer)->get('/');

        $response->assertOk();
        $response->assertSee('No sample amounts are shown.');
        $response->assertViewHas('userCount', 1);
        $response->assertViewHas('activeUserCount', 1);
        $response->assertDontSee('৳');
    }

    public function test_viewer_cannot_manage_users_and_administrator_can_create_one(): void
    {
        $viewer = $this->userWithRole('viewer');
        $this->actingAs($viewer)->get('/users')->assertForbidden();

        $admin = $this->userWithRole('administrator');
        $viewerRole = Role::query()->where('slug', 'viewer')->firstOrFail();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Sales Person',
            'email' => 'Sales@Example.com',
            'password' => 'Correct-Horse-1',
            'password_confirmation' => 'Correct-Horse-1',
            'roles' => [$viewerRole->id],
        ])->assertRedirect(route('users.index'));

        $created = User::query()->where('email', 'sales@example.com')->firstOrFail();
        $this->assertTrue($created->roles->contains('slug', 'viewer'));

        $encoded = AuditLog::query()->where('event', 'user_created')->get()->toJson();
        $this->assertStringNotContainsString('Correct-Horse-1', $encoded);

        $this->actingAs($admin)->get(route('users.edit', $created))->assertOk();
    }

    public function test_administrator_cannot_assign_or_edit_a_super_administrator(): void
    {
        $admin = $this->userWithRole('administrator');
        $super = User::factory()->create(['email' => 'root@example.com']);
        $superRole = Role::query()->where('slug', 'super-administrator')->firstOrFail();
        $super->roles()->attach($superRole);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Second Root',
            'email' => 'second-root@example.com',
            'password' => 'Correct-Horse-1',
            'password_confirmation' => 'Correct-Horse-1',
            'roles' => [$superRole->id],
        ])->assertSessionHasErrors('roles');

        $this->actingAs($admin)->get(route('users.edit', $super))->assertForbidden();
    }

    public function test_user_cannot_deactivate_themselves(): void
    {
        $admin = $this->userWithRole('administrator');

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => 'Renamed Admin',
            'email' => $admin->email,
            'roles' => [$admin->roles()->firstOrFail()->id],
            'is_active' => '0',
        ])->assertRedirect(route('users.index'));

        $admin->refresh();
        $this->assertSame('Renamed Admin', $admin->name);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->isSuperAdministrator() === false);
    }

    public function test_last_active_super_administrator_cannot_be_removed(): void
    {
        $super = $this->userWithRole('super-administrator');
        $viewerRole = Role::query()->where('slug', 'viewer')->firstOrFail();

        $this->expectException(ValidationException::class);

        app(SuperAdministratorGuard::class)->assertChangeIsSafe($super, [$viewerRole->id], true);
    }

    public function test_role_permissions_can_be_changed_and_are_preserved_on_reseed(): void
    {
        $super = $this->userWithRole('super-administrator');
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $audit = Permission::query()->where('slug', 'audit.view')->firstOrFail();

        $this->actingAs($super)->get(route('roles.edit', $viewer))->assertOk();

        $this->actingAs($super)->put(route('roles.update', $viewer), [
            'permissions' => [$audit->id],
        ])->assertRedirect(route('roles.index'));

        $this->seedAccess();

        $viewer->refresh();
        $this->assertSame(['audit.view'], $viewer->permissions()->orderBy('slug')->pluck('slug')->all());

        $superRole = Role::query()->where('slug', 'super-administrator')->firstOrFail();
        $superRole->permissions()->sync([]);
        $this->seedAccess();

        $this->assertSame(
            Permission::query()->count(),
            $superRole->fresh()->permissions()->count(),
        );

        $this->actingAs($super)->put(route('roles.update', $superRole), [
            'permissions' => [],
        ])->assertForbidden();
    }

    public function test_setting_a_password_revokes_that_users_sessions_only(): void
    {
        $admin = $this->userWithRole('administrator');
        $user = $this->userWithRole('viewer', ['email' => 'staff@example.com']);
        $previousToken = $user->remember_token;
        $viewerRole = $user->roles()->firstOrFail();

        DB::table('sessions')->insert([
            [
                'id' => 'staff-session',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => 'staff',
                'last_activity' => time(),
            ],
            [
                'id' => 'admin-session',
                'user_id' => $admin->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => 'admin',
                'last_activity' => time(),
            ],
        ]);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Staff Person',
            'email' => $user->email,
            'roles' => [$viewerRole->id],
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('sessions', ['id' => 'staff-session']);
        $this->assertSame($previousToken, $user->fresh()->remember_token);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Staff Person',
            'email' => $user->email,
            'password' => 'New-Password-22',
            'password_confirmation' => 'New-Password-22',
            'roles' => [$viewerRole->id],
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('sessions', ['id' => 'staff-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);
        $this->assertNotSame($previousToken, $user->fresh()->remember_token);
        $this->assertStringNotContainsString('New-Password-22', AuditLog::query()->get()->toJson());
    }

    public function test_administrator_can_view_roles_but_cannot_change_them(): void
    {
        $admin = $this->userWithRole('administrator');
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();

        $this->actingAs($admin)->get('/roles')->assertOk();
        $this->actingAs($admin)->get(route('roles.edit', $viewer))->assertForbidden();
    }
}
