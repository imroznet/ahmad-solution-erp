# Architecture

The system is a Laravel modular monolith. Modules will live in the normal Laravel directories until a boundary is large enough to deserve its own namespace. No module is allowed to bypass the accounting and document rules below.

## Request flow

Web routes use the Laravel `web` middleware group, with security headers on every response and an active-account check after the session starts. `/health` and `/up` are registered outside that group so a monitoring request does not open a session or touch MySQL.

Guests can reach sign-in, password reset, and a quotation share link. The share link uses a random token in the path. Every other page requires an authenticated active user and a named permission. The public quotation page does not accept an internal permission and does not show internal notes.

## Authorization

`App\Authorization\PermissionCatalog` is the list of permission slugs. A new screen adds a slug there, a middleware entry of `permission:that.slug`, and a default role grant. Seed data fills an empty role and always restores the full set to `super-administrator`. It does not overwrite a role that already has assignments, so a reviewed change survives the next seed.

An administrator can create users and cannot grant or edit the super administrator role. A person cannot deactivate their own account from the user screen. `SuperAdministratorGuard` rejects a change that would leave the system without an active super administrator.

Company permissions are `company.view`, `company.update`, `company.address.manage`, `company.documents.view`, `company.documents.create`, `company.documents.update`, `company.documents.delete`, `company.documents.download`, and `company.document_categories.manage`. Party permissions are `parties.view`, `parties.create`, `parties.update`, `parties.archive`, `customers.view`, `customers.manage`, `suppliers.view`, `suppliers.manage`, `parties.contacts.manage`, and `parties.addresses.manage`. On a database that already has roles, migrations `2026_11_04_100002_grant_company_module_access` and `2026_11_05_100002_grant_party_module_access` add those default grants. They do not remove a grant that was taken away from an older permission. A later seed still leaves a customized role alone, except the super administrator, who always receives every permission.

Sales roles receive customer access and do not receive supplier access. Accounts roles receive both. The document manager and the viewer receive neither. A route middleware checks that the user holds one of the relevant permissions. The controller then checks the individual party, so a customer permission does not open a supplier-only record.

Quotation permissions are `quotations.view`, `quotations.create`, `quotations.update`, `quotations.submit`, `quotations.approve`, `quotations.send`, `quotations.cancel`, `quotations.share`, and `quotations.respond`. Migration `2026_11_06_100002_grant_quotation_module_access` adds those default grants on an existing database. It does not remove a grant that was taken away from another module. Re-running it restores a removed default quotation grant. The super administrator still receives every permission.

A sales role with `quotations.view` and `customers.view` can open every customer quotation. There is no salesperson ownership row. A role that has `quotations.view` but no customer permission gets an empty list, and another quotation's URL returns 404. The creator of a revision cannot approve it, and the latest submitter cannot approve it. That check is in the quotation workflow, so the super administrator's permission bypass does not waive it.

## Money

`App\Support\Decimal` accepts integers and plain decimal strings. `App\Support\Money` stores currency amounts at two decimal places and rounds a tie away from zero. That rounding rule is an application choice. It is not an NBR rule. `App\Accounting\JournalBalance` checks that unsigned debit and credit totals match. It does not post a journal.

`brick/math` selects GMP, then BCMath, then its pure-PHP calculator. The system status page shows which one is available. The status page does not show the database name, username, password, or application key.

## Audit

`App\Services\AuditLogger` writes sign-in, sign-out, failed sign-in, lockout, inactive-account attempts, user changes, password resets, role-permission changes, company profile and address changes, document category changes, document creation, update, retirement, new file versions, private downloads, party, role, contact, and address changes, and quotation creation, revision, submission, approval, return, send, cancellation, expiry, share creation, share revocation, public view, public PDF, internal PDF, acceptance, and rejection. Keys containing `token`, `password`, or `secret` are removed from the payload. A URL under `/q/` is stored as `/q/[redacted]`. The model refuses updates and deletes. The database user can still change the table directly; a trigger can be added later if the host permits it.

## Company profile

There is one company profile row. Registered office and corporate/head office are different `company_addresses` rows. The application does not copy one into the other. An active registered office and an active corporate office are each limited to one row. Other types may repeat. Closing or replacing an address keeps the previous row and records `supersedes_address_id` on the new row. Only one active address can be the correspondence default, and only one can be the billing default. Those flags are cleared inside a database transaction.

Legal files are `company_document_versions`. A new upload inserts a version. It does not replace the stored file. The saved path is a random name on the private disk. The original filename is display text only. Allowed types and the size limit live in `config/asl.php` (`DOCUMENT_MAX_KILOBYTES`, `LOGO_MAX_KILOBYTES`). PDF, JPG, JPEG, and PNG are accepted. PHP, HTML, SVG, and other executable names are rejected.

Expiry counts use the `expires_on` date in `Asia/Dhaka`. They are calculated when the dashboard or expiry page is opened. No queue worker is required. A document inside 7 days is also inside the longer windows. Documents with no expiry date are omitted. A scheduled email reminder is not part of this module.

TIN and BIN are stored as entered. The form does not check an NBR checksum, because that rule is not encoded here. The July–June dates offered on an empty profile are a suggestion, not a saved assumption.

## Parties

Customers and suppliers are roles on one `parties` row. The same organisation can hold both roles. Creating the second role does not copy the identity. TIN, BIN, and the VAT registration number are optional strings, so a missing identifier is valid and a leading zero is kept. The party currency is BDT.

Contacts and addresses belong to the party. One active contact can be primary, one active address can be the billing default, and one active address can be the shipping default. Those limits are enforced inside a transaction after the party row is locked. There is no database partial unique index, because the SQLite test database and MySQL do not share one syntax for that rule. Closing or replacing an address keeps the old row and stores `supersedes_address_id` on the new row. Archiving a contact or a party keeps the row. An archived party cannot be edited until it is reactivated. There is no hard-delete screen, so a later quotation or invoice can use `parties.id` as a foreign key.

A save that matches another party's name, TIN, BIN, phone, or email asks for confirmation. Confirming creates a separate record. It does not merge the two.

## Quotations

A quotation is for an active party that has the customer role and currency BDT. The header stores that role. Each revision snapshots the identity, contact, and addresses used for that revision. Draft edits refresh the snapshot from the live party. A revision that has left draft does not.

Lines are typed. Quantity uses 4 decimal places and unit price uses 2. Extra digits are rejected. `App\Quotations\QuotationCalculator` multiplies with `App\Support\Decimal`, then rounds the line total with `App\Support\Money`. The quotation total adds those rounded line totals. Tax is excluded. The notice on the revision says the amounts are not a tax invoice, a Mushak form, or a delivery challan. No VAT rate is stored.

The number is allocated from `quotation_number_sequences` with `lockForUpdate` inside the same transaction as the insert. The year is the calendar year in `Asia/Dhaka`. A failed create rolls the number back. The format is `QT-2026-0001`, and the sequence can grow past 9999.

Status changes are limited to the transition map in `App\Quotations\QuotationStatus`. Returning a submission puts the same revision back to draft and keeps the approval row. Customer rejection is a separate `rejected` state. Accepted, rejected, expired, and cancelled do not move again. A new revision can be opened from approved, sent, rejected, or expired. An accepted quotation is closed; a later change is a new quotation. Sending writes `quotation_sends` with `email_not_configured` and does not call the mailer.

The share token is 32 random bytes, encoded as 43 unpadded base64url characters. Only its SHA-256 hash is stored. The link expires, can be revoked, and is rate limited by hash and IP. Missing, expired, and revoked links return the same 404 page. The public response requires a confirmation checkbox and a respondent name. The same decision twice keeps one response row. A different decision is rejected. A link for an older revision stays readable and cannot be accepted. The application redacts the token from the audit URL and from the session's previous URL. The web server's own access log is outside that control and may still contain the customer URL.

The PDF is rendered by `dompdf/dompdf` when an authorized user or a valid share link requests that revision. Remote resource loading and PHP inside the HTML are disabled. The file is not written under `public/`. The library is pure PHP and needs `ext-dom` and `ext-mbstring`. It does not need `intl`, Imagick, or BCMath. The default font is DejaVu Sans, which does not include Bengali script, so a Bangla name may not appear in the PDF. Amounts use the same `৳` western grouping as the screen, and the PDF also names the currency BDT.

## Files

- Private company documents and the company logo are stored on the `local` disk under `storage/app/private`. Downloads go through an authenticated controller, check a permission, and write an audit row. Nothing in that directory may be linked from `public/`.

## Time and currency

The application timezone is `Asia/Dhaka`. The default currency is BDT. Display grouping is the western three-digit form. Lakh and crore grouping is not applied until the company confirms it.

## What is deliberately absent

Challans, invoices, Mushak forms, VDS, AIT, and journals are specified in the database plan and are not tables yet. There will be no product, stock, or warehouse tables. Those future documents should reference `parties.id` and record whether the document used the customer role or the supplier role. A quotation is not one of those documents.
