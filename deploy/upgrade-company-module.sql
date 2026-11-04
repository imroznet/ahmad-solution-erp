-- Ahmad Solution Ltd. ERP upgrade from the foundation schema.
-- Use this when deploy/mysql-schema.sql was imported before the company tables existed.
-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.
-- This script is safe to run once. Existing rows and grants are not deleted.
-- It expects the foundation tables users, roles, permissions, permission_role, and migrations.

SET NAMES utf8mb4;
SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));

CREATE TABLE IF NOT EXISTS `company_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `singleton_key` varchar(20) NOT NULL DEFAULT 'primary',
  `legal_name` varchar(200) NOT NULL,
  `trading_name` varchar(200) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `rjsc_registration_number` varchar(60) DEFAULT NULL,
  `incorporation_date` date DEFAULT NULL,
  `business_type` varchar(120) DEFAULT NULL,
  `tin` varchar(30) DEFAULT NULL,
  `bin` varchar(30) DEFAULT NULL,
  `trade_licence_number` varchar(60) DEFAULT NULL,
  `trade_licence_issuing_authority` varchar(160) DEFAULT NULL,
  `trade_licence_issued_on` date DEFAULT NULL,
  `trade_licence_expires_on` date DEFAULT NULL,
  `financial_year_starts_on` date NOT NULL,
  `financial_year_ends_on` date NOT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'BDT',
  `website` varchar(200) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `description` text,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_profiles_singleton_key_unique` (`singleton_key`),
  CONSTRAINT `company_profiles_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_profile_id` bigint unsigned NOT NULL,
  `address_type` varchar(40) NOT NULL,
  `line_1` varchar(200) NOT NULL,
  `line_2` varchar(200) DEFAULT NULL,
  `area` varchar(120) DEFAULT NULL,
  `upazila` varchar(120) DEFAULT NULL,
  `district` varchar(120) NOT NULL,
  `division` varchar(120) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `country` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date DEFAULT NULL,
  `effective_until` date DEFAULT NULL,
  `is_default_correspondence` tinyint(1) NOT NULL DEFAULT 0,
  `is_default_billing` tinyint(1) NOT NULL DEFAULT 0,
  `remarks` text,
  `supersedes_address_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_addresses_type_active_index` (`company_profile_id`, `address_type`, `is_active`),
  CONSTRAINT `company_addresses_company_profile_id_foreign` FOREIGN KEY (`company_profile_id`) REFERENCES `company_profiles` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `company_addresses_supersedes_address_id_foreign` FOREIGN KEY (`supersedes_address_id`) REFERENCES `company_addresses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `company_addresses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `company_addresses_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_categories_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_profile_id` bigint unsigned NOT NULL,
  `document_category_id` bigint unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `document_number` varchar(80) DEFAULT NULL,
  `issuing_authority` varchar(160) DEFAULT NULL,
  `issued_on` date DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `verification_status` varchar(20) NOT NULL DEFAULT 'pending',
  `remarks` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_documents_expires_on_index` (`expires_on`),
  KEY `company_documents_verification_status_index` (`verification_status`),
  KEY `company_documents_profile_category_index` (`company_profile_id`, `document_category_id`),
  CONSTRAINT `company_documents_company_profile_id_foreign` FOREIGN KEY (`company_profile_id`) REFERENCES `company_profiles` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `company_documents_document_category_id_foreign` FOREIGN KEY (`document_category_id`) REFERENCES `document_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `company_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `company_documents_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_document_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_document_id` bigint unsigned NOT NULL,
  `version_number` int unsigned NOT NULL,
  `storage_path` varchar(255) NOT NULL,
  `original_filename` varchar(180) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `extension` varchar(8) NOT NULL,
  `size_bytes` bigint unsigned NOT NULL,
  `note` varchar(500) DEFAULT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_document_versions_storage_path_unique` (`storage_path`),
  UNIQUE KEY `company_document_versions_number_unique` (`company_document_id`, `version_number`),
  CONSTRAINT `company_document_versions_company_document_id_foreign` FOREIGN KEY (`company_document_id`) REFERENCES `company_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_document_versions_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 10, 'View company profile', 'company.view', 'Company', 'View the company profile and addresses.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 10 OR `existing_permissions`.`slug` = 'company.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 11, 'Update company profile', 'company.update', 'Company', 'Update the company profile and logo.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 11 OR `existing_permissions`.`slug` = 'company.update');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 12, 'Manage company addresses', 'company.address.manage', 'Company', 'Create, correct, and close company addresses.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 12 OR `existing_permissions`.`slug` = 'company.address.manage');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 13, 'View company documents', 'company.documents.view', 'Company', 'View legal document records and expiry dates.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 13 OR `existing_permissions`.`slug` = 'company.documents.view');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 14, 'Create company documents', 'company.documents.create', 'Company', 'Add legal document records and files.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 14 OR `existing_permissions`.`slug` = 'company.documents.create');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 15, 'Update company documents', 'company.documents.update', 'Company', 'Update legal document records and add file versions.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 15 OR `existing_permissions`.`slug` = 'company.documents.update');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 16, 'Delete company documents', 'company.documents.delete', 'Company', 'Retire a legal document record. Stored file versions are kept.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 16 OR `existing_permissions`.`slug` = 'company.documents.delete');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 17, 'Download company documents', 'company.documents.download', 'Company', 'Download private legal document files.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 17 OR `existing_permissions`.`slug` = 'company.documents.download');

INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)
SELECT 18, 'Manage document categories', 'company.document_categories.manage', 'Company', 'Create, rename, and deactivate document categories.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = 18 OR `existing_permissions`.`slug` = 'company.document_categories.manage');

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 10 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 10);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 11 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 11);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 12 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 12);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 13 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 13);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 14 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 14);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 15 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 15);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 16 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 16);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 17 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 17);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 1, 18 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 1 AND `existing_grants`.`permission_id` = 18);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 10 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 10);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 11 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 11);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 12 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 12);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 13 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 13);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 14 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 14);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 15 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 15);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 16 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 16);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 17 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 17);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 2, 18 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 2 AND `existing_grants`.`permission_id` = 18);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 10 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 10);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 13 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 13);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 3, 17 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 3 AND `existing_grants`.`permission_id` = 17);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 10 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 10);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 13 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 13);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 4, 17 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 4 AND `existing_grants`.`permission_id` = 17);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 5, 10 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 5 AND `existing_grants`.`permission_id` = 10);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 6, 10 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 6 AND `existing_grants`.`permission_id` = 10);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 7, 10 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 7 AND `existing_grants`.`permission_id` = 10);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 7, 12 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 7 AND `existing_grants`.`permission_id` = 12);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 7, 13 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 7 AND `existing_grants`.`permission_id` = 13);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 7, 14 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 7 AND `existing_grants`.`permission_id` = 14);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 7, 15 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 7 AND `existing_grants`.`permission_id` = 15);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 7, 16 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 7 AND `existing_grants`.`permission_id` = 16);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 7, 17 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 7 AND `existing_grants`.`permission_id` = 17);

INSERT INTO `permission_role` (`role_id`, `permission_id`)
SELECT 7, 18 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = 7 AND `existing_grants`.`permission_id` = 18);

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 1, 'Certificate of Incorporation', 'certificate-of-incorporation', 1, 1, 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 1 OR `existing_categories`.`slug` = 'certificate-of-incorporation');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 2, 'Memorandum of Association', 'memorandum-of-association', 1, 1, 2, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 2 OR `existing_categories`.`slug` = 'memorandum-of-association');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 3, 'Articles of Association', 'articles-of-association', 1, 1, 3, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 3 OR `existing_categories`.`slug` = 'articles-of-association');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 4, 'Trade Licence', 'trade-licence', 1, 1, 4, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 4 OR `existing_categories`.`slug` = 'trade-licence');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 5, 'TIN Certificate', 'tin-certificate', 1, 1, 5, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 5 OR `existing_categories`.`slug` = 'tin-certificate');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 6, 'BIN/VAT Registration Certificate', 'bin-vat-registration-certificate', 1, 1, 6, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 6 OR `existing_categories`.`slug` = 'bin-vat-registration-certificate');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 7, 'Bank Solvency Certificate', 'bank-solvency-certificate', 1, 1, 7, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 7 OR `existing_categories`.`slug` = 'bank-solvency-certificate');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 8, 'Bank Account Documents', 'bank-account-documents', 1, 1, 8, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 8 OR `existing_categories`.`slug` = 'bank-account-documents');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 9, 'Board Resolution', 'board-resolution', 1, 1, 9, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 9 OR `existing_categories`.`slug` = 'board-resolution');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 10, 'RJSC Documents', 'rjsc-documents', 1, 1, 10, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 10 OR `existing_categories`.`slug` = 'rjsc-documents');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 11, 'Shareholding Documents', 'shareholding-documents', 1, 1, 11, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 11 OR `existing_categories`.`slug` = 'shareholding-documents');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 12, 'ISO Certificates', 'iso-certificates', 1, 1, 12, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 12 OR `existing_categories`.`slug` = 'iso-certificates');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 13, 'Electrical Contractor Licence', 'electrical-contractor-licence', 1, 1, 13, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 13 OR `existing_categories`.`slug` = 'electrical-contractor-licence');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 14, 'Manufacturer Authorization', 'manufacturer-authorization', 1, 1, 14, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 14 OR `existing_categories`.`slug` = 'manufacturer-authorization');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 15, 'Distributor Authorization', 'distributor-authorization', 1, 1, 15, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 15 OR `existing_categories`.`slug` = 'distributor-authorization');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 16, 'Experience Certificate', 'experience-certificate', 1, 1, 16, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 16 OR `existing_categories`.`slug` = 'experience-certificate');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 17, 'Project Completion Certificate', 'project-completion-certificate', 1, 1, 17, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 17 OR `existing_categories`.`slug` = 'project-completion-certificate');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 18, 'Tax Clearance Certificate', 'tax-clearance-certificate', 1, 1, 18, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 18 OR `existing_categories`.`slug` = 'tax-clearance-certificate');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 19, 'Insurance Certificate', 'insurance-certificate', 1, 1, 19, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 19 OR `existing_categories`.`slug` = 'insurance-certificate');

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 20, 'Custom Documents', 'custom-documents', 1, 1, 20, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = 20 OR `existing_categories`.`slug` = 'custom-documents');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_04_100001_create_company_tables', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_04_100001_create_company_tables');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_04_100002_grant_company_module_access', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_04_100002_grant_company_module_access');
