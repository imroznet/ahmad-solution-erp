#!/usr/bin/env bash
# Cloud Agent boot. Starts local MySQL 8.4, migrates, seeds, and serves the app.
# The local database password matches GitHub Actions and is not a production secret.
set -euo pipefail

if [[ ! -f artisan ]]; then
    script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    if [[ -f "$script_dir/../artisan" ]]; then
        cd "$script_dir/.."
    fi
fi
if [[ ! -f artisan ]]; then
    echo "Run this script from the application root." >&2
    exit 1
fi

if ! sudo mysqladmin --protocol=socket ping -uroot >/dev/null 2>&1; then
    if [[ -x /etc/init.d/mysql ]]; then
        sudo service mysql start
    else
        sudo bash /etc/init.d/mysql start
    fi
fi

ready=0
for _ in $(seq 1 30); do
    if sudo mysqladmin --protocol=socket ping -uroot >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 1
done
if [[ "$ready" -ne 1 ]]; then
    echo "MySQL did not become ready." >&2
    exit 1
fi

mysql_version="$(sudo mysql --protocol=socket -uroot -N -e "SELECT VERSION();")"
case "$mysql_version" in
    8.4.*) ;;
    *)
        echo "MySQL ${mysql_version} is running. GitHub Actions uses the mysql:8.4 image." >&2
        exit 1
        ;;
esac

sudo mysql --protocol=socket -uroot <<'SQL'
CREATE DATABASE IF NOT EXISTS asl_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'asl'@'127.0.0.1' IDENTIFIED BY 'secret';
CREATE USER IF NOT EXISTS 'asl'@'localhost' IDENTIFIED BY 'secret';
ALTER USER 'asl'@'127.0.0.1' IDENTIFIED BY 'secret';
ALTER USER 'asl'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON asl_erp.* TO 'asl'@'127.0.0.1';
GRANT ALL PRIVILEGES ON asl_erp.* TO 'asl'@'localhost';
FLUSH PRIVILEGES;
SQL

php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction

if [[ -n "${ADMIN_NAME:-}" && -n "${ADMIN_EMAIL:-}" && -n "${ADMIN_PASSWORD:-}" ]]; then
    admin_exists="$(php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$email = strtolower(trim((string) getenv('ADMIN_EMAIL')));
echo App\Models\User::query()->where('email', $email)->exists() ? '1' : '0';
PHP
)"
    if [[ "$admin_exists" == "0" ]]; then
        php artisan asl:create-admin --no-interaction
    fi
fi

unset ADMIN_NAME ADMIN_EMAIL ADMIN_PASSWORD

if php -r 'exit(@fsockopen("127.0.0.1", 8000) ? 0 : 1);'; then
    echo "php artisan serve is already listening on 127.0.0.1:8000"
    exit 0
fi

exec php artisan serve --host=127.0.0.1 --port=8000
