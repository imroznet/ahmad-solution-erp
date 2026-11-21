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
- Approval by someone other than the revision's creator and latest submitter. A role with `quotations.approve_own` may approve its own quotation. The super administrator has that permission. Other roles receive it only from the Roles page
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

Done in this branch:

- A source register and a Mushak 6.3 field map for the owner-confirmed PDF, with the 2026 SRO and withholding-rule index entries recorded separately from anything the gazette text has not confirmed
- Effective-dated tax rules for output VAT, VDS, and AIT. Approved text is frozen. An amendment is a new version. Overlapping active rules for the same type and classification are rejected. A blank classification is not a wildcard. No rate is seeded, and a rule does not fill a Mushak percentage
- Mushak 6.3 as its own document on an issued commercial invoice. Seller BIN and address come from the company profile and are snapshotted at issue. The buyer can be the invoice customer, an existing party, or a one-off name that does not create a party. Issued rows cannot be edited. Void keeps the row. The PDF is private
- A typed VAT percentage, including an explicit zero. A blank percentage does not calculate. VAT and the VAT-inclusive final amount calculate from that percentage. Editing the final amount reverse-calculates the base and keeps the percentage and the adjustment. `final = base + VAT + adjustment`. Precision 2, 3, or 4 and the selected rounding method are stored. A signed rounding adjustment is separate from VAT. BDT on the form is shown at two decimal places
- VDS and AIT records in both directions, with typed basis and amount. They are not added to output VAT. No Mushak 6.6 or withholding certificate is generated, because those forms were not verified
- Milestone 6 hardening (merged as PR #22): a commercial invoice cannot be voided while a linked Mushak 6.3 is issued — the Mushak must be voided first, with a reason; a draft Mushak does not block the void. A Mushak draft update reloads the row under `lockForUpdate()` inside the transaction and rechecks that it is still a draft, so a concurrent issue cannot be overwritten; the same recheck was added to Mushak void. The draft Mushak form now calculates live: base, rate, final, adjustment, precision, and rounding changes call `POST /tax/mushak/calculate`, which runs the same server-side `VatLineCalculator` that runs on save. The browser does no money arithmetic. Supplementary duty has no live handler and never changes the driver, base, or VAT. The rendered Mushak PDF was checked against the owner-confirmed PDF (SHA-256 matches the source register): seller, buyer, amounts, and the supplementary note are correct, and the table now has the official form's total row and prints the rate as `15%` instead of `15.0000%`. Bengali labels are still not rendered — the PDF says so in plain text — The later PDF section replaces that disclosure with the Bangla title and rule line on the tax PDFs. A follow-up (branch `muse/mushak-preview-race-fix`) fixed the live preview race Codex found on PR #22: every edit now invalidates in-flight previews at once — a generation counter incremented on input plus an AbortController, with the generation check as the authority — so only the latest valid response can update the form, and a stale forward response can no longer overwrite a newer final-amount edit (or the reverse). The preview protocol lives in `resources/js/mushak-preview.js`, covered by regression tests run with `npm run test:js` (also run in CI).

Not done, and not claimed:

- A statutory VAT rate, exemption, threshold, or specific-tax amount
- NBR compliance of a calculated document
- Mushak 6.6, or any other Mushak form
- An official VDS or AIT certificate
- Journals, receivables, payables, receipts, or payments
- Email, filing, or deployment
- Bengali on quotation, challan, and commercial invoice PDFs. Those three stay on Dompdf. The tax PDFs are in the later section
- A printed check of the Mushak 6.3 page against the current prescribed form after the owner approves the emblem taken from the confirmed form file

## PDF letterhead and quotation tax

Done in this branch:

- An issued challan or invoice stores a private copy of the company logo and prints that copy. A later logo change does not change the issued PDF. A snapshot from before this copy has no logo
- Quotation, challan, and invoice PDFs share a compact letterhead and a 10mm page margin. The Mushak PDF keeps the official-form header
- A quotation revision can be VAT excluding or VAT including. Excluding keeps the line sum as the total and stores no tax. Including calculates VAT and a generic Tax % from what the user types, plus an optional adjustment that needs a reason when it is not zero. The page does not calculate the money. Rates are not prefilled. Blank is not zero
- The commercial invoice still excludes tax. Its ceiling is the quotation subtotal, the line base
- Mushak 6.3 PDF layout follows the owner-confirmed form: emblem, MUSAK-6.3 box, centred seller, buyer and issue details, the ten columns, the total row, and the authorised-person lines. Calculated values are unchanged. The English font disclosure on that page was removed in the later PDF section. The layout is not an NBR certification

Not done, and not claimed:

- Owner approval of the government emblem image before merge
- Confirmation of the generic "Tax %" label. It is not treated as a named tax
- Quotation tax at 3 or 4 decimal places. Quotation money columns stay at 2 decimal places, half up
- Staging deploy. This branch does not upload to cPanel

## Mobile layout, sidebar, and measurement units

Done in this branch:

- Wide tables scroll inside the page at phone and tablet width. The page itself does not scroll sideways. Line forms stack to one column below the desktop breakpoint. Buttons, links, and the menu meet a 44px tap height on smaller screens. The mobile menu closes after a link is tapped
- The sidebar groups the same links under Sales, Tax, Parties, Company, and Administration. Each link keeps its current permission check. Dashboard stays above the groups. Units sits under Company. Approvals stays under Administration and is still gated by `quotations.approve`. A desktop control collapses the bar to icons and remembers that choice in the browser
- A measurement-unit list can be viewed and managed with `units.view` and `units.manage`. Quotation, challan, invoice, and Mushak draft lines choose an active symbol or type another unit. The stored value is still the text on the line. An inactive unit is not offered for a new choice, and a line that already uses it is left as it is. There is no item, price, or stock list

Not done, and not claimed:

- A staging upload. This branch does not upload to cPanel
- A product catalogue. Lines are still typed by hand

## PDF header, addresses, and Bengali on the tax invoice

Done in this branch:

- The shared letterhead name is 14px and bold. The meta lines stay 8px, the body stays 10px, and the page margin stays 10mm
- Each company address has Show on PDFs. A new PDF prints every active address with that flag, one labelled line each. Active registered and corporate addresses start with the flag on. Turning every flag off leaves the address off the letterhead. An issued challan, invoice, or Mushak keeps the address stored at issue
- A customer address on a quotation, challan, or commercial invoice PDF is one flowing line. A post office and postcode print as `Name-Code` when both were stored. An older snapshot without a post office does not gain one
- Mushak 6.3 and the VDS/AIT PDFs are rendered by mPDF 8.3.1. Quotation, challan, and commercial invoice PDFs stay on Dompdf. The Mushak title is `কর চালানপত্র`, and the Bangla rule line sits with the English rule line. The English font disclosure is gone. This is not an NBR certification
- Noto Sans Bengali was not registered. mPDF 8.3.1 throws while reading that font's OpenType coverage. Bengali runs use FreeSerif, the face mPDF already selects for Bengali, with its OpenType layout enabled. Latin text stays DejaVu Sans
- At desktop width the sidebar is a viewport-height column with its own scrollbar. The phone drawer is unchanged

Not done, and not claimed:

- Bengali words on quotation, challan, or commercial invoice PDFs
- Noto Sans Bengali as the PDF face. A later renderer that can read its tables would be a new test
- An NBR certification, or a VAT rate chosen by the system
- A staging upload. `gd` and `mbstring` on stage.ahmad.bd still have to be confirmed before this mPDF code is deployed. This branch does not upload to cPanel

## Public site maintenance and Mushak title

Done in this branch:

- The Mushak 6.3 title prints `Tax Invoice` and `কর চালানপত্র` on one line. Both rule lines stay as they were
- An administrator can hide the public website for maintenance. The switch is audited. The default is visible. The ERP and quotation share links stay available
- The same screen turns the public contact form on or off, and can require the reCAPTCHA checkbox. The form defaults to on. reCAPTCHA defaults to off. The keys stay in the environment
- The public site is a separate application and database. The ERP writes the flag file only when `PUBLIC_SITE_MAINTENANCE_FILE` is set. The public-site candidate reads `/home/ahmadcom/ahmad-private/public-site-maintenance` (or `AHMAD_MAINTENANCE_FILE`) on each public request and answers HTTP 503 while the file contains `1`. Until that file is shared on the host and the candidate is deployed, saving the switch does not change ahmad.com.bd

Not done, and not claimed:

- A live maintenance page on ahmad.com.bd. The host file is not confirmed from this environment
- An NBR certification, or a VAT rate chosen by the system
- A staging upload

## Milestone 7 — accounting

Phase 1 is merged:

- Chart of accounts for assets, liabilities, equity, income, and expenses. Codes use 1xxx, 2xxx, 3xxx, 4xxx, and 5xxx
- Locked system accounts: Cash in Hand, Accounts Receivable, Accounts Payable, Output VAT Payable, VDS Receivable, AIT Receivable, Sales Revenue, and Retained Earnings. A system account cannot be renamed, retyped, deactivated, or deleted
- Bank and MFS accounts are assets added, edited, and deactivated on the Bank and MFS screen. None are seeded. Cash in Hand remains the only cash account created by the module. Each bank or MFS account stores the date it was opened
- Financial years are entered and edited. The software does not assume a July–June year. The company profile holds the current year, and other years are added on the Financial years screen
- Opening balances are balanced journals with an opening date. The go-live cut-off is the company registration date, 15 May 2025, stored on the profile when that date was empty. Each account is opened once. A bank or MFS opening cannot be dated before the account was opened. The first bank account opened on 2 November 2026, so that account's opening date cannot be earlier. The account itself is not seeded
- Draft and posted journals. A posted journal is not edited or deleted. A reversal is a new journal that negates it
- Numbers `JE-{Asia/Dhaka year}-{sequence}`. A failed create does not keep the number
- Trial balance and general ledger from posted journals. Amounts are BDT at two decimal places
- Permissions 75–81, 84, and 85. The administrator and the existing accountant receive them. The accounts manager does not. No new role is seeded
- A supplier cost is a manual journal to an expense account. There is no supplier-bill module
- The ledger does not accrue interest. A Shariah-compliant profit or markup is a journal entered when the bank or the owner instructs. A treatment a government rule requires is entered the same way

Phase 2 is in this branch:

- Issuing a commercial invoice posts Accounts Receivable to Sales Revenue for the invoice total, with the customer on the receivable line. A zero total posts nothing. Void reverses that journal when one exists. An invoice issued before this phase has no journal, and voiding it still works
- Issuing a Mushak 6.3 posts Accounts Receivable to Output VAT Payable for the stored VAT, rounded half-up to two decimal places. Explicit zero VAT posts nothing. Void reverses that journal when one exists. A delivery challan posts nothing
- Customer receipts, numbered `RCPT-{Asia/Dhaka year}-{sequence}`. A receipt posts when it is recorded: the settlement account, VDS Receivable, and AIT Receivable, credited to Accounts Receivable. VDS and AIT are typed. They are not calculated, and they are not credited to Output VAT Payable. A recorded receipt is not edited. Void reverses its journal
- Allocations apply a receipt to issued invoices of the same customer. Partial allocation leaves the remainder as an advance on Accounts Receivable. There is no advance account. An invoice cannot be voided while a live allocation exists
- Receivables aging as at Asia/Dhaka today, from issued invoices and Mushak VAT, opening receivable lines, manual receivable adjustments, and receipts that are not void. A later date is left out. The customer total matches posted Accounts Receivable as at that day
- Permission 82, `receipts.create`, for the administrator and the existing accountant. The accounts manager does not receive it. There is no `receipts.view`
- Invoices and Mushak forms issued before this phase are not back-posted

Phase 3 is in this branch:

- Supplier payments, numbered `PAYM-{Asia/Dhaka year}-{sequence}`. A payment posts when it is recorded: debit Accounts Payable for the supplier, credit the settlement account. There is no VDS or AIT line. A recorded payment is not edited. Void reverses its journal
- Allocations apply a payment to posted manual journals that credit Accounts Payable for the same supplier. Partial allocation leaves the remainder as an advance on Accounts Payable. There is no advance account. A journal cannot be reversed while a live allocation exists
- Payables aging as at Asia/Dhaka today, from those journals, opening payable lines, payable adjustments, and payments that are not void. A later date is left out. The supplier total matches posted Accounts Payable as at that day
- Profit and loss, balance sheet, and direct cash flow. Cash flow uses receipts and payments that are not void, classified by the operating, investing, or financing choice stored on the document. A manual journal that moves cash stays out of cash flow
- Retained Earnings prefers code `3000`. An upgrade that already has an equity account on that code uses that account. If the code belongs to another account, Retained Earnings takes the next free code from `3001` upward. Closing a past financial year posts a balanced carry-forward on the first day of the next year and then rejects postings in the closed year. Posting and closing lock the same financial-year row. The year that contains today cannot be closed, and a year cannot be closed until a later year exists. Reopening is limited to the super administrator and reverses the carry-forward
- Permissions 83, `payments.create`, and 86, `periods.manage`, for the administrator and the existing accountant. The accounts manager does not receive them. There is no `payments.view`

Not done, and not claimed:

- The entry that applies VDS at filing. VDS stays a receivable until that later entry
- Fixed assets, depreciation, and payroll
- A seeded bank, branch, account number, or mobile number
- An NBR certification, or a VAT rate chosen by the system
- Back-posting old invoices
- Supplementary duty posted to the ledger

## Milestone 8 — mail and operations

- SMTP delivery status, failure log, and retry
- Scheduler for expiry reminders
- Queue drain compatible with cPanel cron

## Hosting checks still open

- `intl` is not confirmed at runtime
- `bcmath` is not confirmed at runtime
- Terminal or SSH is not confirmed
- The staging database password is not part of this repository and must be entered on the server
