#!/usr/bin/env bash
# Cloud Agent dependency install. Idempotent. Does not start MySQL or the app server.
# The local database password matches GitHub Actions (mysql:8.4) and is not a production secret.
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

export DEBIAN_FRONTEND=noninteractive

if ! dpkg -s php8.4-cli >/dev/null 2>&1 || ! dpkg -s php8.4-sqlite3 >/dev/null 2>&1 || ! dpkg -s php8.4-bcmath >/dev/null 2>&1 || ! dpkg -s php8.4-mysql >/dev/null 2>&1; then
    sudo apt-get update
    sudo apt-get install -y --no-install-recommends software-properties-common ca-certificates gnupg apt-transport-https
    if ! grep -Rqs "ondrej/php" /etc/apt/sources.list /etc/apt/sources.list.d 2>/dev/null; then
        sudo add-apt-repository -y ppa:ondrej/php
    fi
    sudo apt-get update
    sudo apt-get install -y --no-install-recommends \
        php8.4-cli php8.4-mysql php8.4-sqlite3 php8.4-xml php8.4-mbstring \
        php8.4-curl php8.4-zip php8.4-bcmath php8.4-gd php8.4-intl php8.4-readline \
        unzip
fi

if ! mysql --version 2>/dev/null | grep -q 'Ver 8\.4\.'; then
    sudo apt-get update
    sudo apt-get install -y --no-install-recommends ca-certificates curl gnupg
    key_tmp="$(mktemp)"
    curl -fsSL -o "$key_tmp" https://repo.mysql.com/RPM-GPG-KEY-mysql-2025
    fingerprint="$(gpg --show-keys --with-colons "$key_tmp" | awk -F: '/^fpr:/ { print $10; exit }')"
    if [[ "$fingerprint" != "BCA43417C3B485DD128EC6D4B7B3B788A8D3785C" ]]; then
        echo "Refusing MySQL repository key ${fingerprint}." >&2
        rm -f "$key_tmp"
        exit 1
    fi
    sudo install -d -m 0755 /usr/share/keyrings
    sudo gpg --dearmor --yes -o /usr/share/keyrings/mysql.gpg "$key_tmp"
    sudo chmod 0644 /usr/share/keyrings/mysql.gpg
    rm -f "$key_tmp"
    echo "deb [signed-by=/usr/share/keyrings/mysql.gpg] http://repo.mysql.com/apt/ubuntu noble mysql-8.4-lts" | sudo tee /etc/apt/sources.list.d/mysql-8.4.list >/dev/null
    sudo apt-get update
    if dpkg -s mysql-server-8.0 >/dev/null 2>&1; then
        sudo bash /etc/init.d/mysql stop || true
        sudo apt-get remove -y mysql-server-8.0 mysql-client-8.0 mysql-server-core-8.0 mysql-client-core-8.0 || true
    fi
    sudo apt-get install -y mysql-community-server mysql-community-client
fi

if [[ -f /etc/init.d/mysql && ! -x /etc/init.d/mysql ]]; then
    sudo chmod 755 /etc/init.d/mysql
fi

sudo tee /etc/mysql/mysql.conf.d/cloud-agent.cnf >/dev/null <<'EOF'
[mysqld]
bind-address = 127.0.0.1
mysqlx = 0
EOF

if ! command -v composer >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
fi

composer install --no-interaction --prefer-dist --no-progress

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

php <<'PHP'
<?php
$path = '.env';
$env = file_get_contents($path);
$replacements = [
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'asl_erp',
    'DB_USERNAME' => 'asl',
    'DB_PASSWORD' => 'secret',
    'APP_URL' => 'http://127.0.0.1:8000',
];
foreach ($replacements as $key => $value) {
    $pattern = '/^'.preg_quote($key, '/').'=.*/m';
    $line = $key.'='.$value;
    if (preg_match($pattern, $env)) {
        $env = preg_replace($pattern, $line, $env);
    } else {
        $env = rtrim($env, "\n")."\n".$line."\n";
    }
}
file_put_contents($path, $env);
PHP

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

npm ci
npm run build
