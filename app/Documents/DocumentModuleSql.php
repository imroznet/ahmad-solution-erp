<?php

namespace App\Documents;

use App\Authorization\AccessSqlExporter;
use App\Authorization\PermissionCatalog;

/**
 * phpMyAdmin script for a database that already has the quotation tables.
 * A clean install uses mysql-schema.sql and reference-data.sql instead.
 */
final class DocumentModuleSql
{
    public static function upgrade(): string
    {
        $lines = [
            '-- Ahmad Solution Ltd. ERP delivery challan and commercial invoice upgrade.',
            '-- Use this when the quotation tables are already imported and these document tables are not.',
            '-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.',
            '-- This script creates the challan and commercial invoice tables, adds their permissions, and grants the default role set.',
            '-- It does not delete rows, grants, or customized permissions. It is safe to import again.',
            '-- Importing it again restores a default grant that was removed. It does not void documents.',
            '-- It expects users, roles, permissions, permission_role, quotations, quotation_revisions, quotation_lines, and migrations.',
            '',
            'SET NAMES utf8mb4;',
            "SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));",
            '',
            self::ddl(),
            '',
        ];

        foreach (PermissionCatalog::permissions() as $permission) {
            if (! in_array($permission['group'], ['Challans', 'Invoices'], true)) {
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

        $documentIds = DocumentModuleInstaller::permissionIds();

        foreach (PermissionCatalog::roles() as $role) {
            $grants = array_values(array_intersect(
                PermissionCatalog::permissionIdsForRole($role['slug']),
                $documentIds,
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
            '2026_11_07_100001_create_delivery_document_tables',
            '2026_11_07_100002_grant_delivery_document_access',
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
CREATE TABLE IF NOT EXISTS `delivery_challan_number_sequences` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commercial_invoice_number_sequences` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_challans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(32) DEFAULT NULL,
  `quotation_id` bigint unsigned NOT NULL,
  `quotation_revision_id` bigint unsigned NOT NULL,
  `status` varchar(20) NOT NULL,
  `challan_date` date NOT NULL,
  `delivery_location` varchar(200) DEFAULT NULL,
  `recipient_name` varchar(160) DEFAULT NULL,
  `recipient_phone` varchar(40) DEFAULT NULL,
  `vehicle_or_carrier` varchar(160) DEFAULT NULL,
  `carrier_reference` varchar(120) DEFAULT NULL,
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
  `customer_notes` text,
  `internal_notes` text,
  `company_snapshot` json DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `dispatched_by` bigint unsigned DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `acknowledged_by` bigint unsigned DEFAULT NULL,
  `acknowledgment_name` varchar(160) DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_challans_number_unique` (`number`),
  KEY `delivery_challans_quotation_id_foreign` (`quotation_id`),
  KEY `delivery_challans_quotation_revision_id_foreign` (`quotation_revision_id`),
  KEY `delivery_challans_issued_by_foreign` (`issued_by`),
  KEY `delivery_challans_dispatched_by_foreign` (`dispatched_by`),
  KEY `delivery_challans_acknowledged_by_foreign` (`acknowledged_by`),
  KEY `delivery_challans_voided_by_foreign` (`voided_by`),
  KEY `delivery_challans_created_by_foreign` (`created_by`),
  KEY `delivery_challans_quotation_status_index` (`quotation_id`, `status`),
  CONSTRAINT `delivery_challans_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_challans_quotation_revision_id_foreign` FOREIGN KEY (`quotation_revision_id`) REFERENCES `quotation_revisions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_challans_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_challans_dispatched_by_foreign` FOREIGN KEY (`dispatched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_challans_acknowledged_by_foreign` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_challans_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_challans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_challan_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_challan_id` bigint unsigned NOT NULL,
  `quotation_line_id` bigint unsigned NOT NULL,
  `position` smallint unsigned NOT NULL,
  `description` text NOT NULL,
  `unit` varchar(40) NOT NULL,
  `quantity` decimal(16,4) NOT NULL,
  `accepted_quantity` decimal(16,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_challan_lines_challan_position_unique` (`delivery_challan_id`, `position`),
  UNIQUE KEY `delivery_challan_lines_challan_line_unique` (`delivery_challan_id`, `quotation_line_id`),
  KEY `delivery_challan_lines_delivery_challan_id_foreign` (`delivery_challan_id`),
  KEY `delivery_challan_lines_quotation_line_id_foreign` (`quotation_line_id`),
  CONSTRAINT `delivery_challan_lines_delivery_challan_id_foreign` FOREIGN KEY (`delivery_challan_id`) REFERENCES `delivery_challans` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_challan_lines_quotation_line_id_foreign` FOREIGN KEY (`quotation_line_id`) REFERENCES `quotation_lines` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_challan_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_challan_id` bigint unsigned NOT NULL,
  `event` varchar(40) NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `recipient_name` varchar(160) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_challan_events_delivery_challan_id_foreign` (`delivery_challan_id`),
  KEY `delivery_challan_events_actor_id_foreign` (`actor_id`),
  KEY `delivery_challan_events_challan_id_index` (`delivery_challan_id`, `id`),
  CONSTRAINT `delivery_challan_events_delivery_challan_id_foreign` FOREIGN KEY (`delivery_challan_id`) REFERENCES `delivery_challans` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_challan_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commercial_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(32) DEFAULT NULL,
  `quotation_id` bigint unsigned NOT NULL,
  `quotation_revision_id` bigint unsigned NOT NULL,
  `status` varchar(20) NOT NULL,
  `invoice_date` date NOT NULL,
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
  `customer_notes` text,
  `internal_notes` text,
  `tax_notice` text NOT NULL,
  `tax_treatment` varchar(40) NOT NULL,
  `subtotal` decimal(18,2) NOT NULL,
  `total` decimal(18,2) NOT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'BDT',
  `company_snapshot` json DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commercial_invoices_number_unique` (`number`),
  KEY `commercial_invoices_quotation_id_foreign` (`quotation_id`),
  KEY `commercial_invoices_quotation_revision_id_foreign` (`quotation_revision_id`),
  KEY `commercial_invoices_issued_by_foreign` (`issued_by`),
  KEY `commercial_invoices_voided_by_foreign` (`voided_by`),
  KEY `commercial_invoices_created_by_foreign` (`created_by`),
  KEY `commercial_invoices_quotation_status_index` (`quotation_id`, `status`),
  CONSTRAINT `commercial_invoices_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commercial_invoices_quotation_revision_id_foreign` FOREIGN KEY (`quotation_revision_id`) REFERENCES `quotation_revisions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commercial_invoices_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commercial_invoices_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commercial_invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commercial_invoice_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `commercial_invoice_id` bigint unsigned NOT NULL,
  `delivery_challan_line_id` bigint unsigned NOT NULL,
  `quotation_line_id` bigint unsigned NOT NULL,
  `position` smallint unsigned NOT NULL,
  `description` text NOT NULL,
  `unit` varchar(40) NOT NULL,
  `quantity` decimal(16,4) NOT NULL,
  `unit_price` decimal(16,2) NOT NULL,
  `line_total` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commercial_invoice_lines_invoice_position_unique` (`commercial_invoice_id`, `position`),
  UNIQUE KEY `commercial_invoice_lines_invoice_challan_unique` (`commercial_invoice_id`, `delivery_challan_line_id`),
  KEY `commercial_invoice_lines_commercial_invoice_id_foreign` (`commercial_invoice_id`),
  KEY `commercial_invoice_lines_delivery_challan_line_id_foreign` (`delivery_challan_line_id`),
  KEY `commercial_invoice_lines_quotation_line_id_foreign` (`quotation_line_id`),
  CONSTRAINT `commercial_invoice_lines_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commercial_invoice_lines_delivery_challan_line_id_foreign` FOREIGN KEY (`delivery_challan_line_id`) REFERENCES `delivery_challan_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commercial_invoice_lines_quotation_line_id_foreign` FOREIGN KEY (`quotation_line_id`) REFERENCES `quotation_lines` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commercial_invoice_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `commercial_invoice_id` bigint unsigned NOT NULL,
  `event` varchar(40) NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commercial_invoice_events_commercial_invoice_id_foreign` (`commercial_invoice_id`),
  KEY `commercial_invoice_events_actor_id_foreign` (`actor_id`),
  KEY `commercial_invoice_events_invoice_id_index` (`commercial_invoice_id`, `id`),
  CONSTRAINT `commercial_invoice_events_commercial_invoice_id_foreign` FOREIGN KEY (`commercial_invoice_id`) REFERENCES `commercial_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commercial_invoice_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }
}
