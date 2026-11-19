-- Ahmad Solution Ltd. ERP measurement units upgrade.
-- Use this when the access-control tables are already imported and the units table is not.
-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.
-- This script creates the units table, adds the unit permissions, grants the default role set, and inserts the starter list.
-- It does not delete rows, grants, or customized permissions. It is safe to import again.
-- A second import puts back a starter symbol that was deleted. It does not change text already stored on a line.
-- It expects roles, permissions, permission_role, and migrations.

SET NAMES utf8mb4;
SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));

CREATE TABLE IF NOT EXISTS `units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `symbol` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_symbol_unique` (`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 71, 'View measurement units', 'units.view', 'Units', 'View the measurement unit list used on quotation, challan, invoice, and Mushak lines.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 71 OR `existing_permissions`.`slug` = 'units.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 72, 'Manage measurement units', 'units.manage', 'Units', 'Create, edit, deactivate, and delete measurement units. Existing line text is not rewritten.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 72 OR `existing_permissions`.`slug` = 'units.manage');

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 71 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 71);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 72 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 72);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 71 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 71);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 72 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 72);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 71 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 71);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 72 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 72);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 71 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 71);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 71 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 71);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 71 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 71);

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Pieces', 'pcs', 1, 10, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'pcs');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Kilogram', 'kg', 1, 20, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'kg');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Gram', 'g', 1, 30, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'g');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Litre', 'L', 1, 40, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'L');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Millilitre', 'ml', 1, 50, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'ml');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Metre', 'm', 1, 60, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'm');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Centimetre', 'cm', 1, 70, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'cm');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Square foot', 'sq ft', 1, 80, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'sq ft');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Box', 'box', 1, 90, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'box');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Set', 'set', 1, 100, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'set');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Pair', 'pair', 1, 110, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'pair');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Hour', 'hour', 1, 120, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'hour');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Day', 'day', 1, 130, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'day');

INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Job / lot', 'job', 1, 140, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = 'job');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_19_110001_create_units_table', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_19_110001_create_units_table');
