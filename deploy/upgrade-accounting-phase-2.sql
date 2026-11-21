-- Milestone 7, phase 2. Import once, after deploy/upgrade-accounting-phase-1.sql,
-- on a database that already has parties, users, and commercial invoices.
-- A second import does not duplicate the permission, the grants, or the migration row.

CREATE TABLE IF NOT EXISTS `receipt_number_sequences` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(32) NOT NULL,
  `party_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `receipt_date` date NOT NULL,
  `amount_received` decimal(18,2) NOT NULL DEFAULT 0.00,
  `vds_deducted` decimal(18,2) NOT NULL DEFAULT 0.00,
  `ait_deducted` decimal(18,2) NOT NULL DEFAULT 0.00,
  `mode` varchar(20) NOT NULL,
  `reference` varchar(80) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `void_reason` text,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipts_number_unique` (`number`),
  KEY `receipts_status_receipt_date_index` (`status`, `receipt_date`),
  KEY `receipts_party_id_foreign` (`party_id`),
  KEY `receipts_account_id_foreign` (`account_id`),
  KEY `receipts_voided_by_foreign` (`voided_by`),
  KEY `receipts_created_by_foreign` (`created_by`),
  CONSTRAINT `receipts_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `receipts_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `receipts_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `receipts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `receipt_allocations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `receipt_id` bigint unsigned NOT NULL,
  `commercial_invoice_id` bigint unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_allocations_receipt_id_commercial_invoice_id_unique` (`receipt_id`, `commercial_invoice_id`),
  KEY `receipt_allocations_commercial_invoice_id_foreign` (`commercial_invoice_id`),
  CONSTRAINT `receipt_allocations_receipt_id_foreign` FOREIGN KEY (`receipt_id`) REFERENCES `receipts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `receipt_allocations_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 82, 'Record customer receipts', 'receipts.create', 'Accounting', 'Record and void a customer receipt. The receipt posts immediately. A recorded receipt is not edited.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 82 OR `existing_permissions`.`slug` = 'receipts.create');

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT `roles`.`id`, `permissions`.`id`
FROM `roles`
JOIN `permissions` ON `permissions`.`slug` = 'receipts.create'
WHERE `roles`.`slug` IN ('administrator', 'super-administrator', 'accountant')
AND NOT EXISTS (
  SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants`
  WHERE `existing_grants`.`role_id` = `roles`.`id` AND `existing_grants`.`permission_id` = `permissions`.`id`
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_21_140001_create_accounting_receipts', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_21_140001_create_accounting_receipts');
