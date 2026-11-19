<?php

namespace App\Quotations;

use App\Authorization\PermissionCatalog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;

/**
 * Adds quotations.approve_own on a database that already has roles.
 * The super administrator receives it. No other role does.
 * A fresh migrate finds no roles and leaves the row to the seeder.
 */
final class QuotationSelfApprovalInstaller
{
    public function install(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $definition = $this->definition();

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

        $role = Role::query()->where('slug', 'super-administrator')->first();

        if ($role instanceof Role) {
            $role->permissions()->syncWithoutDetaching([$record->getKey()]);
        }
    }

    /**
     * @return array{id: int, name: string, slug: string, group: string, description: string}|null
     */
    private function definition(): ?array
    {
        foreach (PermissionCatalog::permissions() as $permission) {
            if ($permission['slug'] === 'quotations.approve_own') {
                return $permission;
            }
        }

        return null;
    }
}
