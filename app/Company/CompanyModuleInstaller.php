<?php

namespace App\Company;

use App\Authorization\PermissionCatalog;
use App\Models\DocumentCategory;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;

/**
 * Adds this module's reference rows to a database that already has users and roles.
 * A fresh migrate finds no roles and leaves permission grants to the seeder.
 * Running it again adds missing company grants and does not remove other grants.
 */
final class CompanyModuleInstaller
{
    public function install(): void
    {
        if (Schema::hasTable('document_categories')) {
            $this->syncCategories();
        }

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

        $companyIds = [];

        foreach (PermissionCatalog::permissions() as $permission) {
            if ($permission['group'] === 'Company') {
                $companyIds[] = $permission['id'];
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
                $companyIds,
            ));

            if ($grant !== []) {
                $role->permissions()->syncWithoutDetaching($grant);
            }
        }
    }

    public function syncCategories(): void
    {
        foreach (DocumentCategoryCatalog::categories() as $category) {
            $record = DocumentCategory::query()->firstOrNew(['slug' => $category['slug']]);

            if ($record->exists) {
                continue;
            }

            $record->id = $category['id'];
            $record->name = $category['name'];
            $record->is_system = true;
            $record->is_active = true;
            $record->sort_order = $category['sort_order'];
            $record->save();
        }
    }
}
