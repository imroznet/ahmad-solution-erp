<?php

namespace App\Accounting;

use App\Authorization\PermissionCatalog;
use App\Models\CompanyProfile;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the Phase 1 accounting permissions on a database that already has roles.
 * The administrator, the super administrator, and the existing accountant receive them.
 * The accounts manager does not. Journals stay out of that role.
 * A fresh migrate finds no roles and leaves the rows to the seeder.
 *
 * The company registration date is 15 May 2025. It is written only when the
 * profile has no incorporation date, and it is not a financial year.
 */
final class AccountingInstaller
{
    /**
     * @var list<string>
     */
    private const SLUGS = [
        'accounting.view',
        'chart_of_accounts.view',
        'chart_of_accounts.manage',
        'journals.view',
        'journals.create',
        'journals.post',
        'journals.reverse',
        'receipts.create',
        'reports.view',
        'opening_balances.manage',
    ];

    public function install(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $ids = [];

        foreach (PermissionCatalog::permissions() as $permission) {
            if (! in_array($permission['slug'], self::SLUGS, true)) {
                continue;
            }

            $record = Permission::query()->firstOrNew(['slug' => $permission['slug']]);

            if (! $record->exists) {
                $record->id = $permission['id'];
                $record->name = $permission['name'];
                $record->permission_group = $permission['group'];
                $record->description = $permission['description'];
                $record->save();
            }

            $ids[] = (int) $record->getKey();
        }

        if ($ids === []) {
            return;
        }

        Role::query()
            ->whereIn('slug', ['administrator', 'super-administrator', 'accountant'])
            ->get()
            ->each(function (Role $role) use ($ids): void {
                $role->permissions()->syncWithoutDetaching($ids);
            });

        if (Schema::hasTable('company_profiles') && Schema::hasColumn('company_profiles', 'incorporation_date')) {
            CompanyProfile::query()
                ->whereNull('incorporation_date')
                ->update(['incorporation_date' => '2025-05-15']);
        }
    }
}
