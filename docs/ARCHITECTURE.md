# Architecture

The system is a Laravel modular monolith. Modules will live in the normal Laravel directories until a boundary is large enough to deserve its own namespace. No module is allowed to bypass the accounting and document rules below.

## Request flow

Web routes use the Laravel `web` middleware group, with security headers on every response and an active-account check after the session starts. `/health` and `/up` are registered outside that group so a monitoring request does not open a session or touch MySQL.

Guests can reach sign-in and password reset only. Every other page requires an authenticated active user and a named permission.

## Authorization

`App\Authorization\PermissionCatalog` is the list of permission slugs. A new screen adds a slug there, a middleware entry of `permission:that.slug`, and a default role grant. Seed data fills an empty role and always restores the full set to `super-administrator`. It does not overwrite a role that already has assignments, so a reviewed change survives the next seed.

An administrator can create users and cannot grant or edit the super administrator role. A person cannot deactivate their own account from the user screen. `SuperAdministratorGuard` rejects a change that would leave the system without an active super administrator.

## Money

`App\Support\Decimal` accepts integers and plain decimal strings. `App\Support\Money` stores currency amounts at two decimal places and rounds a tie away from zero. That rounding rule is an application choice. It is not an NBR rule. `App\Accounting\JournalBalance` checks that unsigned debit and credit totals match. It does not post a journal.

`brick/math` selects GMP, then BCMath, then its pure-PHP calculator. The system status page shows which one is available. The status page does not show the database name, username, password, or application key.

## Audit

`App\Services\AuditLogger` writes sign-in, sign-out, failed sign-in, lockout, inactive-account attempts, user changes, password resets, and role-permission changes. The model refuses updates and deletes. The database user can still change the table directly; a trigger can be added later if the host permits it.

## Files

The local disk root is `storage/app/private`, and its public serve route is off. Later document downloads will check a permission and write an access log. Nothing in `storage/app/private` may be linked from `public/`.

## Time and currency

The application timezone is `Asia/Dhaka`. The default currency is BDT. Display grouping is the western three-digit form. Lakh and crore grouping is not applied until the company confirms it.

## What is deliberately absent

Quotations, challans, invoices, Mushak forms, VDS, AIT, journals, and company documents are specified in the database plan and are not tables yet. There will be no product, stock, or warehouse tables.
