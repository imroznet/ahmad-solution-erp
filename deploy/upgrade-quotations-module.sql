-- Ahmad Solution Ltd. ERP quotations upgrade.
-- Use this when the party tables are already imported and the quotation tables are not.
-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.
-- This script creates the quotation tables, adds the quotation permissions, and grants the default role set.
-- It does not delete rows, grants, or customized permissions. It is safe to import again.
-- It expects users, roles, permissions, permission_role, parties, and migrations.

SET NAMES utf8mb4;
SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));

CREATE TABLE IF NOT EXISTS `quotation_number_sequences` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(32) NOT NULL,
  `party_id` bigint unsigned NOT NULL,
  `party_role` varchar(20) NOT NULL,
  `current_revision_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotations_number_unique` (`number`),
  KEY `quotations_party_id_foreign` (`party_id`),
  KEY `quotations_created_by_foreign` (`created_by`),
  KEY `quotations_current_revision_id_index` (`current_revision_id`),
  KEY `quotations_status_id_index` (`status`, `id`),
  KEY `quotations_created_at_index` (`created_at`),
  CONSTRAINT `quotations_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `quotations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotation_revisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` bigint unsigned NOT NULL,
  `revision_number` int unsigned NOT NULL,
  `status` varchar(20) NOT NULL,
  `quotation_date` date NOT NULL,
  `valid_until` date NOT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'BDT',
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
  `terms` text,
  `customer_notes` text,
  `internal_notes` text,
  `tax_notice` text NOT NULL,
  `tax_treatment` varchar(40) NOT NULL,
  `subtotal` decimal(18,2) NOT NULL,
  `total` decimal(18,2) NOT NULL,
  `cancel_reason` text,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `expired_by` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_revisions_quotation_revision_unique` (`quotation_id`, `revision_number`),
  KEY `quotation_revisions_quotation_id_foreign` (`quotation_id`),
  KEY `quotation_revisions_cancelled_by_foreign` (`cancelled_by`),
  KEY `quotation_revisions_expired_by_foreign` (`expired_by`),
  KEY `quotation_revisions_created_by_foreign` (`created_by`),
  KEY `quotation_revisions_quotation_date_index` (`quotation_date`),
  KEY `quotation_revisions_quotation_status_index` (`quotation_id`, `status`),
  CONSTRAINT `quotation_revisions_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `quotation_revisions_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotation_revisions_expired_by_foreign` FOREIGN KEY (`expired_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotation_revisions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotation_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_revision_id` bigint unsigned NOT NULL,
  `position` smallint unsigned NOT NULL,
  `description` text NOT NULL,
  `unit` varchar(40) NOT NULL,
  `quantity` decimal(16,4) NOT NULL,
  `unit_price` decimal(16,2) NOT NULL,
  `line_total` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_lines_revision_position_unique` (`quotation_revision_id`, `position`),
  KEY `quotation_lines_quotation_revision_id_foreign` (`quotation_revision_id`),
  CONSTRAINT `quotation_lines_quotation_revision_id_foreign` FOREIGN KEY (`quotation_revision_id`) REFERENCES `quotation_revisions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotation_approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_revision_id` bigint unsigned NOT NULL,
  `action` varchar(20) NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotation_approvals_quotation_revision_id_foreign` (`quotation_revision_id`),
  KEY `quotation_approvals_user_id_foreign` (`user_id`),
  KEY `quotation_approvals_revision_id_index` (`quotation_revision_id`, `id`),
  CONSTRAINT `quotation_approvals_quotation_revision_id_foreign` FOREIGN KEY (`quotation_revision_id`) REFERENCES `quotation_revisions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `quotation_approvals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotation_sends` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_revision_id` bigint unsigned NOT NULL,
  `sent_by` bigint unsigned DEFAULT NULL,
  `recipient_label` varchar(200) DEFAULT NULL,
  `delivery_status` varchar(40) NOT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotation_sends_quotation_revision_id_foreign` (`quotation_revision_id`),
  KEY `quotation_sends_sent_by_foreign` (`sent_by`),
  CONSTRAINT `quotation_sends_quotation_revision_id_foreign` FOREIGN KEY (`quotation_revision_id`) REFERENCES `quotation_revisions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `quotation_sends_sent_by_foreign` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotation_share_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_revision_id` bigint unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_share_tokens_token_hash_unique` (`token_hash`),
  KEY `quotation_share_tokens_quotation_revision_id_foreign` (`quotation_revision_id`),
  KEY `quotation_share_tokens_created_by_foreign` (`created_by`),
  KEY `quotation_share_tokens_revision_revoked_index` (`quotation_revision_id`, `revoked_at`),
  CONSTRAINT `quotation_share_tokens_quotation_revision_id_foreign` FOREIGN KEY (`quotation_revision_id`) REFERENCES `quotation_revisions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `quotation_share_tokens_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotation_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_revision_id` bigint unsigned NOT NULL,
  `decision` varchar(20) NOT NULL,
  `source` varchar(20) NOT NULL,
  `respondent_name` varchar(160) NOT NULL,
  `respondent_designation` varchar(120) DEFAULT NULL,
  `remarks` text,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `quotation_share_token_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_responses_revision_unique` (`quotation_revision_id`),
  KEY `quotation_responses_quotation_revision_id_foreign` (`quotation_revision_id`),
  KEY `quotation_responses_recorded_by_foreign` (`recorded_by`),
  KEY `quotation_responses_quotation_share_token_id_foreign` (`quotation_share_token_id`),
  CONSTRAINT `quotation_responses_quotation_revision_id_foreign` FOREIGN KEY (`quotation_revision_id`) REFERENCES `quotation_revisions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `quotation_responses_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotation_responses_quotation_share_token_id_foreign` FOREIGN KEY (`quotation_share_token_id`) REFERENCES `quotation_share_tokens` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotation_share_access_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_share_token_id` bigint unsigned DEFAULT NULL,
  `event` varchar(40) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotation_share_access_logs_token_foreign` (`quotation_share_token_id`),
  KEY `quotation_share_access_logs_token_index` (`quotation_share_token_id`, `id`),
  CONSTRAINT `quotation_share_access_logs_token_foreign` FOREIGN KEY (`quotation_share_token_id`) REFERENCES `quotation_share_tokens` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 29, 'View quotations', 'quotations.view', 'Quotations', 'View quotations for customers this user is allowed to see.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 29 OR `existing_permissions`.`slug` = 'quotations.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 30, 'Create quotations', 'quotations.create', 'Quotations', 'Create a draft quotation for an active customer.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 30 OR `existing_permissions`.`slug` = 'quotations.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 31, 'Update quotations', 'quotations.update', 'Quotations', 'Edit a draft and open a new revision of an issued quotation.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 31 OR `existing_permissions`.`slug` = 'quotations.update');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 32, 'Submit quotations', 'quotations.submit', 'Quotations', 'Submit a draft quotation for approval.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 32 OR `existing_permissions`.`slug` = 'quotations.submit');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 33, 'Approve quotations', 'quotations.approve', 'Quotations', 'Approve or return a submitted quotation prepared by someone else.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 33 OR `existing_permissions`.`slug` = 'quotations.approve');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 34, 'Send quotations', 'quotations.send', 'Quotations', 'Record that an approved quotation was sent. This does not send email.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 34 OR `existing_permissions`.`slug` = 'quotations.send');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 35, 'Cancel quotations', 'quotations.cancel', 'Quotations', 'Cancel a quotation. The record is kept.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 35 OR `existing_permissions`.`slug` = 'quotations.cancel');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 36, 'Share quotations', 'quotations.share', 'Quotations', 'Create or revoke a customer share link. Only the token hash is stored.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 36 OR `existing_permissions`.`slug` = 'quotations.share');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 37, 'Record quotation responses', 'quotations.respond', 'Quotations', 'Record a customer acceptance or rejection against the sent revision.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 37 OR `existing_permissions`.`slug` = 'quotations.respond');

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 29 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 29);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 30 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 30);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 31 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 31);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 32 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 32);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 33 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 33);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 34 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 34);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 35 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 35);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 36 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 36);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 37 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 37);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 29 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 29);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 30 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 30);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 31 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 31);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 32 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 32);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 33 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 33);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 34 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 34);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 35 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 35);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 36 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 36);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 37 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 37);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 29 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 29);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 33 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 33);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 29 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 29);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 29 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 29);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 30 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 30);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 31 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 31);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 32 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 32);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 33 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 33);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 34 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 34);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 35 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 35);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 36 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 36);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 37 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 37);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 29 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 29);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 30 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 30);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 31 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 31);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 32 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 32);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 34 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 34);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 36 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 36);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 37 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 37);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_06_100001_create_quotation_tables', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_06_100001_create_quotation_tables');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_06_100002_grant_quotation_module_access', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_06_100002_grant_quotation_module_access');
