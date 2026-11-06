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
            '-- Ahmad Solution Ltd. ERP tax-document upgrade.',
            '-- Use this when commercial invoices are already imported and the tax tables are not.',
            '-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.',
            '-- This script creates tax rules, Mushak 6.3, VDS, and AIT tables, adds their permissions, and grants the default role set.',
            '-- It does not delete rows or grants. Importing it again restores a default grant that was removed. It does not remove a custom grant.',
            '-- It does not seed a VAT rate. Output VAT, VDS, and AIT stay in separate tables.',
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
            '2026_11_08_100001_create_tax_tables',
            '2026_11_08_100002_grant_tax_module_access',
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
SQL;
    }
}
