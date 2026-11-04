# Database plan

The staging database is `ahmadcom_asl_erp`. The host and password come from `.env`. Monetary columns will be `DECIMAL`, never `FLOAT` or `DOUBLE`.

## Tables in the schema

| Table | Purpose |
| --- | --- |
| `users` | Accounts. `is_active` replaces deletion. Email is stored in lower case. |
| `password_reset_tokens` | Password reset tokens. |
| `sessions` | Database sessions. |
| `cache`, `cache_locks` | Database cache. |
| `jobs`, `job_batches`, `failed_jobs` | Database queue. |
| `roles`, `permissions` | Role catalogue and code-owned permission slugs. |
| `permission_role`, `role_user` | Assignments. Composite primary keys. |
| `audit_logs` | Append-only security and administration history, including private document downloads. |
| `company_profiles` | The single company identity. TIN and BIN are stored as entered. |
| `company_addresses` | Registered office, corporate office, and other addresses. Closed rows are kept. |
| `document_categories` | System and custom categories. System rows are not deleted. |
| `company_documents` | Document metadata. Retirement is a soft delete. |
| `company_document_versions` | One stored file per version. Paths are random names on the private disk. |
| `parties` | One organisation. TIN, BIN, and VAT registration number are strings. `currency_code` is BDT. `status` is active, inactive, or archived. |
| `party_roles` | `customer` or `supplier`. Unique per party and role. A party may have both. |
| `party_contacts` | Several contacts. One active primary contact is enforced in a transaction, not by a partial unique index. |
| `party_addresses` | Registered, office, billing, shipping, project/site, branch, and other. Closed rows stay. `supersedes_address_id` links a replacement. |
| `quotation_number_sequences` | One locked counter per Asia/Dhaka calendar year. |
| `quotations` | Header: number, customer party, role used at creation, current revision, and status. |
| `quotation_revisions` | Immutable commercial snapshot after the revision leaves draft. Totals are `DECIMAL(18,2)`. |
| `quotation_lines` | Hand-typed description, unit, quantity `DECIMAL(16,4)`, unit price `DECIMAL(16,2)`, and rounded line total. No product key. |
| `quotation_approvals` | Submit, approve, and return events for one revision. |
| `quotation_sends` | The send event. `delivery_status` is `email_not_configured` until mail exists. |
| `quotation_share_tokens` | SHA-256 of the share token, expiry, and revocation. The raw token is not stored. |
| `quotation_share_access_logs` | View, PDF, expiry, revocation, and rate-limit events. The raw token is not stored. |
| `quotation_responses` | One acceptance or rejection per revision. |
| `migrations` | Laravel migration ledger. |

`permissions.permission_group` avoids the reserved word `group`.

There is no `company_settings` table. The single profile holds the currency, financial year, and contact fields. Download history is an `audit_logs` row, not a separate access-log table.

## Party design

The earlier plan used separate customer and supplier tables. That was changed. Tax and document obligations differ by the document, not by cloning the organisation. `parties.id` is the stable key for a quotation, and later for a challan, invoice, receivable, payable, VDS or AIT record, and journal line. The document stores which role applied when it was issued. Opening balances and payments are not in this schema.

Archived parties and closed addresses are not soft-deleted. A soft delete would hide them from status filters and from a normal foreign key. There is no delete action. Division, district, and upazila are indexed text fields so a locality can be entered without a gazetteer table.

Name search uses a bounded `LIKE` query and pagination. Exact TIN, BIN, email, phone, status, and district columns are indexed for the duplicate check and the filters.

## Quotation design

A quotation points at `parties.id` and stores `party_role` as `customer`. Each revision copies the legal name, tax identifiers, contact, and billing and shipping address text. Later edits to the party do not change that revision.

`current_revision_id` is an indexed column without a foreign key, so the header and its first revision can be inserted without a circular constraint. The application sets it in the same transaction.

Draft lines may be replaced while the revision is still a draft. After it is submitted, the revision model rejects content changes. A new revision is a new row. Issued lines, revisions, approvals, sends, responses, and the quotation itself cannot be deleted through the application.

Quantity is stored at 4 decimal places and must be greater than zero, up to `9999999.9999`. Unit price is stored at 2 decimal places, from `0.00` to `99999999.99`. A value with extra fractional digits is rejected. The line total is quantity times unit price, then rounded to 2 decimal places with a tie away from zero. The revision total is the sum of those line totals. `subtotal` and `total` are `DECIMAL(18,2)`, so the largest stored total is `9999999999999999.99`. Ten maximum lines fit (`9999999998900000.00`). Eleven maximum lines sum to `10999999998790000.00` and are rejected before insert. MySQL strict mode must also reject a value above the column, rather than truncating it. The columns are not widened. `tax_treatment` is `excluded`. The tax notice is copied onto the revision from configuration. No VAT amount is stored.

The number is `QT-` plus the Asia/Dhaka calendar year plus a sequence of at least 4 digits. The year's `quotation_number_sequences` row is inserted with `INSERT IGNORE` before `FOR UPDATE`, including when that year has no row yet. The increment is not an unlocked `MAX()+1`. Both statements run inside the quotation-create transaction, so a failed create does not consume the number. A deadlock or lock wait is retried up to 5 times on that transaction. A duplicate `quotations.number` is retried up to 5 times after the transaction rolls back. The unique index on `quotations.number` remains the second guard.

A response after `valid_until`, compared as a date in Asia/Dhaka, does not insert `quotation_responses`. For a sent or approved revision that has no response yet, the same locked transaction sets the status to `expired`, fills `expired_at`, and writes one `quotation_expired` audit row. `expired_by` is the internal user, or null when the attempt came from a share link. An existing response is left as it is.

Share rows store `hash('sha256', raw token)`. There is no password hash on a quotation link. A generic password-protected share for other documents is still planned.

## Planned tables

These tables are not created yet.

### Delivery and commercial invoice

- `delivery_challans`
- `delivery_challan_items`
- `commercial_invoices`
- `commercial_invoice_items`
- `credit_notes`
- `debit_notes`

A challan is not a tax invoice. An issued invoice is not edited in place. A quantity or amount change after issue requires a reason and a new adjustment document.

### Tax

- `tax_rules` with type, rate, start date, end date, supply type, legal reference, notes, and active flag
- `mushak_documents`
- `mushak_document_items`
- `vds_certificates`
- `ait_records`

Mushak 6.3, 6.4, 6.5, 6.6, and 6.2 are different documents. The application will not use one generic form for all of them. VAT, VDS, and AIT stay in separate records. No rate is seeded as a statutory default until the current NBR rule is identified and dated.

### Accounting

- `chart_of_accounts`
- `fiscal_periods`
- `journal_entries`
- `journal_lines`
- `payments`
- `receipts`
- `payment_allocations`
- `expense_categories`
- `expenses`

A posted journal's debit total must equal its credit total inside a database transaction. The posted row is then immutable. There is no automatic posting until a mapping has been written and tested.

### Sharing for later documents

- `document_share_tokens` would store a hash, expiry, optional password hash, and revocation time for documents other than quotations
- `document_share_access_logs` for those documents

Quotation shares already use `quotation_share_tokens` and `quotation_share_access_logs`. The raw token is shown once and is never stored. A quotation link does not have a password.

## Explicitly excluded

No tables for products, inventory balances, warehouses, stock movements, or purchase orders.
