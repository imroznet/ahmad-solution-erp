# Ahmad Solution Ltd. Business Management System

Internal business management system for Ahmad Solution Ltd., Bangladesh. This release has the application foundation, the company profile, customer and supplier records, quotations, delivery challans, and commercial invoices. A customer and a supplier can be the same organisation. A quotation is a priced offer with hand-typed lines. It is not an invoice, a delivery challan, or a tax form. A delivery challan is not a commercial invoice. A commercial invoice is not a tax invoice or a Mushak form. Tax documents and accounting are planned and are not implemented yet.

This application is separate from the legacy public website. Do not migrate or modify that site as part of this project.

## Stack

- PHP 8.4 and Laravel 13
- MySQL 8
- Blade, Tailwind CSS, Alpine.js, and Vite
- PHPUnit, Laravel Pint, and Larastan

Decimal amounts use `brick/math`. The library uses GMP or BCMath when either extension is loaded, and a pure-PHP calculator otherwise. Do not use floating-point arithmetic for money.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate
php artisan db:seed
php artisan asl:create-admin
```

The `.env.example` file targets the staging MySQL database. Its password is empty on purpose. Tests use SQLite in memory and do not need MySQL.

`asl:create-admin` prompts for the administrator. For a non-interactive shell, set `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` in the environment, run `php artisan asl:create-admin --no-interaction`, and then unset `ADMIN_PASSWORD`. Do not store that password in `.env`.

## Tests and checks

```bash
php artisan test
vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=1G
```

## Deployment

GitHub Actions builds a zip archive with production dependencies and compiled assets. It does not deploy to the server. The archive is a workflow artifact.

Read [docs/CPANEL-DEPLOYMENT.md](docs/CPANEL-DEPLOYMENT.md) before uploading anything. The domain document root must be the Laravel `public` directory.

## Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [Database plan](docs/DATABASE-PLAN.md)
- [cPanel deployment](docs/CPANEL-DEPLOYMENT.md)
- [Roadmap](docs/ROADMAP.md)
- [Security](SECURITY.md)
- [Agent notes](AGENTS.md)
