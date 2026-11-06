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

- Quotations, challans, invoices
- Mushak, VDS, AIT, and the general ledger
- Email delivery beyond the Laravel mail configuration
- Share links
- A strict Content-Security-Policy
- Deployment onto `stage.ahmad.bd`

## Milestone 2 — company profile and documents

Done in this branch:

- One company profile, including TIN, BIN, trade licence, RJSC, logo, and financial-year dates
- Separate registered office and corporate/head office addresses, plus branch, project, factory, and other
- Address history: close and replace keep the previous row
- One active correspondence default and one active billing default
- Document categories, private uploads, versions, and soft-delete retirement
- Expiry counts for expired and 7, 15, 30, and 60 days, from the database, in Asia/Dhaka
- phpMyAdmin category SQL and an upgrade script for a foundation database

Not done in this milestone:

- Email reminders for expiry. The dashboard is the reminder until cron mail is added.
- Customers, suppliers, quotations, challans, invoices
- Mushak, VDS, AIT, and the general ledger
- Share links
- A strict Content-Security-Policy
- Deployment onto `stage.ahmad.bd`

## Milestone 3 — parties

Done in this branch:

- One party record, with customer and supplier roles that can both be present
- Multiple contacts, one active primary contact, and archived contacts kept
- Multiple addresses, billing and shipping defaults, and address history
- TIN, BIN, and VAT registration number stored as text, including a blank value
- Duplicate warning without an automatic merge
- Sales access to customers only, and accounts access to customers and suppliers
- phpMyAdmin upgrade script for a database that already has the company module

Not done in this milestone:

- Quotations, challans, invoices, receivables, and payables
- Opening balances or accounting ledgers
- Email expiry reminders
- Share links
- A strict Content-Security-Policy
- Deployment onto `stage.ahmad.bd`

## Milestone 4 — quotations

Done in this branch:

- Draft quotations for an active BDT customer, with the customer role stored on the quotation
- A customer, contact, and address snapshot on each revision, so a later party edit does not rewrite that revision
- Hand-typed lines: description, unit, quantity, and unit price. No product catalogue
- `QT-{Asia/Dhaka year}-{sequence}` numbers allocated under a row lock, with a unique number index. The first number of a Dhaka year inserts that year's row with `INSERT IGNORE` before the lock. A failed create does not consume the number
- Quantity at 4 decimal places and money at 2. Extra digits are rejected. A line total rounds half away from zero, and the quotation total sums those rounded lines. A total above `9999999999999999.99` is rejected. Tax is excluded and is not calculated
- A response after the validity date expires an unanswered sent or approved revision and does not store a response. The validity date itself can still be accepted or rejected
- States: draft, submitted for approval, approved, sent, accepted, rejected, expired, and cancelled. Only the listed transitions are allowed
- A new revision from an approved, sent, rejected, or expired quotation. The previous revision, its lines, snapshot, terms, and totals stay
- Approval by someone other than the revision's creator and latest submitter, including a super administrator
- An explicit send record. Email is not sent
- A share link whose raw token is shown once. The database stores the SHA-256 hash, an expiry, and revocation. The public page can record one acceptance or rejection for the current sent revision
- A PDF of the revision being viewed, rendered when requested and not stored under `public/`

Not done, and not claimed:

- A scheduled job that expires quotations without an accept, reject, or manual expiry action
- Email delivery of a quotation
- A share-link password
- Revising an accepted quotation. A change after acceptance is a new quotation
- Accepting a superseded revision
- NBR compliance, a VAT rate, Mushak, VDS, or AIT
- Receivables, payables, and journal posting
- A product, stock, warehouse, or procurement module
- A strict Content-Security-Policy
- Deployment onto `stage.ahmad.bd`

## Milestone 5 — challan and commercial invoice

Done in this branch:

- A delivery challan from an accepted quotation and its accepted revision, with the customer and line text copied onto the challan
- Partial deliveries and more than one challan for one quotation. Typed quantities only. Issued quantities cannot pass the accepted quantity. A draft does not reserve that quantity
- Separate issue, dispatch, and acknowledgment. A PDF does not record dispatch or receipt
- A commercial invoice against that quotation and one or more issued challans. Each invoice line points at the challan line and the quotation line, so the delivered quantity can be traced
- Staged invoicing. Issued quantities cannot pass the delivered quantity, and issued amounts stay within the accepted line and quotation totals, including the remainder left by earlier rounding
- `DC-{Asia/Dhaka year}-{sequence}` and `CI-{year}-{sequence}` numbers allocated when the document is issued. A draft has no number. A failed issue does not consume the number
- Issued content and the company snapshot stay as they were. A controlled void keeps the document. A challan whose current lines are used by a draft or issued invoice cannot be voided until those invoices are voided
- A draft edit keeps every previous line row, with the actor and time, and the update audit stores both line sets. Current totals and PDFs use only the latest lines

Not done, and not claimed:

- Email of a challan or invoice
- A share link for either document
- Credit notes and debit notes. Void is the adjustment path in this release
- Inventory, products, stock, warehouses, procurement, or stock movements
- VAT, a VAT rate, Mushak, VDS, AIT, receivables, payables, receipts, or journals
- NBR compliance. Tax is excluded and is not calculated
- Per-customer assignment beyond the quotation visibility check. Sales can see every customer document
- Adding a vehicle after issue. Acknowledgment does not require dispatch
- A scheduled job that expires quotations
- Deployment onto `stage.ahmad.bd`

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
