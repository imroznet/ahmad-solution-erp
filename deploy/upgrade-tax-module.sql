-- Ahmad Solution Ltd. ERP tax-rule, Mushak 6.3, VDS, and AIT upgrade.
-- Use this when the commercial invoice tables are already imported and these tax tables are not.
-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.
-- This script creates the tax tables, adds their permissions, and grants the default role set.
-- It does not insert a tax rate. VAT on Mushak 6.3 is typed on the document.
-- It does not delete rows, grants, or customized permissions. It is safe to import again.
-- Importing it again restores a default grant that was removed. It does not remove a grant.
-- It expects users, roles, permissions, permission_role, parties, quotations, commercial invoices, and migrations.

SET NAMES utf8mb4;
SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));

CREATE TABLE IF NOT EXISTS `tax_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_type` varchar(32) NOT NULL,
  `rule_key` varchar(80) NOT NULL,
  `version` int unsigned NOT NULL,
  `supersedes_rule_id` bigint unsigned DEFAULT NULL,
  `supply_classification` varchar(120) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `rate` decimal(9,4) DEFAULT NULL,
  `rate_kind` varchar(32) DEFAULT NULL,
  `calculation_basis` varchar(80) DEFAULT NULL,
  `rounding_rule` varchar(80) DEFAULT NULL,
  `price_treatment` varchar(32) DEFAULT NULL,
  `threshold_amount` decimal(18,2) DEFAULT NULL,
  `exemption_note` text DEFAULT NULL,
  `required_supporting_data` text DEFAULT NULL,
  `legal_instrument` varchar(200) NOT NULL,
  `section_reference` varchar(200) DEFAULT NULL,
  `source_url` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `status` varchar(32) NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `retired_by` bigint unsigned DEFAULT NULL,
  `retired_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_rules_key_version_unique` (`rule_key`, `version`),
  KEY `tax_rules_type_status_from_index` (`tax_type`, `status`, `effective_from`),
  KEY `tax_rules_supersedes_rule_id_foreign` (`supersedes_rule_id`),
  KEY `tax_rules_created_by_foreign` (`created_by`),
  KEY `tax_rules_updated_by_foreign` (`updated_by`),
  KEY `tax_rules_approved_by_foreign` (`approved_by`),
  KEY `tax_rules_retired_by_foreign` (`retired_by`),
  CONSTRAINT `tax_rules_supersedes_rule_id_foreign` FOREIGN KEY (`supersedes_rule_id`) REFERENCES `tax_rules` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tax_rules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rules_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rules_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rules_retired_by_foreign` FOREIGN KEY (`retired_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tax_rule_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_rule_id` bigint unsigned NOT NULL,
  `event` varchar(40) NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_rule_events_rule_id_index` (`tax_rule_id`, `id`),
  KEY `tax_rule_events_actor_id_foreign` (`actor_id`),
  CONSTRAINT `tax_rule_events_tax_rule_id_foreign` FOREIGN KEY (`tax_rule_id`) REFERENCES `tax_rules` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tax_rule_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_number_sequences` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `commercial_invoice_id` bigint unsigned DEFAULT NULL,
  `quotation_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `number` varchar(32) DEFAULT NULL,
  `buyer_source` varchar(20) NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `buyer_name` varchar(200) DEFAULT NULL,
  `buyer_bin` varchar(30) DEFAULT NULL,
  `buyer_tin` varchar(30) DEFAULT NULL,
  `buyer_address` text DEFAULT NULL,
  `buyer_phone` varchar(40) DEFAULT NULL,
  `buyer_email` varchar(255) DEFAULT NULL,
  `destination` varchar(300) DEFAULT NULL,
  `responsible_name` varchar(200) DEFAULT NULL,
  `responsible_designation` varchar(120) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `issue_time` char(5) DEFAULT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'BDT',
  `total_value` decimal(18,2) DEFAULT NULL,
  `total_supplementary_duty` decimal(18,2) DEFAULT NULL,
  `total_vat` decimal(18,2) DEFAULT NULL,
  `total_inclusive` decimal(18,2) DEFAULT NULL,
  `tax_review_status` varchar(40) NOT NULL DEFAULT 'review_required',
  `rate_determined_by_erp` tinyint(1) NOT NULL DEFAULT 0,
  `treatment_rule_id` bigint unsigned DEFAULT NULL,
  `treatment_rule_snapshot` json DEFAULT NULL,
  `treatment_note` text DEFAULT NULL,
  `treatment_recorded_by` bigint unsigned DEFAULT NULL,
  `treatment_recorded_at` timestamp NULL DEFAULT NULL,
  `company_snapshot` json DEFAULT NULL,
  `customer_snapshot` json DEFAULT NULL,
  `source_references` json DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `manual_vat_notice` text NOT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mushak_documents_number_unique` (`number`),
  KEY `mushak_documents_status_id_index` (`status`, `id`),
  KEY `mushak_documents_commercial_invoice_id_foreign` (`commercial_invoice_id`),
  KEY `mushak_documents_quotation_id_foreign` (`quotation_id`),
  KEY `mushak_documents_party_id_foreign` (`party_id`),
  KEY `mushak_documents_treatment_rule_id_foreign` (`treatment_rule_id`),
  CONSTRAINT `mushak_documents_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_documents_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_documents_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_documents_treatment_rule_id_foreign` FOREIGN KEY (`treatment_rule_id`) REFERENCES `tax_rules` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_documents_treatment_recorded_by_foreign` FOREIGN KEY (`treatment_recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_documents_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_documents_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_documents_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_documents_manual_rate` CHECK (`rate_determined_by_erp` = 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_document_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mushak_document_id` bigint unsigned NOT NULL,
  `commercial_invoice_line_id` bigint unsigned DEFAULT NULL,
  `quotation_line_id` bigint unsigned DEFAULT NULL,
  `delivery_challan_line_id` bigint unsigned DEFAULT NULL,
  `position` smallint unsigned NOT NULL,
  `description` text NOT NULL,
  `unit` varchar(40) DEFAULT NULL,
  `quantity` decimal(16,4) DEFAULT NULL,
  `unit_price` decimal(16,2) DEFAULT NULL,
  `line_value` decimal(18,2) DEFAULT NULL,
  `supplementary_duty` decimal(18,2) DEFAULT NULL,
  `vat_rate` decimal(9,4) DEFAULT NULL,
  `specific_tax_note` varchar(120) DEFAULT NULL,
  `vat_amount` decimal(18,2) DEFAULT NULL,
  `inclusive_value` decimal(18,2) DEFAULT NULL,
  `vat_rate_entered_by` bigint unsigned DEFAULT NULL,
  `vat_rate_entered_at` timestamp NULL DEFAULT NULL,
  `superseded_at` timestamp NULL DEFAULT NULL,
  `superseded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `active_position` smallint unsigned GENERATED ALWAYS AS (case when `superseded_at` is null then `position` else null end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mushak_lines_current_position_unique` (`mushak_document_id`, `active_position`),
  CONSTRAINT `mushak_document_lines_document_id_foreign` FOREIGN KEY (`mushak_document_id`) REFERENCES `mushak_documents` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_document_lines_invoice_line_id_foreign` FOREIGN KEY (`commercial_invoice_line_id`) REFERENCES `commercial_invoice_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_document_lines_quotation_line_id_foreign` FOREIGN KEY (`quotation_line_id`) REFERENCES `quotation_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_document_lines_challan_line_id_foreign` FOREIGN KEY (`delivery_challan_line_id`) REFERENCES `delivery_challan_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_document_lines_rate_entered_by_foreign` FOREIGN KEY (`vat_rate_entered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_document_lines_superseded_by_foreign` FOREIGN KEY (`superseded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_document_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mushak_document_id` bigint unsigned NOT NULL,
  `event` varchar(40) NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mushak_document_events_document_id_index` (`mushak_document_id`, `id`),
  CONSTRAINT `mushak_document_events_document_id_foreign` FOREIGN KEY (`mushak_document_id`) REFERENCES `mushak_documents` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_document_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vds_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` varchar(32) NOT NULL,
  `status` varchar(20) NOT NULL,
  `counterparty_source` varchar(20) NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `counterparty_name` varchar(200) NOT NULL,
  `counterparty_bin` varchar(30) DEFAULT NULL,
  `counterparty_tin` varchar(30) DEFAULT NULL,
  `mushak_document_id` bigint unsigned DEFAULT NULL,
  `commercial_invoice_id` bigint unsigned DEFAULT NULL,
  `underlying_reference` varchar(200) NOT NULL,
  `basis_amount` decimal(18,2) DEFAULT NULL,
  `deducted_amount` decimal(18,2) NOT NULL,
  `record_date` date NOT NULL,
  `certificate_number` varchar(80) DEFAULT NULL,
  `treasury_reference` varchar(160) DEFAULT NULL,
  `tax_rule_id` bigint unsigned DEFAULT NULL,
  `rule_snapshot` json DEFAULT NULL,
  `tax_review_status` varchar(40) NOT NULL DEFAULT 'review_required',
  `notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vds_records_direction_status_index` (`direction`, `status`),
  CONSTRAINT `vds_records_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_mushak_document_id_foreign` FOREIGN KEY (`mushak_document_id`) REFERENCES `mushak_documents` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_tax_rule_id_foreign` FOREIGN KEY (`tax_rule_id`) REFERENCES `tax_rules` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vds_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vds_records_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ait_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` varchar(32) NOT NULL,
  `status` varchar(20) NOT NULL,
  `counterparty_source` varchar(20) NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `counterparty_name` varchar(200) NOT NULL,
  `counterparty_bin` varchar(30) DEFAULT NULL,
  `counterparty_tin` varchar(30) DEFAULT NULL,
  `mushak_document_id` bigint unsigned DEFAULT NULL,
  `commercial_invoice_id` bigint unsigned DEFAULT NULL,
  `underlying_reference` varchar(200) NOT NULL,
  `basis_amount` decimal(18,2) DEFAULT NULL,
  `deducted_amount` decimal(18,2) NOT NULL,
  `record_date` date NOT NULL,
  `certificate_number` varchar(80) DEFAULT NULL,
  `treasury_reference` varchar(160) DEFAULT NULL,
  `tax_rule_id` bigint unsigned DEFAULT NULL,
  `rule_snapshot` json DEFAULT NULL,
  `tax_review_status` varchar(40) NOT NULL DEFAULT 'review_required',
  `notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ait_records_direction_status_index` (`direction`, `status`),
  CONSTRAINT `ait_records_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_mushak_document_id_foreign` FOREIGN KEY (`mushak_document_id`) REFERENCES `mushak_documents` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_tax_rule_id_foreign` FOREIGN KEY (`tax_rule_id`) REFERENCES `tax_rules` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ait_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ait_records_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 52, 'View tax rules', 'tax_rules.view', 'Tax rules', 'View effective-dated tax rules. Viewing a rule does not apply its rate.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 52 OR `existing_permissions`.`slug` = 'tax_rules.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 53, 'Manage tax rules', 'tax_rules.manage', 'Tax rules', 'Create and edit a draft tax rule, and open an amendment. A draft is not applied.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 53 OR `existing_permissions`.`slug` = 'tax_rules.manage');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 54, 'Approve tax rules', 'tax_rules.approve', 'Tax rules', 'Approve or retire a tax rule. Approval does not calculate VAT on a document.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 54 OR `existing_permissions`.`slug` = 'tax_rules.approve');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 55, 'View Mushak 6.3', 'mushak.view', 'Mushak', 'View Mushak 6.3 tax invoices.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 55 OR `existing_permissions`.`slug` = 'mushak.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 56, 'Prepare Mushak 6.3', 'mushak.create', 'Mushak', 'Prepare a draft Mushak 6.3. VAT figures are typed, not calculated.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 56 OR `existing_permissions`.`slug` = 'mushak.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 57, 'Issue Mushak 6.3', 'mushak.issue', 'Mushak', 'Issue a Mushak 6.3. Issuing freezes the typed figures and the company snapshot.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 57 OR `existing_permissions`.`slug` = 'mushak.issue');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 58, 'Void Mushak 6.3', 'mushak.void', 'Mushak', 'Void a Mushak 6.3. The record is kept. This does not create a statutory credit note.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 58 OR `existing_permissions`.`slug` = 'mushak.void');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 59, 'Download Mushak 6.3 PDFs', 'mushak.pdf', 'Mushak', 'Download a Mushak 6.3 PDF. The file is not stored under public/.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 59 OR `existing_permissions`.`slug` = 'mushak.pdf');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 60, 'Record Mushak treatment', 'mushak.record_treatment', 'Mushak', 'Record that a person confirmed the tax treatment. The typed VAT figures stay as entered.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 60 OR `existing_permissions`.`slug` = 'mushak.record_treatment');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 61, 'View VDS records', 'vds.view', 'VDS', 'View VAT deduction records. They are not output VAT.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 61 OR `existing_permissions`.`slug` = 'vds.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 62, 'Record VDS', 'vds.manage', 'VDS', 'Record a VDS amount that a person typed. It is not calculated and not added to VAT.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 62 OR `existing_permissions`.`slug` = 'vds.manage');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 63, 'Void VDS records', 'vds.void', 'VDS', 'Void a VDS record. The row is kept.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 63 OR `existing_permissions`.`slug` = 'vds.void');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 64, 'View AIT records', 'ait.view', 'AIT', 'View income-tax withholding records. They are not VAT.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 64 OR `existing_permissions`.`slug` = 'ait.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 65, 'Record AIT', 'ait.manage', 'AIT', 'Record an income-tax withholding amount that a person typed.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 65 OR `existing_permissions`.`slug` = 'ait.manage');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 66, 'Void AIT records', 'ait.void', 'AIT', 'Void an income-tax withholding record. The row is kept.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 66 OR `existing_permissions`.`slug` = 'ait.void');

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
SELECT 4, 52 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 52);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 55 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 55);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 56 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 56);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 57 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 57);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 59 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 59);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 60 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 60);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 61 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 61);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 62 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 62);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 64 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 64);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 65 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 65);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 55 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 55);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 59 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 59);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 55 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 55);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 59 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 59);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_08_100001_create_tax_tables', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_08_100001_create_tax_tables');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_08_100002_grant_tax_module_access', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_08_100002_grant_tax_module_access');
