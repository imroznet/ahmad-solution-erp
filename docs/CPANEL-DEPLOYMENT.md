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
6. A schema dump, the access-control SQL check, the company-module SQL check, the parties-module SQL check, the quotations-module SQL check, the delivery-documents SQL check, and the tax-module SQL check
7. The company upgrade script is imported into that MySQL database and must leave 20 document categories. The parties upgrade script is imported twice on a database that has the foundation and company tables but not the party grants, and it must leave the party tables and the sales customer's permission without giving that role supplier access. The quotations upgrade script is imported twice after the party tables exist, and it must leave the nine quotation permissions, with `quotations.view` on the sales executive and without `quotations.approve` on that role. The delivery-documents upgrade script is imported twice after the quotation tables exist, and it must leave eight challan permissions and six invoice permissions, with `challans.view` on the sales executive and without `challans.void` on that role. The tax upgrade script is imported twice after those tables exist, and it must leave the tax-rule, Mushak, VDS, and AIT permissions, with `mushak.view` on the sales executive and without `mushak.void` or `tax_rules.approve` on the accountant.
8. `composer install --no-dev --optimize-autoloader`
9. `scripts/build-deploy-package.sh`

Download `ahmad-solution-erp-<git-sha>.zip` from the workflow artifacts. The zip contains `vendor`, `public/build`, `deploy/mysql-schema.sql`, `deploy/reference-data.sql`, `deploy/company-reference-data.sql`, the module upgrade scripts including `deploy/upgrade-tax-module.sql`, and no `.env`.

`dompdf/dompdf` is included for quotation, delivery challan, and commercial invoice PDFs. It is pure PHP and uses `ext-dom` and `ext-mbstring`. It does not need `intl`, Imagick, or BCMath. Remote fetching is disabled. A PDF is generated for the request and is not stored under `public/`. DejaVu Sans does not include Bengali script, so a Bangla name may be missing from the PDF. The amount line still shows BDT and the `৳` amount. An issued challan or invoice PDF uses the company snapshot stored at issue and omits the live logo.

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
- `DOCUMENT_MAX_KILOBYTES=10240` and `LOGO_MAX_KILOBYTES=2048` unless the host limits need to be lower
- SMTP values only when mail is ready. Until then leave `MAIL_MAILER=log`

`public/.user.ini` allows 20M uploads. Keep the document limit below `upload_max_filesize` and `post_max_size`. Private files are written to `storage/app/private`. That directory must stay outside the domain document root and be writable by the account user. Do not copy certificates into `public/`.

`SESSION_SECURE_COOKIE` can stay empty. An `https` application URL marks the cookie Secure. If sign-in loops after HTTPS is enabled, the web server is probably forwarding HTTP to PHP. Set `TRUSTED_PROXIES` to the proxy address reported by the host. Do not use `*`.

The account user should own the files. `storage` and `bootstrap/cache` must be writable by that same user. Do not use mode `777`.

## Database

Preferred, once a one-off cPanel cron or Terminal exists:

```text
/usr/local/bin/php /home/ahmadcom/laravel-stage/artisan migrate --force
```

Confirm the PHP 8.4 binary path in the host's PHP selector. Delete the cron as soon as it has run. Do not leave migrate on a schedule.

Until a CLI exists, import these files in phpMyAdmin in order for an empty database:

1. `deploy/mysql-schema.sql`
2. `deploy/reference-data.sql`
3. `deploy/company-reference-data.sql`
4. Administrator SQL from the generator below

Stop if an import reports an existing table or a duplicate key. Do not drop the staging database to force it.

The schema dump includes the `migrations` rows so a later `artisan migrate` does not try to create the same tables again. `company-reference-data.sql` inserts the document categories only. It has no company profile and no certificates. Party, quotation, challan, and invoice permissions are already in `reference-data.sql`. There is no separate party, quotation, or delivery-document reference file because those modules have no seeded business rows.

### Foundation database that is already imported

If milestone 1 was imported and the company tables are not there, do not import `mysql-schema.sql` again. Import `deploy/upgrade-company-module.sql` once. It creates the company tables if they are missing, adds the company permissions and category rows, grants the new permissions without deleting current role grants, and records the two new migration names. Run the whole file in one phpMyAdmin session so the `sql_mode` change applies to the inserts.

If the company module is already imported and the party tables are not there, import `deploy/upgrade-parties-module.sql` once. It creates `parties`, `party_roles`, `party_contacts`, and `party_addresses` if they are missing, adds the party permissions, grants the default role set without deleting current grants, and records the two party migration names. Importing it again does not duplicate those rows. Do not import `mysql-schema.sql` again.

If the party tables are already imported and the quotation tables are not there, import `deploy/upgrade-quotations-module.sql` once, after the parties file. It creates the quotation tables if they are missing, adds the quotation permissions, grants the default role set without deleting current grants, and records the two quotation migration names. Importing it again does not duplicate those rows. A second import puts back a default quotation grant that was removed after the first import. It does not remove a grant. Do not import `mysql-schema.sql` again.

If the quotation tables are already imported and the delivery challan tables are not there, import `deploy/upgrade-delivery-documents-module.sql` once, after the quotations file. It creates the challan and commercial invoice tables if they are missing, including the superseded-line columns, adds the challan and invoice permissions, grants the default role set without deleting current grants, and records the three delivery-document migration names. A database that already imported the first version of these tables receives the history columns and the current-line unique keys. That repair does not delete line rows. Importing the file again does not duplicate those rows. A second import puts back a default challan or invoice grant that was removed after the first import. It does not remove a grant. Do not import `mysql-schema.sql` again.

If the commercial invoice tables are already imported and the tax tables are not there, import `deploy/upgrade-tax-documents-module.sql` once, after the delivery-document file. It creates the tax-rule, Mushak 6.3, VDS, and AIT tables if they are missing, adds their permissions, grants the default role set without deleting current grants, and records the two tax migration names. It does not insert a tax rate. Importing it again does not duplicate those rows. A second import puts back a default tax grant that was removed after the first import. It does not remove a grant. Do not import `mysql-schema.sql` again.

`artisan migrate --force` on an older database does the same work. The permission migration adds the new grants and does not take older permissions away from a customized role.

### First administrator

On a trusted machine, not on the website:

```bash
ADMIN_NAME="Full name" \
ADMIN_EMAIL="person@example.com" \
ADMIN_PASSWORD="a long password" \
php deploy/generate-admin-sql.php
```

Paste the printed SQL into phpMyAdmin as one script. Do not save it in the repository. Unset `ADMIN_PASSWORD` afterwards. The script turns off `NO_BACKSLASH_ESCAPES` for that session and escapes backslashes before apostrophes, so a name such as `A\'B` cannot break out of the `INSERT`.

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
