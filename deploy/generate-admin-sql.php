<?php

/**
 * Print a one-time SQL statement that creates the first super administrator.
 *
 * Run from a trusted machine. Do not commit the output.
 *
 *   ADMIN_NAME="Amina Rahman" \
 *   ADMIN_EMAIL="admin@example.com" \
 *   ADMIN_PASSWORD="replace-with-a-long-password" \
 *   php deploy/generate-admin-sql.php
 *
 * The password rules match App\Auth\PasswordRule: at least 12 characters,
 * with uppercase, lowercase, and a number.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$name = trim((string) getenv('ADMIN_NAME'));
$email = strtolower(trim((string) getenv('ADMIN_EMAIL')));
$password = (string) getenv('ADMIN_PASSWORD');

$errors = [];

if ($name === '' || strlen($name) > 120) {
    $errors[] = 'ADMIN_NAME is required and must be 120 characters or fewer.';
}

if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'ADMIN_EMAIL must be a valid email address.';
}

if (strlen($password) < 12 || ! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password) || ! preg_match('/\d/', $password)) {
    $errors[] = 'ADMIN_PASSWORD must be at least 12 characters and include uppercase, lowercase, and a number.';
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT);

if ($hash === false) {
    fwrite(STDERR, 'Unable to hash the password.'.PHP_EOL);
    exit(1);
}

$quotedName = "'".str_replace("'", "''", $name)."'";
$quotedEmail = "'".str_replace("'", "''", $email)."'";
$quotedHash = "'".str_replace("'", "''", $hash)."'";

echo <<<SQL
-- Ahmad Solution Ltd. ERP first administrator.
-- Import after deploy/mysql-schema.sql and deploy/reference-data.sql.
-- Do not commit this output. It contains a password hash.

INSERT INTO `users` (`name`, `email`, `email_verified_at`, `password`, `is_active`, `created_at`, `updated_at`)
VALUES ({$quotedName}, {$quotedEmail}, NOW(), {$quotedHash}, 1, NOW(), NOW());

INSERT INTO `role_user` (`user_id`, `role_id`)
SELECT LAST_INSERT_ID(), `id` FROM `roles` WHERE `slug` = 'super-administrator';

SQL;
