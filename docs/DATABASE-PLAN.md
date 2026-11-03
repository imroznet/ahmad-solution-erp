# Database plan

The staging database is `ahmadcom_asl_erp`. The host and password come from `.env`. Monetary columns will be `DECIMAL`, never `FLOAT` or `DOUBLE`.

## Tables in this milestone

| Table | Purpose |
| --- | --- |
| `users` | Accounts. `is_active` replaces deletion. Email is stored in lower case. |
| `password_reset_tokens` | Password reset tokens. |
| `sessions` | Database sessions. |
| `cache`, `cache_locks` | Database cache. |
| `jobs`, `job_batches`, `failed_jobs` | Database queue. |
| `roles`, `permissions` | Role catalogue and code-owned permission slugs. |
| `permission_role`, `role_user` | Assignments. Composite primary keys. |
| `audit_logs` | Append-only security and administration history. |
| `migrations` | Laravel migration ledger. |

`permissions.permission_group` avoids the reserved word `group`.

## Planned tables

These tables are not created yet.

### Company

- `company_profiles`
- `company_addresses` with a type of registered office, head office, branch, project office, factory, or other
- `company_settings`

Registered office and head office are separate address rows. A document template chooses an address subject to the statutory rule for that document.

### Legal documents

- `document_categories`
- `company_documents`
- `document_versions`
- `document_access_logs`

Files stay on the private disk. The database stores the path and metadata, not a public URL.

### Parties

- `customers`
- `suppliers`
- `party_contacts`
- `party_addresses`

Customers and suppliers stay in separate tables. They are not one polymorphic party in the first version, because their tax and document obligations differ.

### Quotations

- `quotations`
- `quotation_items`
- `quotation_revisions`
- `quotation_approvals`
- `quotation_acceptances`

A revision inserts a new version. It does not update the issued rows of the previous version. Line items are free text. There is no product foreign key.

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

### Sharing

- `document_share_tokens` stores a hash, expiry, optional password hash, and revocation time
- `document_share_access_logs`

The raw token is shown once and is never stored.

## Explicitly excluded

No tables for products, inventory balances, warehouses, stock movements, or purchase orders.
