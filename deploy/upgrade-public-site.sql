-- Public site maintenance flag.
-- Import once on a database that already has users, roles, permissions, and migrations.
-- Importing again does not add a second control row, does not turn maintenance back off,
-- and does not duplicate the permission or its grants.

CREATE TABLE IF NOT EXISTS `public_site_controls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `maintenance_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `public_site_controls_updated_by_foreign` (`updated_by`),
  CONSTRAINT `public_site_controls_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `public_site_controls` (`id`, `maintenance_enabled`, `created_at`, `updated_at`)
SELECT 1, 0, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `public_site_controls`) AS `existing_controls` WHERE `existing_controls`.`id` = 1);

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 74, 'Manage the public site', 'public_site.manage', 'Administration', 'Show or hide the public website while it is under maintenance. This does not hide the ERP.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 74 OR `existing_permissions`.`slug` = 'public_site.manage');

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT `roles`.`id`, `permissions`.`id`
FROM `roles`
JOIN `permissions` ON `permissions`.`slug` = 'public_site.manage'
WHERE `roles`.`slug` IN ('administrator', 'super-administrator')
AND NOT EXISTS (
  SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants`
  WHERE `existing_grants`.`role_id` = `roles`.`id` AND `existing_grants`.`permission_id` = `permissions`.`id`
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_20_120001_create_public_site_controls', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_20_120001_create_public_site_controls');
