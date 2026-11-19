<?php

namespace App\Quotations;

use App\Authorization\AccessSqlExporter;
use App\Authorization\PermissionCatalog;

/**
 * phpMyAdmin upgrade for a database that already has the quotation permissions.
 * It adds quotations.approve_own and grants that permission only to the super administrator.
 * It does not restore any other grant.
 */
final class QuotationSelfApprovalSql
{
    public static function upgrade(): string
    {
        $permission = null;
        $superId = 1;

        foreach (PermissionCatalog::permissions() as $candidate) {
            if ($candidate['slug'] === 'quotations.approve_own') {
                $permission = $candidate;
                break;
            }
        }

        foreach (PermissionCatalog::roles() as $role) {
            if ($role['slug'] === 'super-administrator') {
                $superId = $role['id'];
            }
        }

        if ($permission === null) {
            return '';
        }

        $migration = '2026_11_19_120001_grant_quotation_self_approval';
        $migrationLiteral = AccessSqlExporter::sqlLiteral($migration);
        $lines = [
            '-- Ahmad Solution Ltd. ERP quotation self-approval permission.',
            '-- Use this when the quotation permissions are already imported.',
            '-- It adds quotations.approve_own and grants it only to the super administrator.',
            '-- It does not grant that permission to any other role and does not restore other grants.',
            '-- Importing it again does not duplicate the row.',
            '-- It expects roles, permissions, permission_role, and migrations.',
            '',
            'SET NAMES utf8mb4;',
            "SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','));",
            '',
            'INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`)',
            sprintf(
                'SELECT %d, %s, %s, %s, %s, NOW(), NOW() FROM DUAL',
                $permission['id'],
                AccessSqlExporter::sqlLiteral($permission['name']),
                AccessSqlExporter::sqlLiteral($permission['slug']),
                AccessSqlExporter::sqlLiteral($permission['group']),
                AccessSqlExporter::sqlLiteral($permission['description']),
            ),
            sprintf(
                'WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id`, `slug` FROM `permissions`) AS `existing_permissions` WHERE `existing_permissions`.`id` = %d OR `existing_permissions`.`slug` = %s);',
                $permission['id'],
                AccessSqlExporter::sqlLiteral($permission['slug']),
            ),
            '',
            'INSERT INTO `permission_role` (`role_id`, `permission_id`)',
            sprintf('SELECT %d, %d FROM DUAL', $superId, $permission['id']),
            sprintf(
                'WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id`, `permission_id` FROM `permission_role`) AS `existing_grants` WHERE `existing_grants`.`role_id` = %d AND `existing_grants`.`permission_id` = %d);',
                $superId,
                $permission['id'],
            ),
            '',
            'INSERT INTO `migrations` (`migration`, `batch`)',
            "SELECT {$migrationLiteral}, COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL",
            "WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = {$migrationLiteral});",
            '',
        ];

        return implode("\n", $lines);
    }
}
