-- Ahmad Solution Ltd. ERP tax-document upgrade.
-- Use this when commercial invoices are already imported and the tax tables are not.
-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.
-- This script creates tax rules, Mushak 6.3, VDS, and AIT tables, adds their permissions, and grants the default role set.
-- It does not delete rows or grants. Importing it again restores a default grant that was removed. It does not remove a custom grant.
-- It does not seed a VAT rate. Output VAT, VDS, and AIT stay in separate tables.
-- It expects users, roles, permissions, permission_role, parties, commercial_invoices, commercial_invoice_lines, and migrations.

SET NAMES utf8mb4;
SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));

CREATE TABLE IF NOT EXISTS `mushak_63_number_sequences` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tax_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `tax_type` varchar(20) NOT NULL,
  `name` varchar(160) NOT NULL,
  `supply_classification` varchar(160) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_rules_code_unique` (`code`),
  KEY `tax_rules_created_by_foreign` (`created_by`),
  KEY `tax_rules_updated_by_foreign` (`updated_by`),
  CONSTRAINT `tax_rules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rules_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tax_rule_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_rule_id` bigint unsigned NOT NULL,
  `version_number` int unsigned NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `rate_percent` decimal(7,4) DEFAULT NULL,
  `fixed_amount` decimal(18,2) DEFAULT NULL,
  `calculation_basis` varchar(40) NOT NULL,
  `rounding_rule` varchar(40) NOT NULL,
  `price_treatment` varchar(20) NOT NULL,
  `threshold_amount` decimal(18,2) DEFAULT NULL,
  `exemption_note` text,
  `legal_instrument` varchar(200) NOT NULL,
  `section_reference` varchar(200) DEFAULT NULL,
  `source_url` varchar(500) DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `legally_effective_on` date DEFAULT NULL,
  `notes` text,
  `approval_status` varchar(20) NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `retired_at` timestamp NULL DEFAULT NULL,
  `retired_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_rule_versions_rule_version_unique` (`tax_rule_id`, `version_number`),
  KEY `tax_rule_versions_rule_status_index` (`tax_rule_id`, `approval_status`),
  KEY `tax_rule_versions_approved_by_foreign` (`approved_by`),
  KEY `tax_rule_versions_created_by_foreign` (`created_by`),
  KEY `tax_rule_versions_retired_by_foreign` (`retired_by`),
  CONSTRAINT `tax_rule_versions_tax_rule_id_foreign` FOREIGN KEY (`tax_rule_id`) REFERENCES `tax_rules` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tax_rule_versions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rule_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rule_versions_retired_by_foreign` FOREIGN KEY (`retired_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_63_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(32) DEFAULT NULL,
  `commercial_invoice_id` bigint unsigned NOT NULL,
  `status` varchar(20) NOT NULL,
  `document_date` date NOT NULL,
  `buyer_source` varchar(20) NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `buyer_legal_name` varchar(200) NOT NULL,
  `buyer_trading_name` varchar(200) DEFAULT NULL,
  `buyer_bin` varchar(30) DEFAULT NULL,
  `buyer_tin` varchar(30) DEFAULT NULL,
  `buyer_phone` varchar(40) DEFAULT NULL,
  `buyer_email` varchar(255) DEFAULT NULL,
  `buyer_address` json DEFAULT NULL,
  `company_snapshot` json DEFAULT NULL,
  `invoice_number_snapshot` varchar(32) DEFAULT NULL,
  `invoice_date_snapshot` date DEFAULT NULL,
  `tax_rule_version_id` bigint unsigned DEFAULT NULL,
  `tax_rule_snapshot` json DEFAULT NULL,
  `review_status` varchar(40) NOT NULL,
  `treatment_confirmed_at` timestamp NULL DEFAULT NULL,
  `treatment_confirmed_by` bigint unsigned DEFAULT NULL,
  `vat_notice` text NOT NULL,
  `form_source_url` varchar(500) NOT NULL,
  `form_sha256` char(64) NOT NULL,
  `base_total` decimal(18,2) NOT NULL,
  `vat_total` decimal(18,2) DEFAULT NULL,
  `inclusive_total` decimal(18,2) DEFAULT NULL,
  `supplementary_duty_total` decimal(18,2) DEFAULT NULL,
  `duties_total` decimal(18,2) DEFAULT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'BDT',
  `customer_notes` text,
  `internal_notes` text,
  `issued_at` timestamp NULL DEFAULT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `open_invoice_id` bigint unsigned GENERATED ALWAYS AS (case when `status` = 'voided' then null else `commercial_invoice_id` end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mushak_63_documents_number_unique` (`number`),
  UNIQUE KEY `mushak_63_documents_open_invoice_unique` (`open_invoice_id`),
  KEY `mushak_63_documents_invoice_status_index` (`commercial_invoice_id`, `status`),
  KEY `mushak_63_documents_party_id_foreign` (`party_id`),
  KEY `mushak_63_documents_tax_rule_version_id_foreign` (`tax_rule_version_id`),
  KEY `mushak_63_documents_treatment_confirmed_by_foreign` (`treatment_confirmed_by`),
  KEY `mushak_63_documents_issued_by_foreign` (`issued_by`),
  KEY `mushak_63_documents_voided_by_foreign` (`voided_by`),
  KEY `mushak_63_documents_created_by_foreign` (`created_by`),
  CONSTRAINT `mushak_63_documents_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_63_documents_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_63_documents_rule_version_foreign` FOREIGN KEY (`tax_rule_version_id`) REFERENCES `tax_rule_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_63_documents_confirmed_by_foreign` FOREIGN KEY (`treatment_confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_63_documents_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_63_documents_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_63_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_63_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mushak_63_document_id` bigint unsigned NOT NULL,
  `commercial_invoice_line_id` bigint unsigned DEFAULT NULL,
  `position` smallint unsigned NOT NULL,
  `description` text NOT NULL,
  `unit` varchar(40) NOT NULL,
  `quantity` decimal(16,4) NOT NULL,
  `unit_price` decimal(16,2) NOT NULL,
  `base_amount` decimal(18,2) NOT NULL,
  `vat_rate_percent` decimal(7,4) DEFAULT NULL,
  `vat_amount` decimal(18,2) DEFAULT NULL,
  `inclusive_amount` decimal(18,2) DEFAULT NULL,
  `supplementary_duty_amount` decimal(18,2) DEFAULT NULL,
  `duties_amount` decimal(18,2) DEFAULT NULL,
  `calculation_driver` varchar(20) NOT NULL,
  `rate_entered_by` bigint unsigned DEFAULT NULL,
  `rate_entered_at` timestamp NULL DEFAULT NULL,
  `superseded_at` timestamp NULL DEFAULT NULL,
  `superseded_by` bigint unsigned DEFAULT NULL,
  `active_position` smallint unsigned GENERATED ALWAYS AS (case when `superseded_at` is null then `position` else null end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mushak_63_lines_current_position_unique` (`mushak_63_document_id`, `active_position`),
  KEY `mushak_63_lines_document_id_foreign` (`mushak_63_document_id`),
  KEY `mushak_63_lines_invoice_line_id_foreign` (`commercial_invoice_line_id`),
  KEY `mushak_63_lines_rate_entered_by_foreign` (`rate_entered_by`),
  KEY `mushak_63_lines_superseded_by_foreign` (`superseded_by`),
  CONSTRAINT `mushak_63_lines_document_id_foreign` FOREIGN KEY (`mushak_63_document_id`) REFERENCES `mushak_63_documents` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_63_lines_invoice_line_foreign` FOREIGN KEY (`commercial_invoice_line_id`) REFERENCES `commercial_invoice_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_63_lines_rate_entered_by_foreign` FOREIGN KEY (`rate_entered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_63_lines_superseded_by_foreign` FOREIGN KEY (`superseded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_63_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mushak_63_document_id` bigint unsigned NOT NULL,
  `event` varchar(40) NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mushak_63_events_document_id_index` (`mushak_63_document_id`, `id`),
  KEY `mushak_63_events_actor_id_foreign` (`actor_id`),
  CONSTRAINT `mushak_63_events_document_id_foreign` FOREIGN KEY (`mushak_63_document_id`) REFERENCES `mushak_63_documents` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_63_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vds_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` varchar(32) NOT NULL,
  `status` varchar(20) NOT NULL,
  `record_date` date NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `counterparty_name` varchar(200) NOT NULL,
  `counterparty_bin` varchar(30) DEFAULT NULL,
  `counterparty_tin` varchar(30) DEFAULT NULL,
  `commercial_invoice_id` bigint unsigned DEFAULT NULL,
  `mushak_63_document_id` bigint unsigned DEFAULT NULL,
  `taxable_basis` decimal(18,2) NOT NULL,
  `tax_amount` decimal(18,2) NOT NULL,
  `certificate_reference` varchar(120) DEFAULT NULL,
  `payment_reference` varchar(120) DEFAULT NULL,
  `tax_rule_version_id` bigint unsigned DEFAULT NULL,
  `tax_rule_snapshot` json DEFAULT NULL,
  `notes` text,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vds_records_party_id_foreign` (`party_id`),
  KEY `vds_records_invoice_id_foreign` (`commercial_invoice_id`),
  KEY `vds_records_mushak_id_foreign` (`mushak_63_document_id`),
  KEY `vds_records_rule_version_foreign` (`tax_rule_version_id`),
  KEY `vds_records_voided_by_foreign` (`voided_by`),
  KEY `vds_records_created_by_foreign` (`created_by`),
  CONSTRAINT `vds_records_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vds_records_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vds_records_mushak_id_foreign` FOREIGN KEY (`mushak_63_document_id`) REFERENCES `mushak_63_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vds_records_rule_version_foreign` FOREIGN KEY (`tax_rule_version_id`) REFERENCES `tax_rule_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vds_records_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vds_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ait_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` varchar(32) NOT NULL,
  `status` varchar(20) NOT NULL,
  `record_date` date NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `counterparty_name` varchar(200) NOT NULL,
  `counterparty_bin` varchar(30) DEFAULT NULL,
  `counterparty_tin` varchar(30) DEFAULT NULL,
  `commercial_invoice_id` bigint unsigned DEFAULT NULL,
  `mushak_63_document_id` bigint unsigned DEFAULT NULL,
  `taxable_basis` decimal(18,2) NOT NULL,
  `tax_amount` decimal(18,2) NOT NULL,
  `certificate_reference` varchar(120) DEFAULT NULL,
  `payment_reference` varchar(120) DEFAULT NULL,
  `tax_rule_version_id` bigint unsigned DEFAULT NULL,
  `tax_rule_snapshot` json DEFAULT NULL,
  `notes` text,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ait_records_party_id_foreign` (`party_id`),
  KEY `ait_records_invoice_id_foreign` (`commercial_invoice_id`),
  KEY `ait_records_mushak_id_foreign` (`mushak_63_document_id`),
  KEY `ait_records_rule_version_foreign` (`tax_rule_version_id`),
  KEY `ait_records_voided_by_foreign` (`voided_by`),
  KEY `ait_records_created_by_foreign` (`created_by`),
  CONSTRAINT `ait_records_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ait_records_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ait_records_mushak_id_foreign` FOREIGN KEY (`mushak_63_document_id`) REFERENCES `mushak_63_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ait_records_rule_version_foreign` FOREIGN KEY (`tax_rule_version_id`) REFERENCES `tax_rule_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ait_records_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ait_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 52, 'View tax rules', 'tax_rules.view', 'Tax rules', 'View effective-dated tax rules. Viewing a rule does not apply its percentage.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 52 OR `existing_permissions`.`slug` = 'tax_rules.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 53, 'Create tax rules', 'tax_rules.create', 'Tax rules', 'Create a tax rule and a draft version. The version is not active until someone else approves it.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 53 OR `existing_permissions`.`slug` = 'tax_rules.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 54, 'Update tax rules', 'tax_rules.update', 'Tax rules', 'Add a new version or close an open approved window. An approved version is not rewritten.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 54 OR `existing_permissions`.`slug` = 'tax_rules.update');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 55, 'Approve tax rules', 'tax_rules.approve', 'Tax rules', 'Approve a tax-rule version prepared by someone else.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 55 OR `existing_permissions`.`slug` = 'tax_rules.approve');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 56, 'Retire tax rules', 'tax_rules.retire', 'Tax rules', 'Retire a tax rule or an approved version. Issued documents keep their snapshot.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 56 OR `existing_permissions`.`slug` = 'tax_rules.retire');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 57, 'View Mushak 6.3', 'mushak.view', 'Mushak', 'View Mushak 6.3 tax invoices linked to commercial invoices this user can see.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 57 OR `existing_permissions`.`slug` = 'mushak.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 58, 'Create Mushak 6.3', 'mushak.create', 'Mushak', 'Prepare a draft Mushak 6.3 from an issued commercial invoice.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 58 OR `existing_permissions`.`slug` = 'mushak.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 59, 'Update Mushak 6.3', 'mushak.update', 'Mushak', 'Edit a draft Mushak 6.3. An issued document cannot be edited.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 59 OR `existing_permissions`.`slug` = 'mushak.update');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 60, 'Issue Mushak 6.3', 'mushak.issue', 'Mushak', 'Issue a Mushak 6.3. Issuing assigns the number and freezes the document.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 60 OR `existing_permissions`.`slug` = 'mushak.issue');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 61, 'Void Mushak 6.3', 'mushak.void', 'Mushak', 'Void a Mushak 6.3. The record is kept. This does not void the commercial invoice.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 61 OR `existing_permissions`.`slug` = 'mushak.void');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 62, 'Download Mushak 6.3 PDFs', 'mushak.pdf', 'Mushak', 'Download a Mushak 6.3 PDF. The file is not stored under public/.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 62 OR `existing_permissions`.`slug` = 'mushak.pdf');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 63, 'View VDS records', 'vds.view', 'VDS', 'View VAT deduction records. A VDS amount is not output VAT.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 63 OR `existing_permissions`.`slug` = 'vds.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 64, 'Create VDS records', 'vds.create', 'VDS', 'Record VDS withheld from the company or deducted by the company. The amount is entered, not calculated from VAT.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 64 OR `existing_permissions`.`slug` = 'vds.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 65, 'Void VDS records', 'vds.void', 'VDS', 'Void a VDS record. The record is kept.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 65 OR `existing_permissions`.`slug` = 'vds.void');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 66, 'View AIT records', 'ait.view', 'AIT', 'View income-tax withholding records. An AIT amount is not output VAT.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 66 OR `existing_permissions`.`slug` = 'ait.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 67, 'Create AIT records', 'ait.create', 'AIT', 'Record income-tax withholding in either business direction. The amount is entered, not calculated from VAT.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 67 OR `existing_permissions`.`slug` = 'ait.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 68, 'Void AIT records', 'ait.void', 'AIT', 'Void an AIT record. The record is kept.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 68 OR `existing_permissions`.`slug` = 'ait.void');

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 52 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 52);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 53 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 53);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 54 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 54);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 55 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 55);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 56 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 56);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 57 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 57);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 58 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 58);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 59 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 59);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 60 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 60);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 61 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 61);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 62 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 62);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 63 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 63);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 64 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 64);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 65 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 65);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 66 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 66);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 67 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 67);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 68 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 68);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 52 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 52);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 53 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 53);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 54 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 54);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 55 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 55);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 56 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 56);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 57 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 57);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 58 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 58);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 59 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 59);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 60 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 60);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 61 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 61);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 62 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 62);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 63 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 63);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 64 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 64);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 65 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 65);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 66 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 66);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 67 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 67);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 68 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 68);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 52 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 52);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 53 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 53);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 54 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 54);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 55 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 55);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 56 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 56);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 57 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 57);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 58 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 58);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 59 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 59);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 60 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 60);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 61 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 61);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 62 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 62);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 63 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 63);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 64 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 64);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 65 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 65);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 66 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 66);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 67 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 67);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 68 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 68);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 52 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 52);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 57 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 57);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 58 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 58);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 59 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 59);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 60 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 60);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 62 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 62);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 63 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 63);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 64 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 64);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 66 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 66);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 67 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 67);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 57 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 57);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 62 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 62);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 57 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 57);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_08_100001_create_tax_tables', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_08_100001_create_tax_tables');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_08_100002_grant_tax_module_access', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_08_100002_grant_tax_module_access');
