# Security

Report a vulnerability privately to the system owner at Ahmad Solution Ltd. Do not open a public issue containing exploit details, credentials, or customer data.

## Controls in this foundation

- Session cookies are HTTP-only, SameSite=Lax, encrypted, and marked Secure when `APP_URL` uses HTTPS.
- Login is limited to five attempts per email and IP address.
- Passwords are hashed by Laravel. The minimum is 12 characters with upper case, lower case, and a number.
- A password reset, and an administrator setting a new password, replaces the remember token and deletes that account's database sessions.
- There is no public registration.
- Inactive accounts cannot sign in, and an existing session is ended on the next request.
- Authorization is permission based. The super administrator role cannot be reduced.
- Audit rows are append-only in the application. Password fields are redacted before they are written.
- A quotation share link stores only the SHA-256 hash of a random token. The raw token is shown once, expires, and can be revoked. Missing, expired, and revoked links return the same 404. The public page is read-only apart from an explicit acceptance or rejection, which requires confirmation and a typed name. Rate limits apply per token hash and per IP. Audit rows and the session's previous URL redact `/q/{token}`. The token is not an internal permission.
- Private documents and the company logo are stored under `storage/app/private`. A download checks `company.documents.download` or `company.view` for the logo, and the download is written to the audit log. The stored name is random. Direct URLs under `public/` are not used. Quotation PDFs are rendered in the request and are not written under `public/`.
- Customer and supplier records require a signed-in user and a matching permission. A sales role can open customers and cannot open a supplier-only record by changing the URL. Contact and address actions are limited to the party in the URL. A quotation URL requires `quotations.view` and customer access. Another quotation's id returns 404 when that access is missing. The person who created or submitted a revision cannot approve it.
- `/health` and `/up` return a fixed status and start no session.
- Responses send `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy`. HSTS is added only on HTTPS responses.
- Debug output is compiled off unless the environment is `local` or `testing`.

## Secrets

`.env` is gitignored. `.env.example` contains empty passwords and an empty `APP_KEY`. SMTP passwords, database passwords, and the application key must be entered on the server only.

Do not commit certificates, database dumps, or generated administrator SQL. `deploy/generate-admin-sql.php` prints a password hash and refuses to run through a web SAPI. Run it on a trusted machine and discard the output after import.

## Known limits

- Content-Security-Policy is not set yet. Alpine and Vite need a nonce design before a strict policy is safe.
- The customer URL contains the bearer token. Application logs redact it. The host's access log may still record that URL.
- Public acceptance records the typed name. It does not verify the customer's identity and it is not an electronic signature.
- Quotation share links have no password.
- Re-running the quotation upgrade SQL restores a default quotation grant that an administrator removed. It does not delete grants.
- `intl` and `bcmath` are not confirmed on the hosting account.
- Trusted proxies are opt-in through `TRUSTED_PROXIES`. Do not set the value to `*`.
- The audit table is protected by the application, not by a database trigger. A person with direct database access can still change rows.
