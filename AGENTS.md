# Agent notes

This file replaces the Laravel skeleton's Boost bootstrap. Laravel Boost is not installed. The production package must stay small, and this repository's rules are the ones that matter.

## Product boundaries

- Build an internal modular monolith for Ahmad Solution Ltd.
- Do not modify the legacy CMS repository.
- Do not add a product catalogue, inventory, stock, warehouse, or procurement module.
- Customers and suppliers share one party record. Add the other role to that record instead of creating a second organisation.
- Quotation lines are typed by hand.
- A quotation is not an invoice. A delivery challan is not a tax invoice. A commercial invoice and Mushak 6.3 are different documents.
- Do not claim that a tax form is NBR-compliant until the current prescribed layout has been checked and the uncertainty is written down.
- Do not invent a VAT rate. Tax rules must be effective-dated configuration.
- Posted journals are immutable. Corrections are reversals or adjustments.
- Do not deploy to cPanel from CI.

## Engineering rules

- PHP 8.4 and Laravel 13. Commit `composer.lock`.
- Money and tax math go through `App\Support\Money` and `App\Support\Decimal`. Never use `float` for money.
- Permissions are registered in `App\Authorization\PermissionCatalog`. Runtime screens may change which role receives them. The super administrator role always keeps every permission.
- Private files stay on the `local` disk under `storage/app/private`. Downloads require a permission. The disk's public `serve` route is disabled.
- Health checks are `/health` and `/up`. They must not read the database and must not return secrets, versions, or exception text.
- Do not add a public Artisan runner or an unauthenticated migration route.
- `.env`, passwords, certificates, database dumps, and customer records stay out of Git.
- `APP_ENV=staging` and `APP_ENV=production` force debug output off.

## Hosting

Shared cPanel. PHP 8.4 is confirmed. `intl` was reported enabled in the selector and `extension_loaded('intl')` returned false, so the application must not require it. `bcmath` is preferred and not confirmed; the decimal library has a pure-PHP fallback. There is no confirmed Terminal, SSH, Composer, or Node on the account. Build artifacts in GitHub Actions.

## Checks before a milestone is finished

Run the test suite, Pint, and PHPStan. Review new migrations, authorization, and anything that would be reachable without a session. Update the roadmap with what remains unfinished.

## Cursor Cloud specific instructions

- The Cloud Agent image starts without PHP. The environment install adds PHP 8.4 from the ondrej PHP PPA, Composer, and MySQL 8, then runs `composer install`, `npm ci`, and `npm run build`. Node 22 is already on the image.
- `.env.example` names the staging database. The environment points `.env` at local MySQL instead: database `asl_erp`, user `asl`, password `secret` (the same values as GitHub Actions), host `127.0.0.1`, port `3306`. The app is served at `http://127.0.0.1:8000`.
- On boot, MySQL is started, the `asl_erp` database is created if needed, and `php artisan migrate --force` plus `php artisan db:seed --force` run before `php artisan serve`.
- `php artisan test` uses SQLite in memory and does not need MySQL. Style check: `vendor/bin/pint --test`. Static analysis: `vendor/bin/phpstan analyse --memory-limit=1G`.
- Create a local administrator in the shell only. Set `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` (at least 12 characters, with mixed case and a number), run `php artisan asl:create-admin --no-interaction`, then unset `ADMIN_PASSWORD`. Do not write that password into `.env`. If those three variables are present at boot and the email is new, the boot script creates the account.
- `GET /health` and `GET /up` do not read the database. A response of `Service is available.` is the healthy body.
