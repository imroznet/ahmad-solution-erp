# Roadmap

## Milestone 0 and 1 — foundation

Done in this branch:

- Repository documents
- Laravel 13 application on PHP 8.4
- MySQL configuration through the environment
- Sign-in, password reset, session security, and login throttling
- Eight roles and a code-owned permission list
- User and role administration
- Append-only audit log
- Dashboard counts from the database, with no sample financial figures
- Authenticated system-status page
- Secret-free health checks
- Decimal money helpers and the journal balance check
- GitHub Actions test, analysis, asset build, and cPanel zip
- phpMyAdmin schema path and a CLI migration path

Not done, and not claimed:

- Company profile and addresses
- Legal document storage
- Customers, suppliers, quotations, challans, invoices
- Mushak, VDS, AIT, and the general ledger
- Email delivery beyond the Laravel mail configuration
- Share links
- A strict Content-Security-Policy
- Deployment onto `stage.ahmad.bd`

## Milestone 2 — company profile and documents

- Company profile, including TIN, BIN, trade licence, and RJSC fields
- Separate registered office and head office addresses, plus branch, project, factory, and other
- Document categories, private uploads, versions, expiry dates, and access logs
- Renewal visibility on the dashboard from real expiry dates

## Milestone 3 — parties

- Customers and suppliers
- Multiple contacts and addresses
- BIN and TIN fields
- Authorization tests for sales and accounts roles

## Milestone 4 — quotations

- Manual lines only
- Numbering, revisions that preserve prior versions, approval, acceptance, PDF, and a hashed share link
- No product lookup

## Milestone 5 — challan and commercial invoice

- Challan from an accepted quotation
- Commercial invoice linked to the quotation and challan
- Issued documents frozen, with an audited adjustment path

## Milestone 6 — tax documents

- Effective-dated tax rules
- Mushak 6.3 first, after the current NBR layout is checked
- Other Mushak forms only after each form's purpose is verified
- VDS and AIT kept separate from output VAT

## Milestone 7 — accounting

- Chart of accounts, journals, receipts, payments, and allocations
- Trial balance, ledger, profit and loss, balance sheet, and cash flow
- Posting only through tested mappings
- Immutability of posted journals

## Milestone 8 — mail and operations

- SMTP delivery status, failure log, and retry
- Scheduler for expiry reminders
- Queue drain compatible with cPanel cron

## Hosting checks still open

- `intl` is not confirmed at runtime
- `bcmath` is not confirmed at runtime
- Terminal or SSH is not confirmed
- The staging database password is not part of this repository and must be entered on the server
