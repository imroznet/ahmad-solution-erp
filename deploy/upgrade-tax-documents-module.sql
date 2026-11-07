-- Ahmad Solution Ltd. ERP tax-rule, Mushak 6.3, VDS, and AIT upgrade.
-- Use this when delivery challans and commercial invoices are already imported and these tables are not.
-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.
-- This script creates the tax tables, adds their permissions, and grants the default role set.
-- It does not seed a VAT, VDS, or AIT rate. It does not delete rows or customized grants.
-- Importing it again restores a default tax grant that was removed. It does not remove a grant.
-- It expects users, roles, permissions, permission_role, parties, commercial_invoices, commercial_invoice_lines, and migrations.

SET NAMES utf8mb4;
SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));

CREATE TABLE IF NOT EXISTS `tax_rule_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rule_group_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version_number` int unsigned NOT NULL,
  `tax_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supply_classification` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `rate_basis` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate_percent` decimal(9,4) DEFAULT NULL,
  `fixed_amount` decimal(18,2) DEFAULT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BDT',
  `calculation_basis` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rounding_rule` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `threshold_amount` decimal(18,2) DEFAULT NULL,
  `threshold_treatment` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `required_supporting_data` text COLLATE utf8mb4_unicode_ci,
  `exemption_note` text COLLATE utf8mb4_unicode_ci,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `legal_instrument` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section_reference` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `publication_date` date DEFAULT NULL,
  `legal_effective_date` date DEFAULT NULL,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `approval_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supersedes_version_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `retired_by` bigint unsigned DEFAULT NULL,
  `retired_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_rule_versions_group_version_unique` (`rule_group_id`,`version_number`),
  KEY `tax_rule_versions_created_by_foreign` (`created_by`),
  KEY `tax_rule_versions_approved_by_foreign` (`approved_by`),
  KEY `tax_rule_versions_retired_by_foreign` (`retired_by`),
  KEY `tax_rule_versions_scope_index` (`tax_type`,`supply_classification`,`rule_code`,`approval_status`),
  KEY `tax_rule_versions_supersedes_foreign` (`supersedes_version_id`),
  CONSTRAINT `tax_rule_versions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rule_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rule_versions_retired_by_foreign` FOREIGN KEY (`retired_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rule_versions_supersedes_foreign` FOREIGN KEY (`supersedes_version_id`) REFERENCES `tax_rule_versions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `tax_rule_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_rule_version_id` bigint unsigned NOT NULL,
  `event` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_rule_events_actor_id_foreign` (`actor_id`),
  KEY `tax_rule_events_version_index` (`tax_rule_version_id`,`id`),
  CONSTRAINT `tax_rule_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rule_events_tax_rule_version_id_foreign` FOREIGN KEY (`tax_rule_version_id`) REFERENCES `tax_rule_versions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `mushak63_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `commercial_invoice_id` bigint unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supply_classification` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pinned_tax_rule_version_id` bigint unsigned DEFAULT NULL,
  `pinned_rule_snapshot` json DEFAULT NULL,
  `party_legal_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_trading_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `party_tin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `party_bin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `party_vat_registration_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quotation_number` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_number` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `challan_numbers` json DEFAULT NULL,
  `invoice_total` decimal(18,2) NOT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BDT',
  `company_snapshot` json DEFAULT NULL,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `issued_at` timestamp NULL DEFAULT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mushak63_documents_pinned_tax_rule_version_id_foreign` (`pinned_tax_rule_version_id`),
  KEY `mushak63_documents_issued_by_foreign` (`issued_by`),
  KEY `mushak63_documents_voided_by_foreign` (`voided_by`),
  KEY `mushak63_documents_created_by_foreign` (`created_by`),
  KEY `mushak63_documents_invoice_status_index` (`commercial_invoice_id`,`status`),
  CONSTRAINT `mushak63_documents_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak63_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak63_documents_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak63_documents_pinned_tax_rule_version_id_foreign` FOREIGN KEY (`pinned_tax_rule_version_id`) REFERENCES `tax_rule_versions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak63_documents_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `mushak63_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mushak63_document_id` bigint unsigned NOT NULL,
  `commercial_invoice_line_id` bigint unsigned NOT NULL,
  `position` smallint unsigned NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(16,4) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL,
  `line_total` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mushak63_lines_position_unique` (`mushak63_document_id`,`position`),
  KEY `mushak63_lines_commercial_invoice_line_id_foreign` (`commercial_invoice_line_id`),
  CONSTRAINT `mushak63_lines_commercial_invoice_line_id_foreign` FOREIGN KEY (`commercial_invoice_line_id`) REFERENCES `commercial_invoice_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak63_lines_mushak63_document_id_foreign` FOREIGN KEY (`mushak63_document_id`) REFERENCES `mushak63_documents` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `mushak63_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mushak63_document_id` bigint unsigned NOT NULL,
  `event` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mushak63_events_actor_id_foreign` (`actor_id`),
  KEY `mushak63_events_document_index` (`mushak63_document_id`,`id`),
  CONSTRAINT `mushak63_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak63_events_mushak63_document_id_foreign` FOREIGN KEY (`mushak63_document_id`) REFERENCES `mushak63_documents` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `vds_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_id` bigint unsigned NOT NULL,
  `commercial_invoice_id` bigint unsigned DEFAULT NULL,
  `underlying_reference` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supply_classification` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `basis_amount` decimal(18,2) NOT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BDT',
  `record_date` date NOT NULL,
  `pinned_tax_rule_version_id` bigint unsigned DEFAULT NULL,
  `pinned_rule_snapshot` json DEFAULT NULL,
  `certificate_reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `treasury_reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `party_legal_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_tin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `party_bin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_snapshot` json DEFAULT NULL,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vds_records_party_id_foreign` (`party_id`),
  KEY `vds_records_commercial_invoice_id_foreign` (`commercial_invoice_id`),
  KEY `vds_records_pinned_tax_rule_version_id_foreign` (`pinned_tax_rule_version_id`),
  KEY `vds_records_voided_by_foreign` (`voided_by`),
  KEY `vds_records_created_by_foreign` (`created_by`),
  KEY `vds_records_direction_status_index` (`direction`,`status`),
  CONSTRAINT `vds_records_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vds_records_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_pinned_tax_rule_version_id_foreign` FOREIGN KEY (`pinned_tax_rule_version_id`) REFERENCES `tax_rule_versions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `vds_record_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vds_record_id` bigint unsigned NOT NULL,
  `event` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vds_record_events_vds_record_id_foreign` (`vds_record_id`),
  KEY `vds_record_events_actor_id_foreign` (`actor_id`),
  CONSTRAINT `vds_record_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vds_record_events_vds_record_id_foreign` FOREIGN KEY (`vds_record_id`) REFERENCES `vds_records` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `ait_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_id` bigint unsigned NOT NULL,
  `commercial_invoice_id` bigint unsigned DEFAULT NULL,
  `underlying_reference` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supply_classification` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `basis_amount` decimal(18,2) NOT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BDT',
  `record_date` date NOT NULL,
  `pinned_tax_rule_version_id` bigint unsigned DEFAULT NULL,
  `pinned_rule_snapshot` json DEFAULT NULL,
  `certificate_reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `treasury_reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `party_legal_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_tin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `party_bin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_snapshot` json DEFAULT NULL,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ait_records_party_id_foreign` (`party_id`),
  KEY `ait_records_commercial_invoice_id_foreign` (`commercial_invoice_id`),
  KEY `ait_records_pinned_tax_rule_version_id_foreign` (`pinned_tax_rule_version_id`),
  KEY `ait_records_voided_by_foreign` (`voided_by`),
  KEY `ait_records_created_by_foreign` (`created_by`),
  KEY `ait_records_direction_status_index` (`direction`,`status`),
  CONSTRAINT `ait_records_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ait_records_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_pinned_tax_rule_version_id_foreign` FOREIGN KEY (`pinned_tax_rule_version_id`) REFERENCES `tax_rule_versions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `ait_record_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ait_record_id` bigint unsigned NOT NULL,
  `event` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ait_record_events_ait_record_id_foreign` (`ait_record_id`),
  KEY `ait_record_events_actor_id_foreign` (`actor_id`),
  CONSTRAINT `ait_record_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ait_record_events_ait_record_id_foreign` FOREIGN KEY (`ait_record_id`) REFERENCES `ait_records` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 52, 'View tax rules', 'tax_rules.view', 'Tax rules', 'View effective-dated output VAT, VDS, and AIT rules and the reason a rule was selected.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 52 OR `existing_permissions`.`slug` = 'tax_rules.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 53, 'Manage tax rules', 'tax_rules.manage', 'Tax rules', 'Create and edit a draft tax rule, and open an amendment. This does not approve the rule.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 53 OR `existing_permissions`.`slug` = 'tax_rules.manage');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 54, 'Approve tax rules', 'tax_rules.approve', 'Tax rules', 'Approve a draft tax rule prepared by someone else. Approval does not issue a tax invoice.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 54 OR `existing_permissions`.`slug` = 'tax_rules.approve');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 55, 'Retire tax rules', 'tax_rules.retire', 'Tax rules', 'Retire an approved tax rule. The version and any pinned snapshot are kept.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 55 OR `existing_permissions`.`slug` = 'tax_rules.retire');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 56, 'View Mushak working papers', 'mushak.view', 'Mushak', 'View Mushak 6.3 working papers linked to commercial invoices.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 56 OR `existing_permissions`.`slug` = 'mushak.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 57, 'Prepare Mushak working papers', 'mushak.create', 'Mushak', 'Prepare a Mushak 6.3 working paper from an issued commercial invoice. This does not issue a tax invoice.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 57 OR `existing_permissions`.`slug` = 'mushak.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 58, 'Update Mushak working papers', 'mushak.update', 'Mushak', 'Edit a draft Mushak 6.3 working paper.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 58 OR `existing_permissions`.`slug` = 'mushak.update');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 59, 'Issue Mushak 6.3', 'mushak.issue', 'Mushak', 'Attempt to issue Mushak 6.3. Issue stays blocked until the legal gate in code is opened.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 59 OR `existing_permissions`.`slug` = 'mushak.issue');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 60, 'Void Mushak working papers', 'mushak.void', 'Mushak', 'Void a draft Mushak 6.3 working paper. The commercial invoice is not voided.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 60 OR `existing_permissions`.`slug` = 'mushak.void');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 61, 'Download Mushak working papers', 'mushak.pdf', 'Mushak', 'Download a private Mushak working-paper PDF. It is not an issued tax invoice.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 61 OR `existing_permissions`.`slug` = 'mushak.pdf');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 62, 'View VDS records', 'vds.view', 'VDS', 'View VAT deduction records. VDS is not output VAT.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 62 OR `existing_permissions`.`slug` = 'vds.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 63, 'Manage VDS records', 'vds.manage', 'VDS', 'Prepare and edit a VDS record. This does not issue a certificate.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 63 OR `existing_permissions`.`slug` = 'vds.manage');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 64, 'Download VDS working papers', 'vds.pdf', 'VDS', 'Download a private VDS working-paper PDF. It is not a certificate.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 64 OR `existing_permissions`.`slug` = 'vds.pdf');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 65, 'View AIT records', 'ait.view', 'AIT', 'View income-tax withholding records. AIT is not VAT or VDS.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 65 OR `existing_permissions`.`slug` = 'ait.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 66, 'Manage AIT records', 'ait.manage', 'AIT', 'Prepare and edit an AIT record. This does not issue a certificate.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 66 OR `existing_permissions`.`slug` = 'ait.manage');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 67, 'Download AIT working papers', 'ait.pdf', 'AIT', 'Download a private AIT working-paper PDF. It is not a certificate.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 67 OR `existing_permissions`.`slug` = 'ait.pdf');

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
SELECT 2, 52 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 52);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 53 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 53);

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
SELECT 4, 52 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 52);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 56 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 56);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 57 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 57);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 58 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 58);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 61 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 61);

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
SELECT 4, 65 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 65);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 66 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 66);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 67 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 67);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_08_100001_create_tax_document_tables', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_08_100001_create_tax_document_tables');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_08_100002_grant_tax_document_access', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_08_100002_grant_tax_document_access');
