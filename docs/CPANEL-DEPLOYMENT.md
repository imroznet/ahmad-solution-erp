# cPanel deployment

CI builds an artifact. It does not upload or migrate the staging site. Do not deploy this milestone until the package has been reviewed.

## Target layout

```text
/home/ahmadcom/laravel-stage/          application, vendor, .env, storage
/home/ahmadcom/laravel-stage/public    domain document root
```

Set the document root for `stage.ahmad.bd` to `/home/ahmadcom/laravel-stage/public`. The project root contains a deny-all `.htaccess` in case the document root is pointed at the wrong directory.

The server does not need Composer, Node, or Terminal for a normal upload. Those tools are required on the machine that builds the artifact.

## Build the package

GitHub Actions on this repository does all of the following:

1. `composer install`
2. Pint, PHPStan, and PHPUnit
3. `composer audit`
4. `npm ci` and `npm run build`
5. `php artisan migrate` against a disposable MySQL 8.4 service
6. A schema dump and the reference-data check
7. `composer install --no-dev --optimize-autoloader`
8. `scripts/build-deploy-package.sh`

Download `ahmad-solution-erp-<git-sha>.zip` from the workflow artifacts. The zip contains `vendor`, `public/build`, `deploy/mysql-schema.sql`, and `deploy/reference-data.sql`. It does not contain `.env`.

## Configure the environment

On a trusted machine:

```bash
php artisan key:generate --show
```

Create `/home/ahmadcom/laravel-stage/.env` from `.env.example` and set:

- `APP_ENV=staging`
- `APP_DEBUG=false`
- `APP_KEY` to the generated key
- `APP_URL=https://stage.ahmad.bd`
- `DB_HOST=localhost` unless the host's database panel shows another host
- `DB_DATABASE=ahmadcom_asl_erp`
- `DB_USERNAME=ahmadcom_asl_user`
- `DB_PASSWORD` to the password stored in cPanel, not in Git
- `LOG_LEVEL=warning`
- SMTP values only when mail is ready. Until then leave `MAIL_MAILER=log`

`SESSION_SECURE_COOKIE` can stay empty. An `https` application URL marks the cookie Secure. If sign-in loops after HTTPS is enabled, the web server is probably forwarding HTTP to PHP. Set `TRUSTED_PROXIES` to the proxy address reported by the host. Do not use `*`.

The account user should own the files. `storage` and `bootstrap/cache` must be writable by that same user. Do not use mode `777`.

## Database

Preferred, once a one-off cPanel cron or Terminal exists:

```text
/usr/local/bin/php /home/ahmadcom/laravel-stage/artisan migrate --force
```

Confirm the PHP 8.4 binary path in the host's PHP selector. Delete the cron as soon as it has run. Do not leave migrate on a schedule.

Until a CLI exists, import these files in phpMyAdmin in order:

1. `deploy/mysql-schema.sql`
2. `deploy/reference-data.sql`
3. Administrator SQL from the generator below

Stop if an import reports an existing table or a duplicate key. Do not drop the staging database to force it.

The schema dump includes the `migrations` rows so a later `artisan migrate` does not try to create the same tables again.

### First administrator

On a trusted machine, not on the website:

```bash
ADMIN_NAME="Full name" \
ADMIN_EMAIL="person@example.com" \
ADMIN_PASSWORD="a long password" \
php deploy/generate-admin-sql.php
```

Paste the printed SQL into phpMyAdmin. Do not save it in the repository. Unset `ADMIN_PASSWORD` afterwards.

The password needs at least 12 characters, upper case, lower case, and a number. The script prints a bcrypt hash and refuses to run if it is invoked by a web request.

## After upload

1. Open `https://stage.ahmad.bd/health`. The body should be `Service is available.` or, for a JSON client, `{"status":"ok"}`.
2. Sign in with the administrator created above.
3. Open System status. Confirm `pdo_mysql` is loaded and the database says connected. Record whether `intl` and `bcmath` are loaded. The application runs without `intl`. Decimal math runs without `bcmath`, using the pure-PHP calculator.
4. Confirm the page does not show a debug stack trace. `APP_ENV` must be `staging`.

## Queue and scheduler

No scheduled business task is registered. When one is added, the cron entry is:

```text
* * * * * /usr/local/bin/php /home/ahmadcom/laravel-stage/artisan schedule:run >> /dev/null 2>&1
```

The database queue does nothing until a worker runs. A shared host can call `php artisan queue:work --stop-when-empty` from cron once a module starts queueing mail. That cron is not required for this milestone.

## What not to upload

- `.env` from a developer machine
- `node_modules`
- `tests`
- `database/database.sqlite`
- generated administrator SQL
- customer records or company certificates
