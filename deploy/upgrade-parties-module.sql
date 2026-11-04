-- Ahmad Solution Ltd. ERP parties upgrade.
-- Use this when the foundation and company tables are already imported and the party tables are not.
-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.
-- This script creates the party tables, adds the party permissions, and grants the default role set.
-- It does not delete rows, grants, or customized permissions. It is safe to import again.
-- It expects users, roles, permissions, permission_role, and migrations.

SET NAMES utf8mb4;
SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));

CREATE TABLE IF NOT EXISTS `parties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `legal_name` varchar(200) NOT NULL,
  `trading_name` varchar(200) DEFAULT NULL,
  `legal_name_bn` varchar(200) DEFAULT NULL,
  `trading_name_bn` varchar(200) DEFAULT NULL,
  `party_kind` varchar(40) NOT NULL,
  `registration_number` varchar(60) DEFAULT NULL,
  `tin` varchar(30) DEFAULT NULL,
  `bin` varchar(30) DEFAULT NULL,
  `vat_registration_number` varchar(40) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `notes` text,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `currency_code` char(3) NOT NULL DEFAULT 'BDT',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parties_legal_name_index` (`legal_name`),
  KEY `parties_trading_name_index` (`trading_name`),
  KEY `parties_tin_index` (`tin`),
  KEY `parties_bin_index` (`bin`),
  KEY `parties_email_index` (`email`),
  KEY `parties_phone_index` (`phone`),
  KEY `parties_status_legal_name_index` (`status`, `legal_name`),
  CONSTRAINT `parties_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `parties_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `party_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `party_id` bigint unsigned NOT NULL,
  `role` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `party_roles_party_id_role_unique` (`party_id`, `role`),
  KEY `party_roles_party_id_index` (`party_id`),
  KEY `party_roles_role_index` (`role`),
  CONSTRAINT `party_roles_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `party_contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `party_id` bigint unsigned NOT NULL,
  `name` varchar(160) NOT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `department` varchar(120) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `alternative_phone` varchar(40) DEFAULT NULL,
  `whatsapp_number` varchar(40) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `party_contacts_party_id_index` (`party_id`),
  KEY `party_contacts_primary_index` (`party_id`, `is_primary`, `is_active`),
  KEY `party_contacts_email_index` (`email`),
  KEY `party_contacts_phone_index` (`phone`),
  KEY `party_contacts_created_by_index` (`created_by`),
  KEY `party_contacts_updated_by_index` (`updated_by`),
  CONSTRAINT `party_contacts_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `party_contacts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `party_contacts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `party_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `party_id` bigint unsigned NOT NULL,
  `address_type` varchar(40) NOT NULL,
  `address_line_1` varchar(200) NOT NULL,
  `address_line_2` varchar(200) DEFAULT NULL,
  `area` varchar(120) DEFAULT NULL,
  `upazila` varchar(120) DEFAULT NULL,
  `district` varchar(120) NOT NULL,
  `division` varchar(120) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `country` varchar(80) NOT NULL,
  `is_default_billing` tinyint(1) NOT NULL DEFAULT 0,
  `is_default_shipping` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date DEFAULT NULL,
  `effective_until` date DEFAULT NULL,
  `supersedes_address_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `party_addresses_party_id_index` (`party_id`),
  KEY `party_addresses_party_active_index` (`party_id`, `is_active`),
  KEY `party_addresses_district_index` (`district`),
  KEY `party_addresses_address_type_index` (`address_type`),
  KEY `party_addresses_created_by_index` (`created_by`),
  KEY `party_addresses_updated_by_index` (`updated_by`),
  CONSTRAINT `party_addresses_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `party_addresses_supersedes_address_id_foreign` FOREIGN KEY (`supersedes_address_id`) REFERENCES `party_addresses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `party_addresses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `party_addresses_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 19, 'View parties', 'parties.view', 'Parties', 'View every customer and supplier on the shared party list.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 19 OR `existing_permissions`.`slug` = 'parties.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 20, 'Create parties', 'parties.create', 'Parties', 'Create a party and assign the customer role, the supplier role, or both.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 20 OR `existing_permissions`.`slug` = 'parties.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 21, 'Update parties', 'parties.update', 'Parties', 'Update party identity and assign or remove customer and supplier roles.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 21 OR `existing_permissions`.`slug` = 'parties.update');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 22, 'Archive parties', 'parties.archive', 'Parties', 'Archive or reactivate a party. The record is kept.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 22 OR `existing_permissions`.`slug` = 'parties.archive');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 23, 'View customers', 'customers.view', 'Parties', 'View the customer list and customer party records.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 23 OR `existing_permissions`.`slug` = 'customers.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 24, 'Manage customers', 'customers.manage', 'Parties', 'Create a customer and update a party that is not also a supplier.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 24 OR `existing_permissions`.`slug` = 'customers.manage');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 25, 'View suppliers', 'suppliers.view', 'Parties', 'View the supplier list and supplier party records.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 25 OR `existing_permissions`.`slug` = 'suppliers.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 26, 'Manage suppliers', 'suppliers.manage', 'Parties', 'Create a supplier and update a party that is not also a customer.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 26 OR `existing_permissions`.`slug` = 'suppliers.manage');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 27, 'Manage party contacts', 'parties.contacts.manage', 'Parties', 'Add, correct, and archive contacts for a party the user can update.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 27 OR `existing_permissions`.`slug` = 'parties.contacts.manage');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 28, 'Manage party addresses', 'parties.addresses.manage', 'Parties', 'Add, correct, and close addresses for a party the user can update.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 28 OR `existing_permissions`.`slug` = 'parties.addresses.manage');

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 19 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 19);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 20 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 20);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 21 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 21);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 22 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 22);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 23 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 23);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 24 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 24);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 25 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 25);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 26 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 26);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 27 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 27);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 28 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 28);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 19 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 19);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 20 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 20);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 21 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 21);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 22 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 22);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 23 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 23);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 24 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 24);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 25 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 25);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 26 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 26);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 27 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 27);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 28 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 28);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 19 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 19);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 20 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 20);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 21 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 21);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 22 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 22);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 23 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 23);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 24 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 24);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 25 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 25);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 26 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 26);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 27 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 27);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 28 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 28);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 19 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 19);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 20 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 20);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 21 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 21);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 23 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 23);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 24 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 24);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 25 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 25);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 26 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 26);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 27 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 27);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 28 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 28);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 23 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 23);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 24 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 24);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 27 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 27);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 28 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 28);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 23 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 23);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 24 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 24);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 27 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 27);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 28 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 28);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_05_100001_create_party_tables', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_05_100001_create_party_tables');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_05_100002_grant_party_module_access', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_05_100002_grant_party_module_access');
