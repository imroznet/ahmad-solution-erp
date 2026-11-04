<?php

namespace App\Parties;

use App\Authorization\AccessSqlExporter;
use App\Authorization\PermissionCatalog;

/**
 * phpMyAdmin script for a database that already has the foundation and company tables.
 * A clean install uses mysql-schema.sql and reference-data.sql instead.
 */
final class PartyModuleSql
{
    public static function upgrade(): string
    {
        $lines = [
            '-- Ahmad Solution Ltd. ERP parties upgrade.',
            '-- Use this when the foundation and company tables are already imported and the party tables are not.',
            '-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.',
            '-- This script creates the party tables, adds the party permissions, and grants the default role set.',
            '-- It does not delete rows, grants, or customized permissions. It is safe to import again.',
            '-- It expects users, roles, permissions, permission_role, and migrations.',
            '',
            'SET NAMES utf8mb4;',
            "SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));",
            '',
            self::ddl(),
            '',
        ];

        foreach (PermissionCatalog::permissions() as $permission) {
            if ($permission['group'] !== 'Parties') {
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

        $partyIds = [];

        foreach (PermissionCatalog::permissions() as $permission) {
            if ($permission['group'] === 'Parties') {
                $partyIds[] = $permission['id'];
            }
        }

        foreach (PermissionCatalog::roles() as $role) {
            $grants = array_values(array_intersect(
                PermissionCatalog::permissionIdsForRole($role['slug']),
                $partyIds,
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
            '2026_11_05_100001_create_party_tables',
            '2026_11_05_100002_grant_party_module_access',
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
CREATE TABLE IF NOT EXISTS `parties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `legal_name` varchar(200) NOT NULL,
  `trading_name` varchar(200) DEFAULT NULL,
  `legal_name_bn` varchar(200) DEFAULT NULL,
  `trading_name_bn` varchar(200) DEFAULT NULL,
  `party_kind` varchar(40) NOT NULL,
  `registration_number` varchar(60) DEFAULT NULL,
  `tin` varchar(30) DEFAULT NULL,
  `bin` varchar(30) DEFAULT NULL,
  `vat_registration_number` varchar(40) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `notes` text,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `currency_code` char(3) NOT NULL DEFAULT 'BDT',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parties_legal_name_index` (`legal_name`),
  KEY `parties_trading_name_index` (`trading_name`),
  KEY `parties_tin_index` (`tin`),
  KEY `parties_bin_index` (`bin`),
  KEY `parties_email_index` (`email`),
  KEY `parties_phone_index` (`phone`),
  KEY `parties_status_legal_name_index` (`status`, `legal_name`),
  CONSTRAINT `parties_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `parties_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `party_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `party_id` bigint unsigned NOT NULL,
  `role` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `party_roles_party_id_role_unique` (`party_id`, `role`),
  KEY `party_roles_party_id_index` (`party_id`),
  KEY `party_roles_role_index` (`role`),
  CONSTRAINT `party_roles_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `party_contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `party_id` bigint unsigned NOT NULL,
  `name` varchar(160) NOT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `department` varchar(120) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `alternative_phone` varchar(40) DEFAULT NULL,
  `whatsapp_number` varchar(40) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `party_contacts_party_id_index` (`party_id`),
  KEY `party_contacts_primary_index` (`party_id`, `is_primary`, `is_active`),
  KEY `party_contacts_email_index` (`email`),
  KEY `party_contacts_phone_index` (`phone`),
  KEY `party_contacts_created_by_index` (`created_by`),
  KEY `party_contacts_updated_by_index` (`updated_by`),
  CONSTRAINT `party_contacts_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `party_contacts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `party_contacts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `party_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `party_id` bigint unsigned NOT NULL,
  `address_type` varchar(40) NOT NULL,
  `address_line_1` varchar(200) NOT NULL,
  `address_line_2` varchar(200) DEFAULT NULL,
  `area` varchar(120) DEFAULT NULL,
  `upazila` varchar(120) DEFAULT NULL,
  `district` varchar(120) NOT NULL,
  `division` varchar(120) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `country` varchar(80) NOT NULL,
  `is_default_billing` tinyint(1) NOT NULL DEFAULT 0,
  `is_default_shipping` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date DEFAULT NULL,
  `effective_until` date DEFAULT NULL,
  `supersedes_address_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `party_addresses_party_id_index` (`party_id`),
  KEY `party_addresses_party_active_index` (`party_id`, `is_active`),
  KEY `party_addresses_district_index` (`district`),
  KEY `party_addresses_address_type_index` (`address_type`),
  KEY `party_addresses_created_by_index` (`created_by`),
  KEY `party_addresses_updated_by_index` (`updated_by`),
  CONSTRAINT `party_addresses_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `party_addresses_supersedes_address_id_foreign` FOREIGN KEY (`supersedes_address_id`) REFERENCES `party_addresses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `party_addresses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `party_addresses_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }
}
