-- Ahmad Solution Ltd. ERP quotation self-approval permission.
-- Use this when the quotation permissions are already imported.
-- It adds quotations.approve_own and grants it only to the super administrator.
-- It does not grant that permission to any other role and does not restore other grants.
-- Importing it again does not duplicate the row.
-- It expects roles, permissions, permission_role, and migrations.

SET NAMES utf8mb4;
SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 73, 'Approve own quotations', 'quotations.approve_own', 'Quotations', 'Approve a submitted quotation the user created or submitted themself. Without it, a user can only approve quotations prepared by someone else.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 73 OR `existing_permissions`.`slug` = 'quotations.approve_own');

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 73 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 73);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_19_120001_grant_quotation_self_approval', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_19_120001_grant_quotation_self_approval');
