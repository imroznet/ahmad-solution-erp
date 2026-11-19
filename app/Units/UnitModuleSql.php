<?php

namespace App\Units;

use App\Authorization\AccessSqlExporter;
use App\Authorization\PermissionCatalog;

/**
 * phpMyAdmin upgrade for the measurement-unit list.
 * Import once on a database that already has the access-control tables.
 * Importing it again does not duplicate rows. A second import restores a
 * starter symbol that was deleted after the first import. It does not
 * rewrite unit text already stored on a document line.
 */
final class UnitModuleSql
{
    public static function upgrade(): string
    {
        $lines = [
            '-- Ahmad Solution Ltd. ERP measurement units upgrade.',
            '-- Use this when the access-control tables are already imported and the units table is not.',
            '-- A clean database should import mysql-schema.sql, reference-data.sql, and company-reference-data.sql instead.',
            '-- This script creates the units table, adds the unit permissions, grants the default role set, and inserts the starter list.',
            '-- It does not delete rows, grants, or customized permissions. It is safe to import again.',
            '-- A second import puts back a starter symbol that was deleted. It does not change text already stored on a line.',
            '-- It expects roles, permissions, permission_role, and migrations.',
            '',
            'SET NAMES utf8mb4;',
            "SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));",
            '',
            self::ddl(),
            '',
        ];

        $unitIds = [];

        foreach (PermissionCatalog::permissions() as $permission) {
            if ($permission['group'] !== 'Units') {
                continue;
            }

            $unitIds[] = $permission['id'];
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

        foreach (PermissionCatalog::roles() as $role) {
            $grants = array_values(array_intersect(
                PermissionCatalog::permissionIdsForRole($role['slug']),
                $unitIds,
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

        foreach (UnitCatalog::starter() as $unit) {
            $symbol = AccessSqlExporter::sqlLiteral($unit['symbol']);
            $lines[] = 'INSERT INTO `units` (`name`, `symbol`, `is_active`, `sort_order`, `created_at`, `updated_at`)';
            $lines[] = sprintf(
                'SELECT %s, %s, 1, %d, NOW(), NOW() FROM DUAL',
                AccessSqlExporter::sqlLiteral($unit['name']),
                $symbol,
                $unit['sort_order'],
            );
            $lines[] = sprintf(
                'WHERE NOT EXISTS (SELECT 1 FROM (SELECT `symbol` FROM `units`) AS `existing_units` WHERE `existing_units`.`symbol` = %s);',
                $symbol,
            );
            $lines[] = '';
        }

        $migration = '2026_11_19_110001_create_units_table';
        $literal = AccessSqlExporter::sqlLiteral($migration);
        $lines[] = 'INSERT INTO `migrations` (`migration`, `batch`)';
        $lines[] = "SELECT {$literal}, COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL";
        $lines[] = "WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = {$literal});";
        $lines[] = '';

        return implode("\n", $lines);
    }

    private static function ddl(): string
    {
        return <<<'SQL'
CREATE TABLE IF NOT EXISTS `units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `symbol` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_symbol_unique` (`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }
}
