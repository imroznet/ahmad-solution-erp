# Architecture

The system is a Laravel modular monolith. Modules will live in the normal Laravel directories until a boundary is large enough to deserve its own namespace. No module is allowed to bypass the accounting and document rules below.

## Request flow

Web routes use the Laravel `web` middleware group, with security headers on every response and an active-account check after the session starts. `/health` and `/up` are registered outside that group so a monitoring request does not open a session or touch MySQL.

Guests can reach sign-in and password reset only. Every other page requires an authenticated active user and a named permission.

## Authorization

`App\Authorization\PermissionCatalog` is the list of permission slugs. A new screen adds a slug there, a middleware entry of `permission:that.slug`, and a default role grant. Seed data fills an empty role and always restores the full set to `super-administrator`. It does not overwrite a role that already has assignments, so a reviewed change survives the next seed.

An administrator can create users and cannot grant or edit the super administrator role. A person cannot deactivate their own account from the user screen. `SuperAdministratorGuard` rejects a change that would leave the system without an active super administrator.

Company permissions are `company.view`, `company.update`, `company.address.manage`, `company.documents.view`, `company.documents.create`, `company.documents.update`, `company.documents.delete`, `company.documents.download`, and `company.document_categories.manage`. On a database that already has roles, migration `2026_11_04_100002_grant_company_module_access` adds those default grants. It does not remove a grant that was taken away from an older permission. A later seed still leaves a customized role alone, except the super administrator, who always receives every permission.

## Money

`App\Support\Decimal` accepts integers and plain decimal strings. `App\Support\Money` stores currency amounts at two decimal places and rounds a tie away from zero. That rounding rule is an application choice. It is not an NBR rule. `App\Accounting\JournalBalance` checks that unsigned debit and credit totals match. It does not post a journal.

`brick/math` selects GMP, then BCMath, then its pure-PHP calculator. The system status page shows which one is available. The status page does not show the database name, username, password, or application key.

## Audit

`App\Services\AuditLogger` writes sign-in, sign-out, failed sign-in, lockout, inactive-account attempts, user changes, password resets, role-permission changes, company profile and address changes, document category changes, document creation, update, retirement, new file versions, and private downloads. The model refuses updates and deletes. The database user can still change the table directly; a trigger can be added later if the host permits it.

## Company profile

There is one company profile row. Registered office and corporate/head office are different `company_addresses` rows. The application does not copy one into the other. An active registered office and an active corporate office are each limited to one row. Other types may repeat. Closing or replacing an address keeps the previous row and records `supersedes_address_id` on the new row. Only one active address can be the correspondence default, and only one can be the billing default. Those flags are cleared inside a database transaction.

Legal files are `company_document_versions`. A new upload inserts a version. It does not replace the stored file. The saved path is a random name on the private disk. The original filename is display text only. Allowed types and the size limit live in `config/asl.php` (`DOCUMENT_MAX_KILOBYTES`, `LOGO_MAX_KILOBYTES`). PDF, JPG, JPEG, and PNG are accepted. PHP, HTML, SVG, and other executable names are rejected.

Expiry counts use the `expires_on` date in `Asia/Dhaka`. They are calculated when the dashboard or expiry page is opened. No queue worker is required. A document inside 7 days is also inside the longer windows. Documents with no expiry date are omitted. A scheduled email reminder is not part of this module.

TIN and BIN are stored as entered. The form does not check an NBR checksum, because that rule is not encoded here. The July–June dates offered on an empty profile are a suggestion, not a saved assumption.

## Files

- Private company documents and the company logo are stored on the `local` disk under `storage/app/private`. Downloads go through an authenticated controller, check a permission, and write an audit row. Nothing in that directory may be linked from `public/`.

## Time and currency

The application timezone is `Asia/Dhaka`. The default currency is BDT. Display grouping is the western three-digit form. Lakh and crore grouping is not applied until the company confirms it.

## What is deliberately absent

Quotations, challans, invoices, Mushak forms, VDS, AIT, and journals are specified in the database plan and are not tables yet. There will be no product, stock, or warehouse tables.
