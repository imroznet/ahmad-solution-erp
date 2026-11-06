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
            '-- Use this when commercial invoices are already imported and these tables are not.',
            '-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.',
            '-- Money columns that participate in the VAT calculation are DECIMAL(20,4).',
            '-- The calculation may use 2, 3, or 4 decimal places. BDT on the Mushak face stays at 2.',
            '-- It does not delete rows or customized grants. It is safe to import again.',
            '-- Importing it again restores a default tax grant that was removed. It does not remove a grant.',
            '-- It does not write a VAT percentage. Output VAT, VDS, and AIT stay in different tables.',
            '-- It expects users, roles, permissions, permission_role, parties, commercial_invoices, commercial_invoice_lines, and migrations.',
            '',
            'SET NAMES utf8mb4;',
            "SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));",
            '',
            self::ddl(),
            '',
        ];

        foreach (PermissionCatalog::permissions() as $permission) {
            if (! in_array($permission['group'], ['Tax rules', 'Mushak', 'VDS', 'AIT'], true)) {
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
CREATE TABLE IF NOT EXISTS `tax_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_type` varchar(20) NOT NULL,
  `rule_code` varchar(40) NOT NULL,
  `supply_classification` varchar(80) NOT NULL DEFAULT '',
  `classification_key` varchar(80) NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_rules_type_code_unique` (`tax_type`, `rule_code`),
  KEY `tax_rules_type_classification_index` (`tax_type`, `classification_key`),
  KEY `tax_rules_created_by_foreign` (`created_by`),
  CONSTRAINT `tax_rules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tax_rule_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_rule_id` bigint unsigned NOT NULL,
  `version_number` int unsigned NOT NULL,
  `status` varchar(20) NOT NULL,
  `rate_kind` varchar(20) NOT NULL DEFAULT 'none',
  `rate_value` decimal(7,4) DEFAULT NULL,
  `fixed_amount` decimal(20,4) DEFAULT NULL,
  `calculation_basis` varchar(80) DEFAULT NULL,
  `threshold_note` text,
  `exemption_note` text,
  `legal_instrument` varchar(200) NOT NULL,
  `section_reference` varchar(200) NOT NULL,
  `source_url` varchar(500) NOT NULL,
  `publication_date` date DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `notes` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `retired_by` bigint unsigned DEFAULT NULL,
  `retired_at` timestamp NULL DEFAULT NULL,
  `retirement_reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_rule_versions_rule_version_unique` (`tax_rule_id`, `version_number`),
  KEY `tax_rule_versions_status_from_index` (`status`, `effective_from`),
  KEY `tax_rule_versions_tax_rule_id_foreign` (`tax_rule_id`),
  KEY `tax_rule_versions_created_by_foreign` (`created_by`),
  KEY `tax_rule_versions_approved_by_foreign` (`approved_by`),
  KEY `tax_rule_versions_retired_by_foreign` (`retired_by`),
  CONSTRAINT `tax_rule_versions_tax_rule_id_foreign` FOREIGN KEY (`tax_rule_id`) REFERENCES `tax_rules` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tax_rule_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rule_versions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rule_versions_retired_by_foreign` FOREIGN KEY (`retired_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_number_sequences` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(32) DEFAULT NULL,
  `commercial_invoice_id` bigint unsigned NOT NULL,
  `status` varchar(20) NOT NULL,
  `document_date` date NOT NULL,
  `supply_destination` varchar(200) DEFAULT NULL,
  `supply_classification` varchar(80) NOT NULL DEFAULT '',
  `buyer_source` varchar(20) NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `buyer_name` varchar(200) NOT NULL,
  `buyer_bin` varchar(40) DEFAULT NULL,
  `buyer_tin` varchar(40) DEFAULT NULL,
  `buyer_address` text NOT NULL,
  `buyer_phone` varchar(40) DEFAULT NULL,
  `buyer_email` varchar(160) DEFAULT NULL,
  `calculation_precision` tinyint unsigned NOT NULL,
  `rounding_method` varchar(32) NOT NULL,
  `currency_code` varchar(3) NOT NULL DEFAULT 'BDT',
  `base_total` decimal(20,4) DEFAULT NULL,
  `vat_total` decimal(20,4) DEFAULT NULL,
  `adjustment_total` decimal(20,4) DEFAULT NULL,
  `final_total` decimal(20,4) DEFAULT NULL,
  `tax_rule_version_id` bigint unsigned DEFAULT NULL,
  `rule_explanation` text,
  `tax_review_status` varchar(40) NOT NULL DEFAULT 'pending',
  `legal_treatment_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `legal_treatment_note` text,
  `review_reason` text,
  `rate_notice` text,
  `company_snapshot` json DEFAULT NULL,
  `source_snapshot` json DEFAULT NULL,
  `customer_notes` text,
  `internal_notes` text,
  `prepared_by_designation` varchar(120) DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` varchar(500) DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mushak_invoices_number_unique` (`number`),
  KEY `mushak_invoices_invoice_status_index` (`commercial_invoice_id`, `status`),
  KEY `mushak_invoices_party_id_foreign` (`party_id`),
  KEY `mushak_invoices_tax_rule_version_id_foreign` (`tax_rule_version_id`),
  KEY `mushak_invoices_issued_by_foreign` (`issued_by`),
  KEY `mushak_invoices_voided_by_foreign` (`voided_by`),
  KEY `mushak_invoices_created_by_foreign` (`created_by`),
  CONSTRAINT `mushak_invoices_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_invoices_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_invoices_tax_rule_version_id_foreign` FOREIGN KEY (`tax_rule_version_id`) REFERENCES `tax_rule_versions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_invoices_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_invoices_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mushak_invoice_id` bigint unsigned NOT NULL,
  `commercial_invoice_line_id` bigint unsigned DEFAULT NULL,
  `position` smallint unsigned NOT NULL,
  `description` varchar(2000) NOT NULL,
  `unit` varchar(40) NOT NULL,
  `quantity` decimal(16,4) NOT NULL,
  `base_amount` decimal(20,4) NOT NULL,
  `vat_rate` decimal(7,4) DEFAULT NULL,
  `vat_amount` decimal(20,4) DEFAULT NULL,
  `adjustment_amount` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `adjustment_reason` varchar(500) DEFAULT NULL,
  `final_amount` decimal(20,4) DEFAULT NULL,
  `residual_amount` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `calculation_origin` varchar(20) NOT NULL,
  `rate_entered_by` bigint unsigned DEFAULT NULL,
  `rate_entered_at` timestamp NULL DEFAULT NULL,
  `adjustment_updated_by` bigint unsigned DEFAULT NULL,
  `adjustment_updated_at` timestamp NULL DEFAULT NULL,
  `superseded_at` timestamp NULL DEFAULT NULL,
  `superseded_by` bigint unsigned DEFAULT NULL,
  `active_position` smallint unsigned GENERATED ALWAYS AS (case when `superseded_at` is null then `position` else null end) STORED,
  `active_invoice_line_id` bigint unsigned GENERATED ALWAYS AS (case when `superseded_at` is null then `commercial_invoice_line_id` else null end) STORED,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mushak_lines_current_position_unique` (`mushak_invoice_id`, `active_position`),
  UNIQUE KEY `mushak_lines_current_source_unique` (`mushak_invoice_id`, `active_invoice_line_id`),
  KEY `mushak_lines_mushak_invoice_id_foreign` (`mushak_invoice_id`),
  KEY `mushak_lines_commercial_invoice_line_id_foreign` (`commercial_invoice_line_id`),
  KEY `mushak_lines_rate_entered_by_foreign` (`rate_entered_by`),
  KEY `mushak_lines_adjustment_updated_by_foreign` (`adjustment_updated_by`),
  KEY `mushak_lines_superseded_by_foreign` (`superseded_by`),
  CONSTRAINT `mushak_lines_mushak_invoice_id_foreign` FOREIGN KEY (`mushak_invoice_id`) REFERENCES `mushak_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_lines_commercial_invoice_line_id_foreign` FOREIGN KEY (`commercial_invoice_line_id`) REFERENCES `commercial_invoice_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_lines_rate_entered_by_foreign` FOREIGN KEY (`rate_entered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_lines_adjustment_updated_by_foreign` FOREIGN KEY (`adjustment_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_lines_superseded_by_foreign` FOREIGN KEY (`superseded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mushak_invoice_id` bigint unsigned NOT NULL,
  `event` varchar(40) NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mushak_events_invoice_id_index` (`mushak_invoice_id`, `id`),
  KEY `mushak_events_actor_id_foreign` (`actor_id`),
  CONSTRAINT `mushak_events_mushak_invoice_id_foreign` FOREIGN KEY (`mushak_invoice_id`) REFERENCES `mushak_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vds_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` varchar(40) NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `party_name` varchar(200) NOT NULL,
  `party_bin` varchar(40) DEFAULT NULL,
  `party_tin` varchar(40) DEFAULT NULL,
  `commercial_invoice_id` bigint unsigned DEFAULT NULL,
  `mushak_invoice_id` bigint unsigned DEFAULT NULL,
  `tax_rule_version_id` bigint unsigned DEFAULT NULL,
  `supply_description` varchar(500) NOT NULL,
  `taxable_basis` decimal(20,4) DEFAULT NULL,
  `amount` decimal(20,4) NOT NULL,
  `record_date` date NOT NULL,
  `certificate_reference` varchar(80) DEFAULT NULL,
  `payment_reference` varchar(80) DEFAULT NULL,
  `source_note` text,
  `internal_notes` text,
  `certificate_status` varchar(40) NOT NULL DEFAULT 'not_issued_form_unverified',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vds_records_record_date_index` (`record_date`),
  KEY `vds_records_party_id_foreign` (`party_id`),
  KEY `vds_records_commercial_invoice_id_foreign` (`commercial_invoice_id`),
  KEY `vds_records_mushak_invoice_id_foreign` (`mushak_invoice_id`),
  KEY `vds_records_tax_rule_version_id_foreign` (`tax_rule_version_id`),
  KEY `vds_records_created_by_foreign` (`created_by`),
  KEY `vds_records_updated_by_foreign` (`updated_by`),
  CONSTRAINT `vds_records_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_mushak_invoice_id_foreign` FOREIGN KEY (`mushak_invoice_id`) REFERENCES `mushak_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_tax_rule_version_id_foreign` FOREIGN KEY (`tax_rule_version_id`) REFERENCES `tax_rule_versions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vds_records_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ait_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` varchar(40) NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `party_name` varchar(200) NOT NULL,
  `party_bin` varchar(40) DEFAULT NULL,
  `party_tin` varchar(40) DEFAULT NULL,
  `commercial_invoice_id` bigint unsigned DEFAULT NULL,
  `mushak_invoice_id` bigint unsigned DEFAULT NULL,
  `tax_rule_version_id` bigint unsigned DEFAULT NULL,
  `supply_description` varchar(500) NOT NULL,
  `taxable_basis` decimal(20,4) DEFAULT NULL,
  `amount` decimal(20,4) NOT NULL,
  `record_date` date NOT NULL,
  `certificate_reference` varchar(80) DEFAULT NULL,
  `payment_reference` varchar(80) DEFAULT NULL,
  `source_note` text,
  `internal_notes` text,
  `certificate_status` varchar(40) NOT NULL DEFAULT 'not_issued_form_unverified',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ait_records_record_date_index` (`record_date`),
  KEY `ait_records_party_id_foreign` (`party_id`),
  KEY `ait_records_commercial_invoice_id_foreign` (`commercial_invoice_id`),
  KEY `ait_records_mushak_invoice_id_foreign` (`mushak_invoice_id`),
  KEY `ait_records_tax_rule_version_id_foreign` (`tax_rule_version_id`),
  KEY `ait_records_created_by_foreign` (`created_by`),
  KEY `ait_records_updated_by_foreign` (`updated_by`),
  CONSTRAINT `ait_records_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_mushak_invoice_id_foreign` FOREIGN KEY (`mushak_invoice_id`) REFERENCES `mushak_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_tax_rule_version_id_foreign` FOREIGN KEY (`tax_rule_version_id`) REFERENCES `tax_rule_versions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ait_records_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }
}
