<?php

namespace App\Company;

use App\Authorization\AccessSqlExporter;
use App\Authorization\PermissionCatalog;

/**
 * phpMyAdmin scripts for this module.
 * Clean installs use the category file after the access-control file.
 * An existing foundation database uses the upgrade file, which creates the
 * tables and adds the new permissions without deleting current grants.
 */
final class CompanyModuleSql
{
    public static function categories(): string
    {
        $rows = [];

        foreach (DocumentCategoryCatalog::categories() as $category) {
            $rows[] = sprintf(
                '(%d, %s, %s, 1, 1, %d, NOW(), NOW())',
                $category['id'],
                AccessSqlExporter::sqlLiteral($category['name']),
                AccessSqlExporter::sqlLiteral($category['slug']),
                $category['sort_order'],
            );
        }

        return implode("\n", [
            '-- Ahmad Solution Ltd. ERP document categories',
            '-- No company profile, certificates, or user records.',
            '-- Import once, after deploy/reference-data.sql, on a clean database.',
            '-- If these primary keys already exist, stop and review. Do not force the import.',
            '',
            'SET NAMES utf8mb4;',
            '',
            'INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES',
            implode(",\n", $rows).';',
            '',
        ]);
    }

    public static function upgrade(): string
    {
        $lines = [
            '-- Ahmad Solution Ltd. ERP upgrade from the foundation schema.',
            '-- Use this when deploy/mysql-schema.sql was imported before the company tables existed.',
            '-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.',
            '-- This script is safe to run once. Existing rows and grants are not deleted.',
            '-- It expects the foundation tables users, roles, permissions, permission_role, and migrations.',
            '',
            'SET NAMES utf8mb4;',
            "SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));",
            '',
            self::ddl(),
            '',
        ];

        foreach (PermissionCatalog::permissions() as $permission) {
            if ($permission['group'] !== 'Company') {
                continue;
            }

            $lines[] = 'INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)';
            $lines[] = sprintf(
                'SELECT %d, %s, %s, %s, %s, NOW(), NOW() FROM DUAL',
                $permission['id'],
                AccessSqlExporter::sqlLiteral($permission['name']),
                AccessSqlExporter::sqlLiteral($permission['slug']),
                AccessSqlExporter::sqlLiteral($permission['group']),
                AccessSqlExporter::sqlLiteral($permission['description']),
            );
            $lines[] = sprintf(
                'WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = %d OR `existing_permissions`.`slug` = %s);',
                $permission['id'],
                AccessSqlExporter::sqlLiteral($permission['slug']),
            );
            $lines[] = '';
        }

        $companyIds = [];

        foreach (PermissionCatalog::permissions() as $permission) {
            if ($permission['group'] === 'Company') {
                $companyIds[] = $permission['id'];
            }
        }

        foreach (PermissionCatalog::roles() as $role) {
            $grants = array_values(array_intersect(
                PermissionCatalog::permissionIdsForRole($role['slug']),
                $companyIds,
            ));

            foreach ($grants as $permissionId) {
                $lines[] = 'INSERT INTO `permission_role` (`role_id`, `permission_id`)';
                $lines[] = sprintf('SELECT %d, %d FROM DUAL', $role['id'], $permissionId);
                $lines[] = sprintf(
                    'WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = %d AND `existing_grants`.`permission_id` = %d);',
                    $role['id'],
                    $permissionId,
                );
                $lines[] = '';
            }
        }

        foreach (DocumentCategoryCatalog::categories() as $category) {
            $lines[] = 'INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)';
            $lines[] = sprintf(
                'SELECT %d, %s, %s, 1, 1, %d, NOW(), NOW() FROM DUAL',
                $category['id'],
                AccessSqlExporter::sqlLiteral($category['name']),
                AccessSqlExporter::sqlLiteral($category['slug']),
                $category['sort_order'],
            );
            $lines[] = sprintf(
                'WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `document_categories`) AS `existing_categories` WHERE `existing_categories`.`id` = %d OR `existing_categories`.`slug` = %s);',
                $category['id'],
                AccessSqlExporter::sqlLiteral($category['slug']),
            );
            $lines[] = '';
        }

        foreach ([
            '2026_11_04_100001_create_company_tables',
            '2026_11_04_100002_grant_company_module_access',
        ] as $migration) {
            $literal = AccessSqlExporter::sqlLiteral($migration);
            $lines[] = 'INSERT INTO `migrations` (`migration`, `batch`)';
            $lines[] = "SELECT {$literal}, COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL";
            $lines[] = "WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = {$literal});";
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private static function ddl(): string
    {
        return <<<'SQL'
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
SQL;
    }
}
