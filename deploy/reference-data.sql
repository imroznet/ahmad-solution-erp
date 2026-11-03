-- Ahmad Solution Ltd. ERP reference data
-- Roles and permissions only. No users and no secrets.
-- Import once, after the schema, into an empty access-control set of tables.
-- If these primary keys already exist, stop and review. Do not force the import.

SET NAMES utf8mb4;

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`) VALUES
(1, 'View dashboard', 'dashboard.view', 'Administration', 'View the business dashboard.', NOW(), NOW()),
(2, 'View users', 'users.view', 'Administration', 'View user accounts.', NOW(), NOW()),
(3, 'Create users', 'users.create', 'Administration', 'Create user accounts.', NOW(), NOW()),
(4, 'Update users', 'users.update', 'Administration', 'Update user accounts and role assignments.', NOW(), NOW()),
(5, 'Deactivate users', 'users.deactivate', 'Administration', 'Activate or deactivate user accounts.', NOW(), NOW()),
(6, 'View roles', 'roles.view', 'Administration', 'View roles and their permissions.', NOW(), NOW()),
(7, 'Manage role permissions', 'roles.manage', 'Administration', 'Change which permissions a role grants.', NOW(), NOW()),
(8, 'View audit log', 'audit.view', 'Administration', 'View the security and change audit log.', NOW(), NOW()),
(9, 'View system status', 'system.view', 'Administration', 'View runtime status, including PHP extensions.', NOW(), NOW());

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 'Super Administrator', 'super-administrator', 'Full access. This role cannot be stripped of permissions.', 1, NOW(), NOW()),
(2, 'Administrator', 'administrator', 'Manages users and reviews audit history. Cannot change the permission matrix.', 1, NOW(), NOW()),
(3, 'Accounts Manager', 'accounts-manager', 'Reserved for accounting supervision. Module permissions are added with those modules.', 1, NOW(), NOW()),
(4, 'Accountant', 'accountant', 'Reserved for day-to-day accounting. Module permissions are added with those modules.', 1, NOW(), NOW()),
(5, 'Sales Manager', 'sales-manager', 'Reserved for quotation supervision. Module permissions are added with those modules.', 1, NOW(), NOW()),
(6, 'Sales Executive', 'sales-executive', 'Reserved for quotation preparation. Module permissions are added with those modules.', 1, NOW(), NOW()),
(7, 'Document Manager', 'document-manager', 'Reserved for legal and certification records. Module permissions are added with that module.', 1, NOW(), NOW()),
(8, 'Viewer', 'viewer', 'Read-only access to the dashboard. Additional view permissions are assigned explicitly.', 1, NOW(), NOW());

INSERT INTO `permission_role` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 8),
(2, 9),
(3, 1),
(4, 1),
(5, 1),
(6, 1),
(7, 1),
(8, 1);
