<?php

namespace App\Tax;

use App\Authorization\AccessSqlExporter;
use App\Authorization\PermissionCatalog;

/**
 * phpMyAdmin script for a database that already has the commercial-invoice tables.
 * A clean install uses mysql-schema.sql and reference-data.sql instead.
 */
final class TaxModuleSql
{
    public static function upgrade(): string
    {
        $lines = [
            '-- Ahmad Solution Ltd. ERP tax-rule, Mushak 6.3, VDS, and AIT upgrade.',
            '-- Use this when Milestones 2-5 are already imported and these tax tables are not.',
            '-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.',
            '-- This script creates the tax tables, adds their permissions, and grants the default role set.',
            '-- It does not delete rows or customized permissions. It is safe to import again.',
            '-- Importing it again restores a default tax grant that was removed. It does not change an issued Mushak.',
            '-- Output VAT, VDS, and AIT stay in separate tables. No statutory rate is inserted.',
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
  `version` smallint unsigned NOT NULL DEFAULT 1,
  `supersedes_rule_id` bigint unsigned DEFAULT NULL,
  `supply_classification` varchar(160) NOT NULL DEFAULT '',
  `documented_rate` decimal(12,4) DEFAULT NULL,
  `rate_kind` varchar(20) NOT NULL DEFAULT 'not_a_rate',
  `calculation_basis` text,
  `rounding_note` text,
  `price_treatment` varchar(20) NOT NULL DEFAULT 'unspecified',
  `threshold_note` text,
  `exemption_note` text,
  `supporting_data_note` text,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `legal_instrument` varchar(200) NOT NULL,
  `section_reference` varchar(200) DEFAULT NULL,
  `source_url` varchar(500) DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `instrument_effective_date` date DEFAULT NULL,
  `notes` text,
  `approval_status` varchar(20) NOT NULL,
  `activity_status` varchar(20) NOT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `retired_at` timestamp NULL DEFAULT NULL,
  `retired_by` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_rules_code_version_unique` (`tax_type`,`rule_code`,`version`),
  KEY `tax_rules_scope_index` (`tax_type`,`supply_classification`,`approval_status`,`activity_status`),
  CONSTRAINT `tax_rules_supersedes_rule_id_foreign` FOREIGN KEY (`supersedes_rule_id`) REFERENCES `tax_rules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rules_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rules_retired_by_foreign` FOREIGN KEY (`retired_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rules_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_invoice_number_sequences` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(32) DEFAULT NULL,
  `commercial_invoice_id` bigint unsigned NOT NULL,
  `quotation_id` bigint unsigned NOT NULL,
  `status` varchar(20) NOT NULL,
  `buyer_source` varchar(20) NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `document_date` date NOT NULL,
  `destination` varchar(200) NOT NULL,
  `calculation_precision` tinyint unsigned NOT NULL,
  `rounding_method` varchar(32) NOT NULL,
  `tax_review_status` varchar(40) NOT NULL DEFAULT 'review_required',
  `tax_review_note` text,
  `tax_review_confirmed_at` timestamp NULL DEFAULT NULL,
  `tax_review_confirmed_by` bigint unsigned DEFAULT NULL,
  `tax_rule_id` bigint unsigned DEFAULT NULL,
  `rule_snapshot` json DEFAULT NULL,
  `seller_legal_name` varchar(200) DEFAULT NULL,
  `seller_bin` varchar(30) DEFAULT NULL,
  `seller_tin` varchar(30) DEFAULT NULL,
  `seller_address` text,
  `seller_phone` varchar(40) DEFAULT NULL,
  `seller_email` varchar(255) DEFAULT NULL,
  `seller_snapshot` json DEFAULT NULL,
  `buyer_name` varchar(200) DEFAULT NULL,
  `buyer_bin` varchar(30) DEFAULT NULL,
  `buyer_tin` varchar(30) DEFAULT NULL,
  `buyer_address` text,
  `buyer_phone` varchar(40) DEFAULT NULL,
  `buyer_email` varchar(255) DEFAULT NULL,
  `buyer_snapshot` json DEFAULT NULL,
  `responsible_name` varchar(160) DEFAULT NULL,
  `responsible_designation` varchar(120) DEFAULT NULL,
  `subtotal_base` decimal(19,4) DEFAULT NULL,
  `total_vat` decimal(19,4) DEFAULT NULL,
  `total_adjustment` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_final` decimal(19,4) DEFAULT NULL,
  `total_supplementary_duty` decimal(19,4) DEFAULT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'BDT',
  `calculation_snapshot` json DEFAULT NULL,
  `internal_notes` text,
  `issued_at` timestamp NULL DEFAULT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mushak_invoices_number_unique` (`number`),
  KEY `mushak_invoices_invoice_status_index` (`commercial_invoice_id`,`status`),
  CONSTRAINT `mushak_invoices_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_invoices_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_invoices_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_invoices_tax_rule_id_foreign` FOREIGN KEY (`tax_rule_id`) REFERENCES `tax_rules` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_invoices_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_invoices_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_invoices_tax_review_confirmed_by_foreign` FOREIGN KEY (`tax_review_confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_invoice_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mushak_invoice_id` bigint unsigned NOT NULL,
  `commercial_invoice_line_id` bigint unsigned NOT NULL,
  `quotation_line_id` bigint unsigned NOT NULL,
  `delivery_challan_line_id` bigint unsigned NOT NULL,
  `position` smallint unsigned NOT NULL,
  `description` text NOT NULL,
  `source_description` text NOT NULL,
  `unit` varchar(40) NOT NULL,
  `quantity` decimal(16,4) NOT NULL,
  `source_unit_price` decimal(16,2) NOT NULL,
  `source_line_total` decimal(18,2) NOT NULL,
  `base_amount` decimal(19,4) NOT NULL,
  `vat_rate_percent` decimal(8,4) DEFAULT NULL,
  `vat_amount` decimal(19,4) DEFAULT NULL,
  `forward_vat_amount` decimal(19,4) DEFAULT NULL,
  `residual_applied` tinyint(1) NOT NULL DEFAULT 0,
  `adjustment_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `adjustment_reason` varchar(500) DEFAULT NULL,
  `adjustment_by` bigint unsigned DEFAULT NULL,
  `adjustment_at` timestamp NULL DEFAULT NULL,
  `supplementary_duty_amount` decimal(19,4) DEFAULT NULL,
  `final_amount` decimal(19,4) DEFAULT NULL,
  `calculation_snapshot` json DEFAULT NULL,
  `rate_entered_by` bigint unsigned DEFAULT NULL,
  `rate_entered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mushak_lines_position_unique` (`mushak_invoice_id`,`position`),
  UNIQUE KEY `mushak_lines_source_unique` (`mushak_invoice_id`,`commercial_invoice_line_id`),
  CONSTRAINT `mushak_invoice_lines_mushak_invoice_id_foreign` FOREIGN KEY (`mushak_invoice_id`) REFERENCES `mushak_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_invoice_lines_commercial_invoice_line_id_foreign` FOREIGN KEY (`commercial_invoice_line_id`) REFERENCES `commercial_invoice_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_invoice_lines_quotation_line_id_foreign` FOREIGN KEY (`quotation_line_id`) REFERENCES `quotation_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_invoice_lines_delivery_challan_line_id_foreign` FOREIGN KEY (`delivery_challan_line_id`) REFERENCES `delivery_challan_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_invoice_lines_adjustment_by_foreign` FOREIGN KEY (`adjustment_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mushak_invoice_lines_rate_entered_by_foreign` FOREIGN KEY (`rate_entered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mushak_invoice_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mushak_invoice_id` bigint unsigned NOT NULL,
  `event` varchar(40) NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mushak_events_invoice_index` (`mushak_invoice_id`,`id`),
  CONSTRAINT `mushak_invoice_events_mushak_invoice_id_foreign` FOREIGN KEY (`mushak_invoice_id`) REFERENCES `mushak_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mushak_invoice_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vds_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` varchar(32) NOT NULL,
  `status` varchar(20) NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `counterparty_name` varchar(200) NOT NULL,
  `counterparty_bin` varchar(30) DEFAULT NULL,
  `counterparty_tin` varchar(30) DEFAULT NULL,
  `commercial_invoice_id` bigint unsigned DEFAULT NULL,
  `mushak_invoice_id` bigint unsigned DEFAULT NULL,
  `source_reference` varchar(120) DEFAULT NULL,
  `taxable_basis` decimal(19,4) DEFAULT NULL,
  `deducted_amount` decimal(19,4) DEFAULT NULL,
  `record_date` date NOT NULL,
  `certificate_reference` varchar(120) DEFAULT NULL,
  `treasury_reference` varchar(120) DEFAULT NULL,
  `tax_rule_id` bigint unsigned DEFAULT NULL,
  `rule_snapshot` json DEFAULT NULL,
  `certificate_form` varchar(40) NOT NULL DEFAULT 'unverified',
  `review_status` varchar(40) NOT NULL DEFAULT 'review_required',
  `notes` text,
  `internal_notes` text,
  `recorded_at` timestamp NULL DEFAULT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vds_records_direction_status_index` (`direction`,`status`),
  CONSTRAINT `vds_records_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_mushak_invoice_id_foreign` FOREIGN KEY (`mushak_invoice_id`) REFERENCES `mushak_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_tax_rule_id_foreign` FOREIGN KEY (`tax_rule_id`) REFERENCES `tax_rules` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vds_records_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vds_records_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vds_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ait_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` varchar(32) NOT NULL,
  `status` varchar(20) NOT NULL,
  `party_id` bigint unsigned DEFAULT NULL,
  `counterparty_name` varchar(200) NOT NULL,
  `counterparty_bin` varchar(30) DEFAULT NULL,
  `counterparty_tin` varchar(30) DEFAULT NULL,
  `commercial_invoice_id` bigint unsigned DEFAULT NULL,
  `mushak_invoice_id` bigint unsigned DEFAULT NULL,
  `source_reference` varchar(120) DEFAULT NULL,
  `taxable_basis` decimal(19,4) DEFAULT NULL,
  `deducted_amount` decimal(19,4) DEFAULT NULL,
  `record_date` date NOT NULL,
  `certificate_reference` varchar(120) DEFAULT NULL,
  `treasury_reference` varchar(120) DEFAULT NULL,
  `tax_rule_id` bigint unsigned DEFAULT NULL,
  `rule_snapshot` json DEFAULT NULL,
  `certificate_form` varchar(40) NOT NULL DEFAULT 'unverified',
  `review_status` varchar(40) NOT NULL DEFAULT 'review_required',
  `notes` text,
  `internal_notes` text,
  `recorded_at` timestamp NULL DEFAULT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ait_records_direction_status_index` (`direction`,`status`),
  CONSTRAINT `ait_records_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_mushak_invoice_id_foreign` FOREIGN KEY (`mushak_invoice_id`) REFERENCES `mushak_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_tax_rule_id_foreign` FOREIGN KEY (`tax_rule_id`) REFERENCES `tax_rules` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ait_records_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ait_records_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ait_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }
}
