<?php

namespace App\Documents;

use App\Authorization\PermissionCatalog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;

/**
 * Adds challan and commercial-invoice permissions to a database that already has roles.
 * A fresh migrate finds no roles and leaves grants to the seeder.
 * Running it again adds missing document grants and does not remove other grants.
 */
final class DocumentModuleInstaller
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

        $documentIds = self::permissionIds();

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
                $documentIds,
            ));

            if ($grant !== []) {
                $role->permissions()->syncWithoutDetaching($grant);
            }
        }
    }

    /**
     * @return list<int>
     */
    public static function permissionIds(): array
    {
        $ids = [];

        foreach (PermissionCatalog::permissions() as $permission) {
            if (in_array($permission['group'], ['Challans', 'Invoices'], true)) {
                $ids[] = $permission['id'];
            }
        }

        return $ids;
    }
}
