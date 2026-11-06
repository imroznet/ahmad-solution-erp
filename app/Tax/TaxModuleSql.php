<?php

namespace App\Tax;

use App\Authorization\AccessSqlExporter;
use App\Authorization\PermissionCatalog;

/**
 * phpMyAdmin script for a database that already has commercial invoices.
 * A clean install uses mysql-schema.sql and reference-data.sql instead.
 */
final class TaxModuleSql
{
    public static function upgrade(): string
    {
        $lines = [
            '-- Ahmad Solution Ltd. ERP tax-rule, Mushak 6.3, VDS, and AIT upgrade.',
            '-- Use this when delivery challans and commercial invoices are already imported and these tables are not.',
            '-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.',
            '-- This script creates the tax tables, adds their permissions, and grants the default role set.',
            '-- It does not seed a VAT, VDS, or AIT rate. It does not delete rows or customized grants.',
            '-- Importing it again restores a default tax grant that was removed. It does not remove a grant.',
            '-- It expects users, roles, permissions, permission_role, parties, commercial_invoices, commercial_invoice_lines, and migrations.',
            '',
            'SET NAMES utf8mb4;',
            "SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));",
            '',
            self::ddl(),
            '',
        ];

        foreach (PermissionCatalog::permissions() as $permission) {
            if (! in_array($permission['group'], TaxModuleInstaller::groups(), true)) {
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

        $taxIds = TaxModuleInstaller::permissionIds();

        foreach (PermissionCatalog::roles() as $role) {
            $grants = array_values(array_intersect(
                PermissionCatalog::permissionIdsForRole($role['slug']),
                $taxIds,
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

        foreach ([
            '2026_11_08_100001_create_tax_document_tables',
            '2026_11_08_100002_grant_tax_document_access',
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
SQL;
    }
}
