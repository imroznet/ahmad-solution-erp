-- Milestone 7, phase 3. Import once, after deploy/upgrade-accounting-phase-2.sql,
-- on a database that already has the ledger, receipts, parties, and users.
-- A second import does not duplicate a table, a column, the permission, the grants, or the migration row.
-- It does not change a cash-flow classification or a closed year that is already stored.

SET @receipt_category := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'receipts'
    AND COLUMN_NAME = 'cash_flow_category'
);
SET @sql := IF(@receipt_category = 0,
  'ALTER TABLE `receipts` ADD COLUMN `cash_flow_category` varchar(20) NOT NULL DEFAULT ''operating'' AFTER `reference`',
  'SELECT 1');
PREPARE receipt_cash_flow FROM @sql;
EXECUTE receipt_cash_flow;
DEALLOCATE PREPARE receipt_cash_flow;

SET @year_closed := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'financial_years'
    AND COLUMN_NAME = 'closed_at'
);
SET @sql := IF(@year_closed = 0,
  'ALTER TABLE `financial_years` ADD COLUMN `closed_at` timestamp NULL DEFAULT NULL AFTER `ends_on`, ADD COLUMN `closed_by` bigint unsigned DEFAULT NULL AFTER `closed_at`, ADD COLUMN `close_reason` text AFTER `closed_by`, ADD COLUMN `carry_forward_journal_id` bigint unsigned DEFAULT NULL AFTER `close_reason`, ADD KEY `financial_years_closed_by_foreign` (`closed_by`), ADD KEY `financial_years_carry_forward_journal_id_foreign` (`carry_forward_journal_id`), ADD CONSTRAINT `financial_years_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL, ADD CONSTRAINT `financial_years_carry_forward_journal_id_foreign` FOREIGN KEY (`carry_forward_journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE financial_year_close FROM @sql;
EXECUTE financial_year_close;
DEALLOCATE PREPARE financial_year_close;

CREATE TABLE IF NOT EXISTS `payment_number_sequences` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(32) NOT NULL,
  `party_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `mode` varchar(20) NOT NULL,
  `reference` varchar(80) DEFAULT NULL,
  `cash_flow_category` varchar(20) NOT NULL DEFAULT 'operating',
  `status` varchar(20) NOT NULL,
  `void_reason` text,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_number_unique` (`number`),
  KEY `payments_status_payment_date_index` (`status`, `payment_date`),
  KEY `payments_party_id_foreign` (`party_id`),
  KEY `payments_account_id_foreign` (`account_id`),
  KEY `payments_voided_by_foreign` (`voided_by`),
  KEY `payments_created_by_foreign` (`created_by`),
  CONSTRAINT `payments_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payments_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payments_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_allocations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned NOT NULL,
  `journal_id` bigint unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_allocations_payment_id_journal_id_unique` (`payment_id`, `journal_id`),
  KEY `payment_allocations_journal_id_foreign` (`journal_id`),
  CONSTRAINT `payment_allocations_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_allocations_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Retained Earnings prefers code 3000. An equity account already on that code, with no
-- system key, is claimed and reactivated. A different account keeps 3000, and this
-- insert takes the lowest free code from 3000 through 3999. A second import does neither.
UPDATE `chart_of_accounts`
SET `system_key` = 'retained_earnings',
    `is_active` = 1,
    `updated_at` = NOW()
WHERE `code` = '3000'
  AND `type` = 'equity'
  AND (`system_key` IS NULL OR `system_key` = '')
  AND NOT EXISTS (
    SELECT 1 FROM (
      SELECT `id` FROM `chart_of_accounts` WHERE `system_key` = 'retained_earnings'
    ) AS `existing_retained`
  );

INSERT INTO `chart_of_accounts` (`code`, `name`, `type`, `system_key`, `is_active`, `created_at`, `updated_at`)
SELECT `candidate`.`code`, 'Retained Earnings', 'equity', 'retained_earnings', 1, NOW(), NOW()
FROM (
  SELECT CONCAT('3', LPAD(`numbers`.`n`, 3, '0')) AS `code`
  FROM (
    SELECT `ones`.`d` + `tens`.`d` * 10 + `hundreds`.`d` * 100 AS `n`
    FROM
      (SELECT 0 AS `d` UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS `ones`
      CROSS JOIN (SELECT 0 AS `d` UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS `tens`
      CROSS JOIN (SELECT 0 AS `d` UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS `hundreds`
  ) AS `numbers`
  WHERE `numbers`.`n` <= 999
) AS `candidate`
WHERE NOT EXISTS (
  SELECT 1 FROM (SELECT `system_key` FROM `chart_of_accounts`) AS `existing_accounts`
  WHERE `existing_accounts`.`system_key` = 'retained_earnings'
)
AND NOT EXISTS (
  SELECT 1 FROM (SELECT `code` FROM `chart_of_accounts`) AS `existing_codes`
  WHERE `existing_codes`.`code` = `candidate`.`code`
)
ORDER BY `candidate`.`code`
LIMIT 1;

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 83, 'Record supplier payments', 'payments.create', 'Accounting', 'Record and void a supplier payment. The payment posts immediately. A recorded payment is not edited.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 83 OR `existing_permissions`.`slug` = 'payments.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 86, 'Manage financial periods', 'periods.manage', 'Accounting', 'Close a past financial year and carry its result into Retained Earnings. A closed year rejects new postings. Reopening a year is limited to the super administrator.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 86 OR `existing_permissions`.`slug` = 'periods.manage');

UPDATE `permissions`
SET `description` = 'View the trial balance, general ledger, receivables and payables aging, profit and loss, balance sheet, and cash flow.'
WHERE `slug` = 'reports.view';

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT `roles`.`id`, `permissions`.`id`
FROM `roles`
JOIN `permissions` ON `permissions`.`slug` IN ('payments.create', 'periods.manage')
WHERE `roles`.`slug` IN ('administrator', 'super-administrator', 'accountant')
AND NOT EXISTS (
  SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants`
  WHERE `existing_grants`.`role_id` = `roles`.`id` AND `existing_grants`.`permission_id` = `permissions`.`id`
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_21_160001_create_accounting_payments', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_21_160001_create_accounting_payments');
