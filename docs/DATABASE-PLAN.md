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
| `delivery_challan_number_sequences` | One locked counter per Asia/Dhaka calendar year for delivery challans. |
| `delivery_challans` | Header. Number is assigned at issue. Customer text is copied from the accepted revision. |
| `delivery_challan_lines` | Typed delivered quantity for one quotation line. No product key. |
| `delivery_challan_events` | Issue, dispatch, acknowledgment, and void. |
| `commercial_invoice_number_sequences` | One locked counter per Asia/Dhaka calendar year for commercial invoices. |
| `commercial_invoices` | Header. Totals are `DECIMAL(18,2)`. Tax is excluded. |
| `commercial_invoice_lines` | One invoiced slice of a challan line, with the quotation line and the rounded amount. |
| `commercial_invoice_events` | Issue and void. |
| `migrations` | Laravel migration ledger. |

`permissions.permission_group` avoids the reserved word `group`.

There is no `company_settings` table. The single profile holds the currency, financial year, and contact fields. Download history is an `audit_logs` row, not a separate access-log table.

## Party design

The earlier plan used separate customer and supplier tables. That was changed. Tax and document obligations differ by the document, not by cloning the organisation. `parties.id` is the stable key for a quotation. A delivery challan and a commercial invoice point at that quotation and copy its customer text. Receivables, payables, VDS, AIT, and journal lines are not in this schema yet. Opening balances and payments are not in this schema.

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

## Delivery challans and commercial invoices

These tables are created by `2026_11_07_100001_create_delivery_document_tables`.

| Table | Purpose |
| --- | --- |
| `delivery_challan_number_sequences` | One locked counter per Asia/Dhaka calendar year. The row is inserted with `INSERT IGNORE` before `FOR UPDATE`. |
| `delivery_challans` | Header. `number` is null until issue. Customer text is copied from the accepted revision. `company_snapshot` is filled at issue. |
| `delivery_challan_lines` | One row per quotation line on that challan, including rows superseded by a later draft edit. Quantity and the accepted quantity are `DECIMAL(16,4)`. `superseded_at` and `superseded_by` mark a replaced row. Current uniqueness uses stored generated columns that are null once a row is superseded: `(delivery_challan_id, active_position)` and `(delivery_challan_id, active_quotation_line_id)`. |
| `delivery_challan_events` | Issue, dispatch, acknowledgment, and void. Dispatch and acknowledgment are events, not statuses. |
| `commercial_invoice_number_sequences` | Separate year counter for commercial invoices. |
| `commercial_invoices` | Header. `subtotal` and `total` are `DECIMAL(18,2)`. `tax_treatment` is `excluded`. |
| `commercial_invoice_lines` | One row per challan line on that invoice, including rows superseded by a later draft edit. `delivery_challan_line_id` and `quotation_line_id` are both stored. Quantity is `DECIMAL(16,4)`, unit price `DECIMAL(16,2)`, and line total `DECIMAL(18,2)`. Current uniqueness uses `(commercial_invoice_id, active_position)` and `(commercial_invoice_id, active_challan_line_id)`. |
| `commercial_invoice_events` | Issue and void. |

Status is `draft`, `issued`, or `voided`. Only `issued` counts as delivered or invoiced, and only lines with `superseded_at` null are included. A voided document keeps its number and its rows. A draft edit keeps the previous line row. The application does not hard-delete these tables' rows. Foreign keys restrict deletes. `2026_11_07_100003_preserve_delivery_document_line_history` adds the history columns to tables created by the first version of this module.

The lock order inside a transaction is the quotation, then the challans in ascending id, then the invoice, and the year sequence last and only during issue. A draft save checks the issued totals so the user sees the limit early. Issue checks them again. Two drafts may each be saved for the full remainder because a draft does not reserve quantity.

An invoice line amount is the slice quantity times the accepted unit price, rounded half away from zero to two decimal places. The slice that finishes the accepted quantity stores the remaining accepted line amount. That keeps staged invoices equal to the accepted line total. The sum of issued invoice totals cannot pass the accepted quotation total or `9999999999999999.99`.

A challan with a draft or issued invoice line pointing at it cannot be voided. Voiding an invoice does not void the challan. Credit notes and debit notes are not in this schema. Void is the adjustment path for this release.

## Planned tables

These tables are not created yet.

### Adjustments and tax

- `credit_notes`
- `debit_notes`

A challan is not a tax invoice. A commercial invoice is not a Mushak form. An issued invoice is not edited in place. A later credit or debit note would be a new document. It is not built yet.

### Tax

These tables are created by the tax migration.

- `tax_rules` identifies one rule: code, tax type (`output_vat`, `vds`, or `ait`), name, and active or retired status.
- `tax_rule_versions` is the immutable history. `effective_from` is inclusive and `effective_to` is inclusive when set. A null end is open-ended. Approved versions of the same rule cannot overlap. A stored percentage is informational. It is not copied onto a document.
- `mushak_63_documents` is a Mushak 6.3 linked to one commercial invoice. A generated column allows one non-void document per invoice. The buyer, company, invoice number, and any selected rule version are snapshotted. `vat_total` stays null until every line has a typed percentage.
- `mushak_63_lines` stores quantity, unit price, base, the typed VAT percentage, the calculated VAT amount, the VAT-inclusive amount, and an optional supplementary-duty amount. A draft edit keeps the previous line and sets `superseded_at`.
- `mushak_63_events` records issue and void.
- `vds_records` and `ait_records` store a direction, a counterparty, a basis, and an amount entered by the user. They do not feed the Mushak VAT total.

Mushak 6.3, 6.4, 6.5, 6.6, and 6.2 are different documents. Only 6.3 is implemented. No rate is seeded.

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
