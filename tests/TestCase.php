<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function seedAccess(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithRole(string $slug, array $attributes = []): User
    {
        $this->seedAccess();

        $user = User::factory()->create($attributes);
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }
}
