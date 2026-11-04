<?php

namespace App\Parties;

use App\Authorization\PermissionCatalog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;

/**
 * Adds this module's permissions to a database that already has roles.
 * A fresh migrate finds no roles and leaves grants to the seeder.
 * Running it again adds missing party grants and does not remove other grants.
 */
final class PartyModuleInstaller
{
    public function install(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || Role::query()->count() === 0) {
            return;
        }

        foreach (PermissionCatalog::permissions() as $permission) {
            $record = Permission::query()->firstOrNew(['slug' => $permission['slug']]);

            if ($record->exists) {
                continue;
            }

            $record->id = $permission['id'];
            $record->name = $permission['name'];
            $record->permission_group = $permission['group'];
            $record->description = $permission['description'];
            $record->save();
        }

        $partyIds = [];

        foreach (PermissionCatalog::permissions() as $permission) {
            if ($permission['group'] === 'Parties') {
                $partyIds[] = $permission['id'];
            }
        }

        foreach (PermissionCatalog::roles() as $definition) {
            $role = Role::query()->where('slug', $definition['slug'])->first();

            if (! $role instanceof Role) {
                continue;
            }

            if ($role->slug === 'super-administrator') {
                $role->permissions()->sync(PermissionCatalog::permissionIdsForRole($role->slug));

                continue;
            }

            $grant = array_values(array_intersect(
                PermissionCatalog::permissionIdsForRole($role->slug),
                $partyIds,
            ));

            if ($grant !== []) {
                $role->permissions()->syncWithoutDetaching($grant);
            }
        }
    }
}
