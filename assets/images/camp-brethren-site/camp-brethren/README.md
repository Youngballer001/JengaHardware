# Elite Academy website and enquiry API

## Local development

Requirements: Node.js 20.11 or newer and npm.

```powershell
npm install
npm start
```

Open <http://127.0.0.1:3000>. The website and API share one origin, so no
permissive cross-origin configuration is needed. Enquiries are stored in
`data/inquiries.sqlite`; set `DATABASE_PATH`, `PORT`, or `HOST` to change the
defaults.

## Staff dashboard

The staff dashboard is available at `/admin`. It requires environment
credentials and is disabled until both `ADMIN_USERNAME` and `ADMIN_PASSWORD`
are configured. Passwords must be at least 16 bytes; use a unique random
password and store it in a secret manager or a private `.env` file. Never commit
real credentials.

For local development, set the credentials before starting the server:

```powershell
$env:ADMIN_USERNAME = "school-office"
$env:ADMIN_PASSWORD = "replace-with-a-unique-random-password"
npm start
```

Staff sessions use random in-memory session IDs in `HttpOnly`, `SameSite=Strict`
cookies and expire after eight hours. Production cookies are marked `Secure`,
so production staff access must use HTTPS. Session data is held in process
memory; restarting the app signs out staff. Keep the service to one app
instance unless session storage is moved to a shared store.

## Email notifications

SMTP notifications are optional. Set all of the following server environment
variables to enable them; if any are missing, notifications remain disabled:

- `SMTP_HOST`
- `SMTP_PORT` (default `587`)
- `SMTP_SECURE` (`true` for implicit TLS; port `465` is secure by default)
- `SMTP_USER`
- `SMTP_PASSWORD`
- `SMTP_FROM`
- `NOTIFICATION_EMAIL`

Notification messages contain the enquiry type and its reference only, not
parent contact details or message text. The enquiry is saved even if mail
delivery fails; its notification status is visible in the staff dashboard.
Configure a real SMTP account and verify mail delivery before relying on alerts.

## API

- `GET /api/health` checks that the API and database are available.
- `POST /api/inquiries` validates, rate-limits, and stores an admission or
  contact enquiry.
- `GET /api/admin/session` returns dashboard configuration and session status.
- `POST /api/admin/session` signs in with a same-origin username and password.
- `POST /api/admin/logout` signs out and clears the session cookie.
- `GET /api/admin/inquiries?limit=25&offset=0` lists enquiries to signed-in
  staff. Page size is capped at 100. There is no public read endpoint.

Successful submissions return `201` with a reference. Invalid input returns
`400`; submissions are limited to five per client IP per 15-minute window.
Never expose the database file or staff credentials publicly.

## Docker deployment

Docker Compose binds the app to `127.0.0.1` on the host and keeps database files
in persistent data and backup volumes. Put it behind a public HTTPS reverse
proxy; do not expose port 3000 directly to the internet.

1. Copy `.env.example` to `.env`.
2. Replace `ADMIN_USERNAME` and `ADMIN_PASSWORD` with unique credentials.
3. Add the complete SMTP settings if email notifications are wanted.
4. Build and start the service:

```powershell
Copy-Item .env.example .env
docker compose up --build -d
docker compose ps
```

Configure an HTTPS proxy to forward requests to `127.0.0.1:3000`, preserving
the public `Host` header. For example, with Caddy, add this to its site
configuration, replacing the example hostname with a domain whose DNS points
to the server:

```text
school.example.org {
    reverse_proxy 127.0.0.1:3000
}
```

Caddy can provision and renew TLS certificates automatically. Confirm HTTPS,
staff sign-in, SMTP delivery, persistent storage and backups before collecting
real enquiries. Create a consistent SQLite snapshot with:

```powershell
docker compose exec -T web npm run backup
```

Snapshots are written to a separate Docker volume and old snapshots are removed
after 30 days by default (`BACKUP_RETENTION_DAYS` can be changed). The server
also creates a snapshot on startup and every 24 hours; change
`BACKUP_INTERVAL_HOURS` to adjust the interval. Regularly copy backups off the
server and test a restore. A second volume on the same host is not a disaster
recovery backup. Do not copy a live database file without using a SQLite-aware
backup method or accounting for its WAL files. Protect backups and define a
retention/deletion policy because enquiries contain personal data.

## Tests

```powershell
npm test
npm audit --omit=dev
```

Tests use in-memory databases and a fake SMTP transport, and do not send mail or
create the local enquiry database.
