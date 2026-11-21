-- Accounting phase 1: chart of accounts, journals, trial balance, financial years, and bank/MFS details.
-- Import once on a database that already has users, roles, permissions, and parties.
-- Importing again does not add a second copy of a system account, does not rename one,
-- does not insert a bank or MFS account, does not duplicate permissions or grants,
-- and does not replace an incorporation date that is already stored.
-- Cash in Hand is the only cash account this file creates. Bank details are entered later in the ERP.
-- The registration date below is used only when company_profiles.incorporation_date is null.
-- It is not a financial year. Financial years are entered in the ERP and are not inserted here.

CREATE TABLE IF NOT EXISTS `chart_of_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(8) NOT NULL,
  `name` varchar(160) NOT NULL,
  `type` varchar(20) NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `system_key` varchar(40) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chart_of_accounts_code_unique` (`code`),
  UNIQUE KEY `chart_of_accounts_system_key_unique` (`system_key`),
  KEY `chart_of_accounts_parent_id_foreign` (`parent_id`),
  KEY `chart_of_accounts_created_by_foreign` (`created_by`),
  CONSTRAINT `chart_of_accounts_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chart_of_accounts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `bank_name` varchar(160) NOT NULL,
  `branch` varchar(160) NOT NULL,
  `account_title` varchar(160) NOT NULL,
  `account_number` varchar(40) NOT NULL,
  `account_type` varchar(20) NOT NULL,
  `opened_on` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_accounts_account_id_unique` (`account_id`),
  UNIQUE KEY `bank_accounts_bank_name_account_number_unique` (`bank_name`, `account_number`),
  CONSTRAINT `bank_accounts_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mfs_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `provider` varchar(20) NOT NULL,
  `mobile_number` varchar(20) NOT NULL,
  `account_title` varchar(160) NOT NULL,
  `opened_on` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mfs_accounts_account_id_unique` (`account_id`),
  UNIQUE KEY `mfs_accounts_mobile_number_unique` (`mobile_number`),
  CONSTRAINT `mfs_accounts_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financial_years` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `financial_years_starts_on_ends_on_index` (`starts_on`, `ends_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_number_sequences` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(32) NOT NULL,
  `status` varchar(20) NOT NULL,
  `journal_date` date NOT NULL,
  `financial_year_starts_on` date NOT NULL,
  `financial_year_ends_on` date NOT NULL,
  `memo` text,
  `source` varchar(20) NOT NULL,
  `source_type` varchar(40) DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `reverses_journal_id` bigint unsigned DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `posted_by` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `journal_entries_number_unique` (`number`),
  KEY `journal_entries_status_journal_date_index` (`status`, `journal_date`),
  KEY `journal_entries_source_type_source_id_index` (`source_type`, `source_id`),
  KEY `journal_entries_reverses_journal_id_foreign` (`reverses_journal_id`),
  KEY `journal_entries_posted_by_foreign` (`posted_by`),
  KEY `journal_entries_created_by_foreign` (`created_by`),
  CONSTRAINT `journal_entries_reverses_journal_id_foreign` FOREIGN KEY (`reverses_journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `journal_entries_posted_by_foreign` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `journal_entries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `journal_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `debit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `memo` varchar(200) DEFAULT NULL,
  `position` smallint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_lines_journal_id_foreign` (`journal_id`),
  KEY `journal_lines_account_id_journal_id_index` (`account_id`, `journal_id`),
  KEY `journal_lines_party_id_foreign` (`party_id`),
  CONSTRAINT `journal_lines_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_lines_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `journal_lines_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `account_openings` (
  `account_id` bigint unsigned NOT NULL,
  `journal_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`account_id`),
  KEY `account_openings_journal_id_foreign` (`journal_id`),
  CONSTRAINT `account_openings_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `account_openings_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `chart_of_accounts` (`code`, `name`, `type`, `system_key`, `is_active`, `created_at`, `updated_at`)
SELECT '1000', 'Cash in Hand', 'asset', 'cash', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `system_key` FROM `chart_of_accounts`) AS `existing_accounts` WHERE `existing_accounts`.`system_key` = 'cash');

INSERT INTO `chart_of_accounts` (`code`, `name`, `type`, `system_key`, `is_active`, `created_at`, `updated_at`)
SELECT '1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `system_key` FROM `chart_of_accounts`) AS `existing_accounts` WHERE `existing_accounts`.`system_key` = 'accounts_receivable');

INSERT INTO `chart_of_accounts` (`code`, `name`, `type`, `system_key`, `is_active`, `created_at`, `updated_at`)
SELECT '1200', 'VDS Receivable', 'asset', 'vds_receivable', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `system_key` FROM `chart_of_accounts`) AS `existing_accounts` WHERE `existing_accounts`.`system_key` = 'vds_receivable');

INSERT INTO `chart_of_accounts` (`code`, `name`, `type`, `system_key`, `is_active`, `created_at`, `updated_at`)
SELECT '1300', 'AIT Receivable', 'asset', 'ait_receivable', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `system_key` FROM `chart_of_accounts`) AS `existing_accounts` WHERE `existing_accounts`.`system_key` = 'ait_receivable');

INSERT INTO `chart_of_accounts` (`code`, `name`, `type`, `system_key`, `is_active`, `created_at`, `updated_at`)
SELECT '2000', 'Accounts Payable', 'liability', 'accounts_payable', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `system_key` FROM `chart_of_accounts`) AS `existing_accounts` WHERE `existing_accounts`.`system_key` = 'accounts_payable');

INSERT INTO `chart_of_accounts` (`code`, `name`, `type`, `system_key`, `is_active`, `created_at`, `updated_at`)
SELECT '2100', 'Output VAT Payable', 'liability', 'output_vat_payable', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `system_key` FROM `chart_of_accounts`) AS `existing_accounts` WHERE `existing_accounts`.`system_key` = 'output_vat_payable');

INSERT INTO `chart_of_accounts` (`code`, `name`, `type`, `system_key`, `is_active`, `created_at`, `updated_at`)
SELECT '4000', 'Sales Revenue', 'income', 'sales_revenue', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `system_key` FROM `chart_of_accounts`) AS `existing_accounts` WHERE `existing_accounts`.`system_key` = 'sales_revenue');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 75, 'View accounting', 'accounting.view', 'Accounting', 'Open the accounting screens.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 75 OR `existing_permissions`.`slug` = 'accounting.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 76, 'View chart of accounts', 'chart_of_accounts.view', 'Accounting', 'View account codes, names, types, and bank or MFS details.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 76 OR `existing_permissions`.`slug` = 'chart_of_accounts.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 77, 'Manage chart of accounts', 'chart_of_accounts.manage', 'Accounting', 'Create, rename, and deactivate accounts, including bank and MFS accounts. System accounts stay locked. No bank or MFS account is created until someone with this permission adds it.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 77 OR `existing_permissions`.`slug` = 'chart_of_accounts.manage');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 78, 'View journals', 'journals.view', 'Accounting', 'View draft and posted journals.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 78 OR `existing_permissions`.`slug` = 'journals.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 79, 'Create journals', 'journals.create', 'Accounting', 'Create, edit, and delete a draft journal.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 79 OR `existing_permissions`.`slug` = 'journals.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 80, 'Post journals', 'journals.post', 'Accounting', 'Post a balanced draft. Posting does not change a posted journal.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 80 OR `existing_permissions`.`slug` = 'journals.post');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 81, 'Reverse journals', 'journals.reverse', 'Accounting', 'Add a journal that negates a posted journal.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 81 OR `existing_permissions`.`slug` = 'journals.reverse');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 84, 'View accounting reports', 'reports.view', 'Accounting', 'View the trial balance and the general ledger.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 84 OR `existing_permissions`.`slug` = 'reports.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 85, 'Manage opening balances', 'opening_balances.manage', 'Accounting', 'Enter opening balances and an opening date. Each account is opened once. A bank or MFS line cannot be dated before that account was opened.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 85 OR `existing_permissions`.`slug` = 'opening_balances.manage');

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT `roles`.`id`, `permissions`.`id`
FROM `roles`
JOIN `permissions` ON `permissions`.`slug` IN (
  'accounting.view',
  'chart_of_accounts.view',
  'chart_of_accounts.manage',
  'journals.view',
  'journals.create',
  'journals.post',
  'journals.reverse',
  'reports.view',
  'opening_balances.manage'
)
WHERE `roles`.`slug` IN ('administrator', 'super-administrator', 'accountant')
AND NOT EXISTS (
  SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants`
  WHERE `existing_grants`.`role_id` = `roles`.`id` AND `existing_grants`.`permission_id` = `permissions`.`id`
);

UPDATE `roles`
SET `description` = 'Prepares invoices, Mushak 6.3, VDS, and AIT, maintains parties, and keeps the ledger. Cannot void those documents or approve tax rules.'
WHERE `slug` = 'accountant';

UPDATE `company_profiles`
SET `incorporation_date` = '2025-05-15'
WHERE `incorporation_date` IS NULL;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_21_100001_create_accounting_ledger', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_21_100001_create_accounting_ledger');
