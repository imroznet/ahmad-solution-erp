<?php

namespace App\PublicSite;

use App\Authorization\PermissionCatalog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;

/**
 * Adds public_site.manage on a database that already has roles.
 * The administrator and the super administrator receive it.
 * A fresh migrate finds no roles and leaves the row to the seeder.
 */
final class PublicSiteInstaller
{
    public function install(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $definition = null;

        foreach (PermissionCatalog::permissions() as $permission) {
            if ($permission['slug'] === 'public_site.manage') {
                $definition = $permission;
                break;
            }
        }

        if ($definition === null) {
            return;
        }

        $record = Permission::query()->firstOrNew(['slug' => $definition['slug']]);

        if (! $record->exists) {
            $record->id = $definition['id'];
            $record->name = $definition['name'];
            $record->permission_group = $definition['group'];
            $record->description = $definition['description'];
            $record->save();
        }

        Role::query()
            ->whereIn('slug', ['administrator', 'super-administrator'])
            ->get()
            ->each(function (Role $role) use ($record): void {
                $role->permissions()->syncWithoutDetaching([$record->getKey()]);
            });
    }
}
