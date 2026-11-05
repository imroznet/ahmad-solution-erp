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
            self::permission(19, 'View parties', 'parties.view', 'Parties', 'View every customer and supplier on the shared party list.'),
            self::permission(20, 'Create parties', 'parties.create', 'Parties', 'Create a party and assign the customer role, the supplier role, or both.'),
            self::permission(21, 'Update parties', 'parties.update', 'Parties', 'Update party identity and assign or remove customer and supplier roles.'),
            self::permission(22, 'Archive parties', 'parties.archive', 'Parties', 'Archive or reactivate a party. The record is kept.'),
            self::permission(23, 'View customers', 'customers.view', 'Parties', 'View the customer list and customer party records.'),
            self::permission(24, 'Manage customers', 'customers.manage', 'Parties', 'Create a customer and update a party that is not also a supplier.'),
            self::permission(25, 'View suppliers', 'suppliers.view', 'Parties', 'View the supplier list and supplier party records.'),
            self::permission(26, 'Manage suppliers', 'suppliers.manage', 'Parties', 'Create a supplier and update a party that is not also a customer.'),
            self::permission(27, 'Manage party contacts', 'parties.contacts.manage', 'Parties', 'Add, correct, and archive contacts for a party the user can update.'),
            self::permission(28, 'Manage party addresses', 'parties.addresses.manage', 'Parties', 'Add, correct, and close addresses for a party the user can update.'),
            self::permission(29, 'View quotations', 'quotations.view', 'Quotations', 'View quotations for customers this user is allowed to see.'),
            self::permission(30, 'Create quotations', 'quotations.create', 'Quotations', 'Create a draft quotation for an active customer.'),
            self::permission(31, 'Update quotations', 'quotations.update', 'Quotations', 'Edit a draft and open a new revision of an issued quotation.'),
            self::permission(32, 'Submit quotations', 'quotations.submit', 'Quotations', 'Submit a draft quotation for approval.'),
            self::permission(33, 'Approve quotations', 'quotations.approve', 'Quotations', 'Approve or return a submitted quotation prepared by someone else.'),
            self::permission(34, 'Send quotations', 'quotations.send', 'Quotations', 'Record that an approved quotation was sent. This does not send email.'),
            self::permission(35, 'Cancel quotations', 'quotations.cancel', 'Quotations', 'Cancel a quotation. The record is kept.'),
            self::permission(36, 'Share quotations', 'quotations.share', 'Quotations', 'Create or revoke a customer share link. Only the token hash is stored.'),
            self::permission(37, 'Record quotation responses', 'quotations.respond', 'Quotations', 'Record a customer acceptance or rejection against the sent revision.'),
            self::permission(38, 'View delivery challans', 'challans.view', 'Challans', 'View delivery challans for customers this user is allowed to see.'),
            self::permission(39, 'Create delivery challans', 'challans.create', 'Challans', 'Create a draft delivery challan from an accepted quotation.'),
            self::permission(40, 'Update delivery challans', 'challans.update', 'Challans', 'Edit a draft delivery challan.'),
            self::permission(41, 'Issue delivery challans', 'challans.issue', 'Challans', 'Issue a delivery challan. Issuing assigns the number and freezes the document.'),
            self::permission(42, 'Record challan dispatch', 'challans.dispatch', 'Challans', 'Record that an issued challan was dispatched. A PDF download does not do this.'),
            self::permission(43, 'Record challan acknowledgment', 'challans.acknowledge', 'Challans', 'Record that the recipient acknowledged an issued challan.'),
            self::permission(44, 'Void delivery challans', 'challans.void', 'Challans', 'Void a delivery challan. The record is kept. A challan used by an open invoice cannot be voided.'),
            self::permission(45, 'Download challan PDFs', 'challans.pdf', 'Challans', 'Download a delivery challan PDF. The file is not stored under public/.'),
            self::permission(46, 'View commercial invoices', 'invoices.view', 'Invoices', 'View commercial invoices for customers this user is allowed to see.'),
            self::permission(47, 'Create commercial invoices', 'invoices.create', 'Invoices', 'Create a draft commercial invoice from issued challans of an accepted quotation.'),
            self::permission(48, 'Update commercial invoices', 'invoices.update', 'Invoices', 'Edit a draft commercial invoice.'),
            self::permission(49, 'Issue commercial invoices', 'invoices.issue', 'Invoices', 'Issue a commercial invoice. Issuing assigns the number and freezes the document.'),
            self::permission(50, 'Void commercial invoices', 'invoices.void', 'Invoices', 'Void a commercial invoice. The record is kept. This does not void the challan.'),
            self::permission(51, 'Download invoice PDFs', 'invoices.pdf', 'Invoices', 'Download a commercial invoice PDF. The file is not stored under public/.'),
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
                'description' => 'Manages users, the company profile, legal documents, customers, suppliers, quotations, delivery challans, and commercial invoices. Cannot change the permission matrix.',
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
                    'parties.view',
                    'parties.create',
                    'parties.update',
                    'parties.archive',
                    'customers.view',
                    'customers.manage',
                    'suppliers.view',
                    'suppliers.manage',
                    'parties.contacts.manage',
                    'parties.addresses.manage',
                    'quotations.view',
                    'quotations.create',
                    'quotations.update',
                    'quotations.submit',
                    'quotations.approve',
                    'quotations.send',
                    'quotations.cancel',
                    'quotations.share',
                    'quotations.respond',
                    'challans.view',
                    'challans.create',
                    'challans.update',
                    'challans.issue',
                    'challans.dispatch',
                    'challans.acknowledge',
                    'challans.void',
                    'challans.pdf',
                    'invoices.view',
                    'invoices.create',
                    'invoices.update',
                    'invoices.issue',
                    'invoices.void',
                    'invoices.pdf',
                ],
            ],
            [
                'id' => 3,
                'name' => 'Accounts Manager',
                'slug' => 'accounts-manager',
                'description' => 'Views the company profile and legal documents, manages customers and suppliers, and can approve a quotation prepared by someone else. Can prepare and void delivery challans and commercial invoices. Broader accounting permissions are added with that module.',
                'permissions' => [
                    'dashboard.view',
                    'company.view',
                    'company.documents.view',
                    'company.documents.download',
                    'parties.view',
                    'parties.create',
                    'parties.update',
                    'parties.archive',
                    'customers.view',
                    'customers.manage',
                    'suppliers.view',
                    'suppliers.manage',
                    'parties.contacts.manage',
                    'parties.addresses.manage',
                    'quotations.view',
                    'quotations.approve',
                    'challans.view',
                    'challans.create',
                    'challans.update',
                    'challans.issue',
                    'challans.dispatch',
                    'challans.acknowledge',
                    'challans.void',
                    'challans.pdf',
                    'invoices.view',
                    'invoices.create',
                    'invoices.update',
                    'invoices.issue',
                    'invoices.void',
                    'invoices.pdf',
                ],
            ],
            [
                'id' => 4,
                'name' => 'Accountant',
                'slug' => 'accountant',
                'description' => 'Views the company profile, legal documents, quotations, and delivery challans, prepares commercial invoices, and maintains customer and supplier records. Cannot void a challan or an invoice. Day-to-day accounting permissions are added with that module.',
                'permissions' => [
                    'dashboard.view',
                    'company.view',
                    'company.documents.view',
                    'company.documents.download',
                    'parties.view',
                    'parties.create',
                    'parties.update',
                    'customers.view',
                    'customers.manage',
                    'suppliers.view',
                    'suppliers.manage',
                    'parties.contacts.manage',
                    'parties.addresses.manage',
                    'quotations.view',
                    'challans.view',
                    'challans.pdf',
                    'invoices.view',
                    'invoices.create',
                    'invoices.update',
                    'invoices.issue',
                    'invoices.pdf',
                ],
            ],
            [
                'id' => 5,
                'name' => 'Sales Manager',
                'slug' => 'sales-manager',
                'description' => 'Views the company profile, manages customers, and can approve a quotation prepared by someone else. Can prepare and void delivery challans and commercial invoices. Supplier records are not included.',
                'permissions' => [
                    'dashboard.view',
                    'company.view',
                    'customers.view',
                    'customers.manage',
                    'parties.contacts.manage',
                    'parties.addresses.manage',
                    'quotations.view',
                    'quotations.create',
                    'quotations.update',
                    'quotations.submit',
                    'quotations.approve',
                    'quotations.send',
                    'quotations.cancel',
                    'quotations.share',
                    'quotations.respond',
                    'challans.view',
                    'challans.create',
                    'challans.update',
                    'challans.issue',
                    'challans.dispatch',
                    'challans.acknowledge',
                    'challans.void',
                    'challans.pdf',
                    'invoices.view',
                    'invoices.create',
                    'invoices.update',
                    'invoices.issue',
                    'invoices.void',
                    'invoices.pdf',
                ],
            ],
            [
                'id' => 6,
                'name' => 'Sales Executive',
                'slug' => 'sales-executive',
                'description' => 'Views the company profile, manages customers, and prepares quotations, delivery challans, and commercial invoices. Cannot approve or cancel a quotation, and cannot void a challan or an invoice. Supplier records are not included.',
                'permissions' => [
                    'dashboard.view',
                    'company.view',
                    'customers.view',
                    'customers.manage',
                    'parties.contacts.manage',
                    'parties.addresses.manage',
                    'quotations.view',
                    'quotations.create',
                    'quotations.update',
                    'quotations.submit',
                    'quotations.send',
                    'quotations.share',
                    'quotations.respond',
                    'challans.view',
                    'challans.create',
                    'challans.update',
                    'challans.issue',
                    'challans.dispatch',
                    'challans.acknowledge',
                    'challans.pdf',
                    'invoices.view',
                    'invoices.create',
                    'invoices.update',
                    'invoices.issue',
                    'invoices.pdf',
                ],
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
