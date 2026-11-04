<?php

namespace Database\Seeders;

use App\Authorization\PermissionCatalog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::permissions() as $permission) {
            $record = Permission::query()->firstOrNew(['slug' => $permission['slug']]);
            $record->name = $permission['name'];
            $record->permission_group = $permission['group'];
            $record->description = $permission['description'];

            if (! $record->exists) {
                $record->id = $permission['id'];
            }

            $record->save();
        }

        foreach (PermissionCatalog::roles() as $definition) {
            $role = Role::query()->firstOrNew(['slug' => $definition['slug']]);
            $role->name = $definition['name'];
            $role->description = $definition['description'];
            $role->is_system = true;

            if (! $role->exists) {
                $role->id = $definition['id'];
            }

            $role->save();

            $permissionIds = PermissionCatalog::permissionIdsForRole($definition['slug']);

            // Super administrator always retains every permission. Other roles
            // receive the default set only when they have none, so a reviewed
            // runtime change is not overwritten on the next seed.
            if ($role->slug === 'super-administrator' || $role->permissions()->count() === 0) {
                $role->permissions()->sync($permissionIds);
            }
        }
    }
}
