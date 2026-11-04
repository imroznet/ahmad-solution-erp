<?php

namespace App\Authorization;

/**
 * Code-owned permission and role catalogue.
 *
 * Slugs are registered here so every authorization check is reviewed in Git.
 * Role assignments are configurable at runtime, except the super administrator
 * role, which always retains every registered permission.
 */
final class PermissionCatalog
{
    /**
     * @return list<array{id: int, name: string, slug: string, group: string, description: string}>
     */
    public static function permissions(): array
    {
        return [
            self::permission(1, 'View dashboard', 'dashboard.view', 'Administration', 'View the business dashboard.'),
            self::permission(2, 'View users', 'users.view', 'Administration', 'View user accounts.'),
            self::permission(3, 'Create users', 'users.create', 'Administration', 'Create user accounts.'),
            self::permission(4, 'Update users', 'users.update', 'Administration', 'Update user accounts and role assignments.'),
            self::permission(5, 'Deactivate users', 'users.deactivate', 'Administration', 'Activate or deactivate user accounts.'),
            self::permission(6, 'View roles', 'roles.view', 'Administration', 'View roles and their permissions.'),
            self::permission(7, 'Manage role permissions', 'roles.manage', 'Administration', 'Change which permissions a role grants.'),
            self::permission(8, 'View audit log', 'audit.view', 'Administration', 'View the security and change audit log.'),
            self::permission(9, 'View system status', 'system.view', 'Administration', 'View runtime status, including PHP extensions.'),
            self::permission(10, 'View company profile', 'company.view', 'Company', 'View the company profile and addresses.'),
            self::permission(11, 'Update company profile', 'company.update', 'Company', 'Update the company profile and logo.'),
            self::permission(12, 'Manage company addresses', 'company.address.manage', 'Company', 'Create, correct, and close company addresses.'),
            self::permission(13, 'View company documents', 'company.documents.view', 'Company', 'View legal document records and expiry dates.'),
            self::permission(14, 'Create company documents', 'company.documents.create', 'Company', 'Add legal document records and files.'),
            self::permission(15, 'Update company documents', 'company.documents.update', 'Company', 'Update legal document records and add file versions.'),
            self::permission(16, 'Delete company documents', 'company.documents.delete', 'Company', 'Retire a legal document record. Stored file versions are kept.'),
            self::permission(17, 'Download company documents', 'company.documents.download', 'Company', 'Download private legal document files.'),
            self::permission(18, 'Manage document categories', 'company.document_categories.manage', 'Company', 'Create, rename, and deactivate document categories.'),
        ];
    }

    /**
     * @return list<array{id: int, name: string, slug: string, description: string, permissions: list<string>|string}>
     */
    public static function roles(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Super Administrator',
                'slug' => 'super-administrator',
                'description' => 'Full access. This role cannot be stripped of permissions.',
                'permissions' => '*',
            ],
            [
                'id' => 2,
                'name' => 'Administrator',
                'slug' => 'administrator',
                'description' => 'Manages users, the company profile, and legal documents. Cannot change the permission matrix.',
                'permissions' => [
                    'dashboard.view',
                    'users.view',
                    'users.create',
                    'users.update',
                    'users.deactivate',
                    'roles.view',
                    'audit.view',
                    'system.view',
                    'company.view',
                    'company.update',
                    'company.address.manage',
                    'company.documents.view',
                    'company.documents.create',
                    'company.documents.update',
                    'company.documents.delete',
                    'company.documents.download',
                    'company.document_categories.manage',
                ],
            ],
            [
                'id' => 3,
                'name' => 'Accounts Manager',
                'slug' => 'accounts-manager',
                'description' => 'Views the company profile and legal documents. Accounting permissions are added with that module.',
                'permissions' => [
                    'dashboard.view',
                    'company.view',
                    'company.documents.view',
                    'company.documents.download',
                ],
            ],
            [
                'id' => 4,
                'name' => 'Accountant',
                'slug' => 'accountant',
                'description' => 'Views the company profile and legal documents. Day-to-day accounting permissions are added with that module.',
                'permissions' => [
                    'dashboard.view',
                    'company.view',
                    'company.documents.view',
                    'company.documents.download',
                ],
            ],
            [
                'id' => 5,
                'name' => 'Sales Manager',
                'slug' => 'sales-manager',
                'description' => 'Views the company profile. Quotation permissions are added with that module.',
                'permissions' => ['dashboard.view', 'company.view'],
            ],
            [
                'id' => 6,
                'name' => 'Sales Executive',
                'slug' => 'sales-executive',
                'description' => 'Views the company profile. Quotation preparation permissions are added with that module.',
                'permissions' => ['dashboard.view', 'company.view'],
            ],
            [
                'id' => 7,
                'name' => 'Document Manager',
                'slug' => 'document-manager',
                'description' => 'Manages company addresses, legal documents, and document categories.',
                'permissions' => [
                    'dashboard.view',
                    'company.view',
                    'company.address.manage',
                    'company.documents.view',
                    'company.documents.create',
                    'company.documents.update',
                    'company.documents.delete',
                    'company.documents.download',
                    'company.document_categories.manage',
                ],
            ],
            [
                'id' => 8,
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only access to the dashboard. Additional view permissions are assigned explicitly.',
                'permissions' => ['dashboard.view'],
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public static function permissionIdsForRole(string $slug): array
    {
        $permissionsBySlug = [];

        foreach (self::permissions() as $permission) {
            $permissionsBySlug[$permission['slug']] = $permission['id'];
        }

        foreach (self::roles() as $role) {
            if ($role['slug'] !== $slug) {
                continue;
            }

            if ($role['permissions'] === '*') {
                return array_values($permissionsBySlug);
            }

            return array_map(
                fn (string $permission): int => $permissionsBySlug[$permission],
                $role['permissions'],
            );
        }

        return [];
    }

    /**
     * @return array{id: int, name: string, slug: string, group: string, description: string}
     */
    private static function permission(int $id, string $name, string $slug, string $group, string $description): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'group' => $group,
            'description' => $description,
        ];
    }
}
