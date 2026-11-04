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
- Share tokens are not implemented yet. When they are, store only a hash, set an expiry, and allow revocation.
- Private documents and the company logo are stored under `storage/app/private`. A download checks `company.documents.download` or `company.view` for the logo, and the download is written to the audit log. The stored name is random. Direct URLs under `public/` are not used.
- Customer and supplier records require a signed-in user and a matching permission. A sales role can open customers and cannot open a supplier-only record by changing the URL. Contact and address actions are limited to the party in the URL.
- `/health` and `/up` return a fixed status and start no session.
- Responses send `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy`. HSTS is added only on HTTPS responses.
- Debug output is compiled off unless the environment is `local` or `testing`.

## Secrets

`.env` is gitignored. `.env.example` contains empty passwords and an empty `APP_KEY`. SMTP passwords, database passwords, and the application key must be entered on the server only.

Do not commit certificates, database dumps, or generated administrator SQL. `deploy/generate-admin-sql.php` prints a password hash and refuses to run through a web SAPI. Run it on a trusted machine and discard the output after import.

## Known limits

- Content-Security-Policy is not set yet. Alpine and Vite need a nonce design before a strict policy is safe.
- `intl` and `bcmath` are not confirmed on the hosting account.
- Trusted proxies are opt-in through `TRUSTED_PROXIES`. Do not set the value to `*`.
- The audit table is protected by the application, not by a database trigger. A person with direct database access can still change rows.
