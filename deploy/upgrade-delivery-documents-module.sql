-- Ahmad Solution Ltd. ERP delivery challan and commercial invoice upgrade.
-- Use this when the quotation tables are already imported and these document tables are not.
-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.
-- This script creates the challan and commercial invoice tables, adds their permissions, and grants the default role set.
-- A draft edit keeps the previous line row and marks it superseded. Current lines are the rows whose superseded_at is null.
-- It does not delete rows, grants, or customized permissions. It is safe to import again.
-- Importing it again restores a default grant that was removed. It does not void documents.
-- It expects users, roles, permissions, permission_role, quotations, quotation_revisions, quotation_lines, and migrations.

SET NAMES utf8mb4;
SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));

CREATE TABLE IF NOT EXISTS `delivery_challan_number_sequences` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commercial_invoice_number_sequences` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_challans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(32) DEFAULT NULL,
  `quotation_id` bigint unsigned NOT NULL,
  `quotation_revision_id` bigint unsigned NOT NULL,
  `status` varchar(20) NOT NULL,
  `challan_date` date NOT NULL,
  `delivery_location` varchar(200) DEFAULT NULL,
  `recipient_name` varchar(160) DEFAULT NULL,
  `recipient_phone` varchar(40) DEFAULT NULL,
  `vehicle_or_carrier` varchar(160) DEFAULT NULL,
  `carrier_reference` varchar(120) DEFAULT NULL,
  `party_legal_name` varchar(200) NOT NULL,
  `party_trading_name` varchar(200) DEFAULT NULL,
  `party_kind` varchar(40) NOT NULL,
  `party_tin` varchar(30) DEFAULT NULL,
  `party_bin` varchar(30) DEFAULT NULL,
  `party_vat_registration_number` varchar(40) DEFAULT NULL,
  `party_email` varchar(255) DEFAULT NULL,
  `party_phone` varchar(40) DEFAULT NULL,
  `contact_name` varchar(160) DEFAULT NULL,
  `contact_designation` varchar(120) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(40) DEFAULT NULL,
  `billing_address` json DEFAULT NULL,
  `shipping_address` json DEFAULT NULL,
  `customer_notes` text,
  `internal_notes` text,
  `company_snapshot` json DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `dispatched_by` bigint unsigned DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `acknowledged_by` bigint unsigned DEFAULT NULL,
  `acknowledgment_name` varchar(160) DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_challans_number_unique` (`number`),
  KEY `delivery_challans_quotation_id_foreign` (`quotation_id`),
  KEY `delivery_challans_quotation_revision_id_foreign` (`quotation_revision_id`),
  KEY `delivery_challans_issued_by_foreign` (`issued_by`),
  KEY `delivery_challans_dispatched_by_foreign` (`dispatched_by`),
  KEY `delivery_challans_acknowledged_by_foreign` (`acknowledged_by`),
  KEY `delivery_challans_voided_by_foreign` (`voided_by`),
  KEY `delivery_challans_created_by_foreign` (`created_by`),
  KEY `delivery_challans_quotation_status_index` (`quotation_id`, `status`),
  CONSTRAINT `delivery_challans_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_challans_quotation_revision_id_foreign` FOREIGN KEY (`quotation_revision_id`) REFERENCES `quotation_revisions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_challans_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_challans_dispatched_by_foreign` FOREIGN KEY (`dispatched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_challans_acknowledged_by_foreign` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_challans_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_challans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_challan_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_challan_id` bigint unsigned NOT NULL,
  `quotation_line_id` bigint unsigned NOT NULL,
  `position` smallint unsigned NOT NULL,
  `description` text NOT NULL,
  `unit` varchar(40) NOT NULL,
  `quantity` decimal(16,4) NOT NULL,
  `accepted_quantity` decimal(16,4) NOT NULL,
  `superseded_at` timestamp NULL DEFAULT NULL,
  `superseded_by` bigint unsigned DEFAULT NULL,
  `active_position` smallint unsigned GENERATED ALWAYS AS (case when `superseded_at` is null then `position` else null end) STORED,
  `active_quotation_line_id` bigint unsigned GENERATED ALWAYS AS (case when `superseded_at` is null then `quotation_line_id` else null end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_challan_lines_current_position_unique` (`delivery_challan_id`, `active_position`),
  UNIQUE KEY `delivery_challan_lines_current_line_unique` (`delivery_challan_id`, `active_quotation_line_id`),
  KEY `delivery_challan_lines_delivery_challan_id_foreign` (`delivery_challan_id`),
  KEY `delivery_challan_lines_quotation_line_id_foreign` (`quotation_line_id`),
  KEY `delivery_challan_lines_superseded_by_foreign` (`superseded_by`),
  CONSTRAINT `delivery_challan_lines_delivery_challan_id_foreign` FOREIGN KEY (`delivery_challan_id`) REFERENCES `delivery_challans` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_challan_lines_quotation_line_id_foreign` FOREIGN KEY (`quotation_line_id`) REFERENCES `quotation_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_challan_lines_superseded_by_foreign` FOREIGN KEY (`superseded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_challan_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_challan_id` bigint unsigned NOT NULL,
  `event` varchar(40) NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `recipient_name` varchar(160) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_challan_events_delivery_challan_id_foreign` (`delivery_challan_id`),
  KEY `delivery_challan_events_actor_id_foreign` (`actor_id`),
  KEY `delivery_challan_events_challan_id_index` (`delivery_challan_id`, `id`),
  CONSTRAINT `delivery_challan_events_delivery_challan_id_foreign` FOREIGN KEY (`delivery_challan_id`) REFERENCES `delivery_challans` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_challan_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commercial_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(32) DEFAULT NULL,
  `quotation_id` bigint unsigned NOT NULL,
  `quotation_revision_id` bigint unsigned NOT NULL,
  `status` varchar(20) NOT NULL,
  `invoice_date` date NOT NULL,
  `party_legal_name` varchar(200) NOT NULL,
  `party_trading_name` varchar(200) DEFAULT NULL,
  `party_kind` varchar(40) NOT NULL,
  `party_tin` varchar(30) DEFAULT NULL,
  `party_bin` varchar(30) DEFAULT NULL,
  `party_vat_registration_number` varchar(40) DEFAULT NULL,
  `party_email` varchar(255) DEFAULT NULL,
  `party_phone` varchar(40) DEFAULT NULL,
  `contact_name` varchar(160) DEFAULT NULL,
  `contact_designation` varchar(120) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(40) DEFAULT NULL,
  `billing_address` json DEFAULT NULL,
  `shipping_address` json DEFAULT NULL,
  `customer_notes` text,
  `internal_notes` text,
  `tax_notice` text NOT NULL,
  `tax_treatment` varchar(40) NOT NULL,
  `subtotal` decimal(18,2) NOT NULL,
  `total` decimal(18,2) NOT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'BDT',
  `company_snapshot` json DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commercial_invoices_number_unique` (`number`),
  KEY `commercial_invoices_quotation_id_foreign` (`quotation_id`),
  KEY `commercial_invoices_quotation_revision_id_foreign` (`quotation_revision_id`),
  KEY `commercial_invoices_issued_by_foreign` (`issued_by`),
  KEY `commercial_invoices_voided_by_foreign` (`voided_by`),
  KEY `commercial_invoices_created_by_foreign` (`created_by`),
  KEY `commercial_invoices_quotation_status_index` (`quotation_id`, `status`),
  CONSTRAINT `commercial_invoices_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commercial_invoices_quotation_revision_id_foreign` FOREIGN KEY (`quotation_revision_id`) REFERENCES `quotation_revisions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commercial_invoices_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commercial_invoices_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commercial_invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commercial_invoice_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `commercial_invoice_id` bigint unsigned NOT NULL,
  `delivery_challan_line_id` bigint unsigned NOT NULL,
  `quotation_line_id` bigint unsigned NOT NULL,
  `position` smallint unsigned NOT NULL,
  `description` text NOT NULL,
  `unit` varchar(40) NOT NULL,
  `quantity` decimal(16,4) NOT NULL,
  `unit_price` decimal(16,2) NOT NULL,
  `line_total` decimal(18,2) NOT NULL,
  `superseded_at` timestamp NULL DEFAULT NULL,
  `superseded_by` bigint unsigned DEFAULT NULL,
  `active_position` smallint unsigned GENERATED ALWAYS AS (case when `superseded_at` is null then `position` else null end) STORED,
  `active_challan_line_id` bigint unsigned GENERATED ALWAYS AS (case when `superseded_at` is null then `delivery_challan_line_id` else null end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commercial_invoice_lines_current_position_unique` (`commercial_invoice_id`, `active_position`),
  UNIQUE KEY `commercial_invoice_lines_current_challan_unique` (`commercial_invoice_id`, `active_challan_line_id`),
  KEY `commercial_invoice_lines_commercial_invoice_id_foreign` (`commercial_invoice_id`),
  KEY `commercial_invoice_lines_delivery_challan_line_id_foreign` (`delivery_challan_line_id`),
  KEY `commercial_invoice_lines_quotation_line_id_foreign` (`quotation_line_id`),
  KEY `commercial_invoice_lines_superseded_by_foreign` (`superseded_by`),
  CONSTRAINT `commercial_invoice_lines_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commercial_invoice_lines_delivery_challan_line_id_foreign` FOREIGN KEY (`delivery_challan_line_id`) REFERENCES `delivery_challan_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commercial_invoice_lines_quotation_line_id_foreign` FOREIGN KEY (`quotation_line_id`) REFERENCES `quotation_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commercial_invoice_lines_superseded_by_foreign` FOREIGN KEY (`superseded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commercial_invoice_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `commercial_invoice_id` bigint unsigned NOT NULL,
  `event` varchar(40) NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commercial_invoice_events_commercial_invoice_id_foreign` (`commercial_invoice_id`),
  KEY `commercial_invoice_events_actor_id_foreign` (`actor_id`),
  KEY `commercial_invoice_events_invoice_id_index` (`commercial_invoice_id`, `id`),
  CONSTRAINT `commercial_invoice_events_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commercial_invoice_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep superseded draft lines on a database created by the first version of this file.
SET @challan_line_history := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'delivery_challan_lines'
    AND COLUMN_NAME = 'superseded_at'
);
SET @sql := IF(@challan_line_history = 0,
  'ALTER TABLE `delivery_challan_lines` ADD COLUMN `superseded_at` timestamp NULL DEFAULT NULL, ADD COLUMN `superseded_by` bigint unsigned DEFAULT NULL, ADD COLUMN `active_position` smallint unsigned GENERATED ALWAYS AS (case when `superseded_at` is null then `position` else null end) STORED, ADD COLUMN `active_quotation_line_id` bigint unsigned GENERATED ALWAYS AS (case when `superseded_at` is null then `quotation_line_id` else null end) STORED, ADD UNIQUE KEY `delivery_challan_lines_current_position_unique` (`delivery_challan_id`, `active_position`), ADD UNIQUE KEY `delivery_challan_lines_current_line_unique` (`delivery_challan_id`, `active_quotation_line_id`), ADD CONSTRAINT `delivery_challan_lines_superseded_by_foreign` FOREIGN KEY (`superseded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE challan_line_history FROM @sql;
EXECUTE challan_line_history;
DEALLOCATE PREPARE challan_line_history;

SET @challan_old_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'delivery_challan_lines'
    AND INDEX_NAME = 'delivery_challan_lines_challan_position_unique'
);
SET @sql := IF(@challan_old_index > 0,
  'ALTER TABLE `delivery_challan_lines` DROP INDEX `delivery_challan_lines_challan_position_unique`, DROP INDEX `delivery_challan_lines_challan_line_unique`',
  'SELECT 1');
PREPARE challan_line_history_index FROM @sql;
EXECUTE challan_line_history_index;
DEALLOCATE PREPARE challan_line_history_index;

SET @invoice_line_history := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'commercial_invoice_lines'
    AND COLUMN_NAME = 'superseded_at'
);
SET @sql := IF(@invoice_line_history = 0,
  'ALTER TABLE `commercial_invoice_lines` ADD COLUMN `superseded_at` timestamp NULL DEFAULT NULL, ADD COLUMN `superseded_by` bigint unsigned DEFAULT NULL, ADD COLUMN `active_position` smallint unsigned GENERATED ALWAYS AS (case when `superseded_at` is null then `position` else null end) STORED, ADD COLUMN `active_challan_line_id` bigint unsigned GENERATED ALWAYS AS (case when `superseded_at` is null then `delivery_challan_line_id` else null end) STORED, ADD UNIQUE KEY `commercial_invoice_lines_current_position_unique` (`commercial_invoice_id`, `active_position`), ADD UNIQUE KEY `commercial_invoice_lines_current_challan_unique` (`commercial_invoice_id`, `active_challan_line_id`), ADD CONSTRAINT `commercial_invoice_lines_superseded_by_foreign` FOREIGN KEY (`superseded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE invoice_line_history FROM @sql;
EXECUTE invoice_line_history;
DEALLOCATE PREPARE invoice_line_history;

SET @invoice_old_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'commercial_invoice_lines'
    AND INDEX_NAME = 'commercial_invoice_lines_invoice_position_unique'
);
SET @sql := IF(@invoice_old_index > 0,
  'ALTER TABLE `commercial_invoice_lines` DROP INDEX `commercial_invoice_lines_invoice_position_unique`, DROP INDEX `commercial_invoice_lines_invoice_challan_unique`',
  'SELECT 1');
PREPARE invoice_line_history_index FROM @sql;
EXECUTE invoice_line_history_index;
DEALLOCATE PREPARE invoice_line_history_index;

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 38, 'View delivery challans', 'challans.view', 'Challans', 'View delivery challans for customers this user is allowed to see.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 38 OR `existing_permissions`.`slug` = 'challans.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 39, 'Create delivery challans', 'challans.create', 'Challans', 'Create a draft delivery challan from an accepted quotation.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 39 OR `existing_permissions`.`slug` = 'challans.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 40, 'Update delivery challans', 'challans.update', 'Challans', 'Edit a draft delivery challan.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 40 OR `existing_permissions`.`slug` = 'challans.update');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 41, 'Issue delivery challans', 'challans.issue', 'Challans', 'Issue a delivery challan. Issuing assigns the number and freezes the document.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 41 OR `existing_permissions`.`slug` = 'challans.issue');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 42, 'Record challan dispatch', 'challans.dispatch', 'Challans', 'Record that an issued challan was dispatched. A PDF download does not do this.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 42 OR `existing_permissions`.`slug` = 'challans.dispatch');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 43, 'Record challan acknowledgment', 'challans.acknowledge', 'Challans', 'Record that the recipient acknowledged an issued challan.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 43 OR `existing_permissions`.`slug` = 'challans.acknowledge');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 44, 'Void delivery challans', 'challans.void', 'Challans', 'Void a delivery challan. The record is kept. A challan used by an open invoice cannot be voided.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 44 OR `existing_permissions`.`slug` = 'challans.void');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 45, 'Download challan PDFs', 'challans.pdf', 'Challans', 'Download a delivery challan PDF. The file is not stored under public/.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 45 OR `existing_permissions`.`slug` = 'challans.pdf');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 46, 'View commercial invoices', 'invoices.view', 'Invoices', 'View commercial invoices for customers this user is allowed to see.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 46 OR `existing_permissions`.`slug` = 'invoices.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 47, 'Create commercial invoices', 'invoices.create', 'Invoices', 'Create a draft commercial invoice from issued challans of an accepted quotation.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 47 OR `existing_permissions`.`slug` = 'invoices.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 48, 'Update commercial invoices', 'invoices.update', 'Invoices', 'Edit a draft commercial invoice.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 48 OR `existing_permissions`.`slug` = 'invoices.update');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 49, 'Issue commercial invoices', 'invoices.issue', 'Invoices', 'Issue a commercial invoice. Issuing assigns the number and freezes the document.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 49 OR `existing_permissions`.`slug` = 'invoices.issue');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 50, 'Void commercial invoices', 'invoices.void', 'Invoices', 'Void a commercial invoice. The record is kept. This does not void the challan.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 50 OR `existing_permissions`.`slug` = 'invoices.void');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 51, 'Download invoice PDFs', 'invoices.pdf', 'Invoices', 'Download a commercial invoice PDF. The file is not stored under public/.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 51 OR `existing_permissions`.`slug` = 'invoices.pdf');

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 38 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 38);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 39 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 39);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 40 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 40);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 41 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 41);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 42 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 42);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 43 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 43);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 44 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 44);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 45 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 45);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 46 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 46);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 47 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 47);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 48 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 48);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 49 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 49);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 50 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 50);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 51 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 51);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 38 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 38);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 39 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 39);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 40 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 40);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 41 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 41);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 42 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 42);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 43 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 43);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 44 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 44);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 45 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 45);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 46 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 46);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 47 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 47);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 48 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 48);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 49 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 49);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 50 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 50);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 51 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 51);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 38 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 38);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 39 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 39);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 40 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 40);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 41 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 41);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 42 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 42);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 43 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 43);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 44 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 44);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 45 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 45);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 46 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 46);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 47 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 47);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 48 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 48);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 49 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 49);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 50 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 50);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 51 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 51);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 38 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 38);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 45 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 45);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 46 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 46);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 47 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 47);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 48 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 48);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 49 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 49);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 51 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 51);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 38 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 38);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 39 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 39);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 40 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 40);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 41 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 41);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 42 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 42);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 43 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 43);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 44 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 44);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 45 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 45);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 46 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 46);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 47 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 47);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 48 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 48);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 49 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 49);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 50 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 50);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 51 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 51);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 38 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 38);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 39 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 39);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 40 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 40);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 41 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 41);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 42 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 42);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 43 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 43);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 45 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 45);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 46 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 46);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 47 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 47);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 48 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 48);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 49 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 49);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 51 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 51);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_07_100001_create_delivery_document_tables', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_07_100001_create_delivery_document_tables');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_07_100002_grant_delivery_document_access', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_07_100002_grant_delivery_document_access');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_07_100003_preserve_delivery_document_line_history', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_07_100003_preserve_delivery_document_line_history');
