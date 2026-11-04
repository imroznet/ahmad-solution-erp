<?php

namespace App\Authorization;

/**
 * Emits the first-install role and permission rows for phpMyAdmin.
 * The statement is insert-only so it cannot wipe a live permission matrix.
 */
final class AccessSqlExporter
{
    public static function toSql(): string
    {
        $lines = [
            '-- Ahmad Solution Ltd. ERP reference data',
            '-- Roles and permissions only. No users and no secrets.',
            '-- Import once, after the schema, into an empty access-control set of tables.',
            '-- If these primary keys already exist, stop and review. Do not force the import.',
            '',
            'SET NAMES utf8mb4;',
            '',
        ];

        $permissionRows = [];

        foreach (PermissionCatalog::permissions() as $permission) {
            $permissionRows[] = sprintf(
                '(%d, %s, %s, %s, %s, NOW(), NOW())',
                $permission['id'],
                self::sqlLiteral($permission['name']),
                self::sqlLiteral($permission['slug']),
                self::sqlLiteral($permission['group']),
                self::sqlLiteral($permission['description']),
            );
        }

        $lines[] = 'INSERT INTO `permissions` (`id`, `name`, `slug`, `permission_group`, `description`, `created_at`, `updated_at`) VALUES';
        $lines[] = implode(",\n", $permissionRows).';';
        $lines[] = '';

        $roleRows = [];
        $pivotRows = [];

        foreach (PermissionCatalog::roles() as $role) {
            $roleRows[] = sprintf(
                '(%d, %s, %s, %s, 1, NOW(), NOW())',
                $role['id'],
                self::sqlLiteral($role['name']),
                self::sqlLiteral($role['slug']),
                self::sqlLiteral($role['description']),
            );

            foreach (PermissionCatalog::permissionIdsForRole($role['slug']) as $permissionId) {
                $pivotRows[] = sprintf('(%d, %d)', $role['id'], $permissionId);
            }
        }

        $lines[] = 'INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES';
        $lines[] = implode(",\n", $roleRows).';';
        $lines[] = '';
        $lines[] = 'INSERT INTO `permission_role` (`role_id`, `permission_id`) VALUES';
        $lines[] = implode(",\n", $pivotRows).';';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * MySQL string literal with backslash escapes enabled.
     * A backslash is escaped before an apostrophe, so A\'B becomes 'A\\\'B'.
     * Keep this identical to sql_literal() in deploy/generate-admin-sql.php.
     */
    public static function sqlLiteral(string $value): string
    {
        return "'".str_replace(
            ['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
            ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
            $value,
        )."'";
    }
}
