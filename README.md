[![Deploy on Railway](https://railway.com/button.svg)](https://railway.com/new/template/osticket-1?utm_medium=integration&utm_source=button&utm_campaign=osticket)

# osTicket on Railway

Railway template for [osTicket](https://osticket.com), the open-source support ticket system: email and web-form tickets, help topics, SLAs, canned replies, a knowledge base and a customer portal.

**What it deploys**

| Service | Source | Notes |
|---|---|---|
| `osTicket` | this repo (`Dockerfile`) | osTicket **v1.18.4** (official release zip, sha256-checked) on `php:8.4.27-apache-trixie` pinned by digest. Public domain, health check `/healthz` |
| `MySQL` | Railway's MySQL | All osTicket data (tickets, users, attachments, settings) with a volume |

The app container holds no data of its own, so redeploys and updates never lose anything.

## First login

1. Deploy and enter **ADMIN_EMAIL** (the only prompt).
2. Open the `osTicket` service's **Variables** tab and copy **ADMIN_USERNAME** (`ostadmin`) and **ADMIN_PASSWORD** (generated).
3. Sign in to the staff panel at `https://YOUR-DOMAIN/scp/` and change the password under your profile. The variable is only used for the first install; changing it later does nothing.
4. Customers open and track tickets at `https://YOUR-DOMAIN/`.

Before customers can email tickets in or get replies, set up your mailboxes (SMTP for sending, IMAP/POP for fetching) under **Admin Panel → Emails**. The installer creates `support@`, `alerts@` and `noreply@` addresses on your admin email's domain as placeholders.

## Variables

| Variable | Default | Purpose |
|---|---|---|
| `ADMIN_EMAIL` | prompt | Admin account email (first install only) |
| `ADMIN_USERNAME` | `ostadmin` | Admin username (first install only; osTicket refuses `admin`) |
| `ADMIN_PASSWORD` | generated | Admin password (first install only) |
| `OSTICKET_SECRET_SALT` | generated | osTicket's `SECRET_SALT`, used to encrypt stored mailbox passwords. **Never change it** after the first boot |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | references to `MySQL` | Database connection |
| `PORT` | `8080` | Port Apache listens on |

Optional, not in the template: `HELPDESK_NAME` (default `Support Center`), `HELPDESK_EMAIL` (default `support@` + your admin email's domain), `TZ` (default `UTC`), all read only at the first install; `OSTICKET_TRUSTED_PROXIES` (osTicket's `TRUSTED_PROXIES`, empty by default).

## How it works

- `railway/start.sh` runs `railway/setup.php` before Apache starts. It waits for MySQL, installs osTicket unattended on the first boot with osTicket's own installer (kept outside the web root, so there is no public `/setup` page), writes `include/ost-config.php` from the variables on every boot, and runs `manage.php upgrade` so a new osTicket version's database changes apply by themselves.
- osTicket's cron (`api/cron.php`: mail fetching, overdue tickets, cleanup) runs every 5 minutes inside the container.
- `/healthz` answers 200 only when the database answers and osTicket is installed.
- Railway terminates HTTPS; osTicket reads `X-Forwarded-Proto` itself.

Plugins and language packs live in the image: fork this repo and add them under `include/plugins` or `include/i18n` with a `COPY` line.

## Updates

Changes reach deployers as Railway "update available" notices when they're merged to `main`. Read [CHANGELOG.md](CHANGELOG.md) before you apply one. osTicket upgrades its database on the next boot. Back up the MySQL volume before a major version update.

## Local build

```sh
docker build -t osticket-railway .
```

Run it next to MySQL 8 with the variables above (`DB_*`, `OSTICKET_SECRET_SALT`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`).

osTicket is GPL-2.0 software by Enhancesoft. This repo only packages it for Railway.
