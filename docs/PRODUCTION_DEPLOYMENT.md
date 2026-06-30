# Production Deployment — DentalFinance

Operational guide for deploying DentalFinance to `dentalfinance.eu` on IONOS VPS L+ with Docker Compose. Architecture decision: **ADR-037** in `docs/DECISIONS.md`.

## 1. Architecture overview

| Component | Technology |
|---|---|
| Domain | `dentalfinance.eu` (canonical), `www` → apex redirect |
| Server | IONOS VPS L+, Ubuntu 24.04 LTS |
| Orchestration | Docker Compose (`compose.production.yml`) |
| Web | FrankenPHP 1.9 + Caddy (automatic HTTPS) |
| App | Laravel 13, PHP 8.3, single image for web/worker/scheduler |
| Database | PostgreSQL 16 (internal network only) |
| Cache / Session / Queue | PostgreSQL (`database` driver) — **no Redis in v1** |
| Registry | GitHub Container Registry (GHCR), tag = full Git commit SHA |
| CI/CD | GitHub Actions (`ci.yml`, `deploy-production.yml`) |
| SMTP | Resend (`smtp.resend.com:587`) |

```
Internet → :443/:80 → app (FrankenPHP/Caddy)
                          ├── worker (queue:work database)
                          ├── scheduler (schedule:work)
                          └── database (PostgreSQL, internal only)
```

## 2. Prerequisites

* IONOS VPS L+ with Ubuntu 24.04 LTS
* Domain `dentalfinance.eu` at IONOS
* GitHub repository with Actions enabled
* Resend account (SMTP relay)
* SSH key pair for deploy user

## 3. IONOS VPS selection

VPS L+ provides sufficient CPU/RAM for Laravel + PostgreSQL + Excel imports on a single node (v1). No separate DB server in v1.

## 4. Ubuntu initial setup

```bash
sudo apt update && sudo apt upgrade -y
sudo timedatectl set-timezone Europe/Berlin
```

## 5. SSH key

Generate locally:

```bash
ssh-keygen -t ed25519 -C "dentalfinance-deploy" -f ~/.ssh/dentalfinance_deploy
```

Add public key to server `~deploy/.ssh/authorized_keys`.

## 6. Deploy user

```bash
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG docker deploy
sudo mkdir -p /home/deploy/.ssh
sudo cp authorized_keys /home/deploy/.ssh/
sudo chown -R deploy:deploy /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
sudo chmod 600 /home/deploy/.ssh/authorized_keys
```

## 7. Disable root/password login

Edit `/etc/ssh/sshd_config`:

```
PermitRootLogin no
PasswordAuthentication no
```

```bash
sudo systemctl reload sshd
```

## 8. IONOS firewall

Allow: 22/tcp (SSH), 80/tcp, 443/tcp. Deny all other inbound. Do **not** expose PostgreSQL or Redis.

## 9. Docker installation

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker deploy
```

## 10. Directory layout

```bash
sudo mkdir -p /opt/dentalfinance/backups/database
sudo chown -R deploy:deploy /opt/dentalfinance
```

```
/opt/dentalfinance/
├── compose.production.yml
├── app.env                 # secrets — chmod 600
├── .deploy-state           # written by deploy.sh
├── deploy/
│   ├── Caddyfile
│   ├── php.ini
│   ├── app.env.example
│   └── scripts/
│       ├── deploy.sh
│       ├── backup-database.sh
│       └── restore-database.sh
└── backups/database/
```

## 11. Server environment file

```bash
cp /opt/dentalfinance/deploy/app.env.example /opt/dentalfinance/app.env
chmod 600 /opt/dentalfinance/app.env
```

Fill all values — see section 13–16.

## 12. APP_KEY generation

On a machine with PHP (or temporary container):

```bash
php artisan key:generate --show
```

Copy output into `APP_KEY=` in `app.env`.

## 13. PostgreSQL credentials

Set in `app.env` (single source — Compose maps these to the PostgreSQL container):

```dotenv
DB_DATABASE=dentalfinance
DB_USERNAME=dentalfinance
DB_PASSWORD=<strong-random>
```

Do **not** duplicate `POSTGRES_DB` / `POSTGRES_USER` / `POSTGRES_PASSWORD` in `app.env`.

## 14. GHCR login (VPS)

Create a GitHub PAT with `read:packages` only. On the VPS:

```bash
echo "<PAT>" | docker login ghcr.io -u <github-username> --password-stdin
```

Never store the PAT in `app.env` or git.

## 15. GitHub Secrets

Repository → Settings → Secrets → Actions:

| Secret | Description |
|---|---|
| `DEPLOY_HOST` | VPS IP or hostname |
| `DEPLOY_PORT` | SSH port (usually `22`) |
| `DEPLOY_USER` | `deploy` |
| `DEPLOY_SSH_PRIVATE_KEY` | Private key contents |
| `DEPLOY_KNOWN_HOSTS` | Output of `ssh-keyscan -p 22 <host>` |

Application secrets stay **only** on the server.

## 16. GitHub Environment `production`

Create environment `production` with required reviewers (recommended).

## 17. First deployment

1. Configure DNS (section 18)
2. Complete `app.env` (including `GHCR_IMAGE=ghcr.io/<owner>/<repo>`)
3. Push to `main` or run **Deploy Production** workflow manually
4. Verify HTTPS and `/up`

Manual deploy on server:

```bash
APP_ENV_FILE=/opt/dentalfinance/app.env \
  /opt/dentalfinance/deploy/scripts/deploy.sh <40-char-commit-sha>
```

On first deployment there is no previous image in `.deploy-state`; rollback is unavailable until a second successful deploy.

## 17a. Rsync layout (CI/CD)

GitHub Actions syncs **separately** — never `rsync --delete` on `/opt/dentalfinance/` as a whole:

| Source | Remote target | `--delete` |
|---|---|---|
| `compose.production.yml` | `/opt/dentalfinance/compose.production.yml` | no |
| `deploy/` | `/opt/dentalfinance/deploy/` | yes |

Protected server paths (never overwritten by rsync): `app.env`, `.deploy-state`, `.deploy.lock`, `backups/`.

## 17b. Compose command (all scripts)

```bash
docker compose \
  --env-file /opt/dentalfinance/app.env \
  -f /opt/dentalfinance/compose.production.yml \
  <subcommand>
```

Scripts never `source app.env` (safe handling of special characters in passwords).

## 17c. Internal healthcheck

The app container exposes an **internal-only** HTTP listener at `127.0.0.1:8080` (not published to the host). Deploy and restore scripts verify:

```text
http://127.0.0.1:8080/up
```

This boots Laravel via `php_server` — a successful HTTPS redirect alone is not sufficient.

## 17d. Vite / welcome view

`resources/views/welcome.blade.php` still references `@vite`, but **no route serves it** in production (`/` → `LandingController`). The Docker image does not include `public/build` or a Node build step. No production page depends on Vite assets in v1.

## 18. DNS (IONOS)

```
A      @      <VPS-IPv4>
CNAME  www    dentalfinance.eu
```

Do **not** remove existing MX/SPF/DKIM/DMARC mail records.

Verify:

```bash
dig +short dentalfinance.eu A
dig +short www.dentalfinance.eu
dig +short dentalfinance.eu MX
```

## 19. HTTPS verification

```bash
curl -I https://dentalfinance.eu/up
curl -I http://dentalfinance.eu/up    # should redirect to HTTPS
curl -I https://www.dentalfinance.eu  # should redirect to apex
```

## 20. SMTP (Resend)

Production uses **Resend SMTP**, not IONOS mailbox authentication.

1. Create a Resend account
2. Generate an API key (Resend dashboard → API Keys)
3. Add and verify domain `dentalfinance.eu` in Resend
4. Add Resend DNS records at IONOS (SPF/DKIM/DMARC as instructed)
5. Set in `app.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=587
MAIL_USERNAME=resend
MAIL_PASSWORD=<resend-api-key>
MAIL_FROM_ADDRESS=system@dentalfinance.eu
MAIL_FROM_NAME=DentalFinance
MAIL_EHLO_DOMAIN=dentalfinance.eu
```

Use `MAIL_SCHEME=smtp` for port 587 (STARTTLS). Laravel 13 does not read `MAIL_ENCRYPTION`; supported schemes are `smtp` (port 587 / STARTTLS) and `smtps` (port 465 / implicit TLS).

Resend SMTP uses the fixed username `resend`; the API key is the SMTP password. Store the key only in server-side `app.env`.

Check Resend plan limits for daily/monthly sending quotas.

## 21. SMTP test

```bash
docker compose \
  --env-file /opt/dentalfinance/app.env \
  -f /opt/dentalfinance/compose.production.yml \
  exec app php artisan tinker
```

```php
Mail::raw('DentalFinance SMTP test', fn ($m) => $m->to('you@example.com')->subject('SMTP test'));
```

## 22. Migrations

New production database:

```bash
APP_IMAGE=ghcr.io/owner/repo:<sha> \
APP_ENV_FILE=/opt/dentalfinance/app.env \
docker compose -f /opt/dentalfinance/compose.production.yml run --rm --no-deps --entrypoint php app artisan migrate --force
```

**Do not** auto-run seeders or SQLite import. For existing Clinic 111 data see ADR-035:

```bash
php artisan app:migrate-sqlite-to-pgsql --dry-run
```

## 23. Queue worker

```bash
docker compose \
  --env-file /opt/dentalfinance/app.env \
  -f /opt/dentalfinance/compose.production.yml \
  ps worker
docker compose \
  --env-file /opt/dentalfinance/app.env \
  -f /opt/dentalfinance/compose.production.yml \
  logs --tail=50 worker
```

Imports currently run synchronously via HTTP; worker is ready for future queued jobs (`database` connection).

## 24. Scheduler

```bash
docker compose \
  --env-file /opt/dentalfinance/app.env \
  -f /opt/dentalfinance/compose.production.yml \
  ps scheduler
```

Uses `php artisan schedule:work`. No sub-minute schedules defined in v1.

## 25. Backup

Manual (reads `APP_IMAGE` from `.deploy-state` if not set):

```bash
APP_ENV_FILE=/opt/dentalfinance/app.env \
  /opt/dentalfinance/deploy/scripts/backup-database.sh
```

Cron example (daily 02:15 UTC):

```cron
15 2 * * * /opt/dentalfinance/deploy/scripts/backup-database.sh >> /var/log/dentalfinance-backup.log 2>&1
```

## 26. External backup copy

Local VPS backups are **not** sufficient. Copy `backups/database/*.dump` to off-site storage (S3, another provider, encrypted object storage) daily.

## 27. Restore test

On staging or maintenance window:

```bash
APP_ENV_FILE=/opt/dentalfinance/app.env \
  /opt/dentalfinance/deploy/scripts/restore-database.sh \
  /opt/dentalfinance/backups/database/dentalfinance-YYYYMMDDTHHMMSSZ.dump
```

Restore flow: safety backup → maintenance mode → stop worker/scheduler → terminate DB connections → `pg_restore` with `--clean --if-exists --no-owner --no-privileges --exit-on-error --single-transaction` → restart services → `artisan up` → internal healthcheck. On failure, a safety backup path is logged for manual recovery.

Requires typing `RESTORE` to confirm. **Never** run from GitHub Actions.

## 28. Application rollback

If deploy healthcheck fails, `deploy.sh` reverts to the previous image recorded in `.deploy-state` and runs a **separate rollback healthcheck**. Messages distinguish rollback success from rollback failure. No automatic database rollback.

Manual rollback:

```bash
export APP_IMAGE=ghcr.io/owner/clinic-111:<previous-sha>
export APP_ENV_FILE=/opt/dentalfinance/app.env
docker compose -f /opt/dentalfinance/compose.production.yml up -d --remove-orphans
```

## 29. Database rollback limits

Application image rollback **does not** reverse migrations. Failed migrations require manual DBA intervention or restore from backup.

## 30. Log review

```bash
docker compose \
  --env-file /opt/dentalfinance/app.env \
  -f /opt/dentalfinance/compose.production.yml \
  logs --tail=100 app
docker compose \
  --env-file /opt/dentalfinance/app.env \
  -f /opt/dentalfinance/compose.production.yml \
  logs --tail=100 worker
docker compose \
  --env-file /opt/dentalfinance/app.env \
  -f /opt/dentalfinance/compose.production.yml \
  logs --tail=100 database
```

Laravel logs go to stderr → Docker json-file driver (10 MB × 5 files rotation).

## 31. Troubleshooting

| Symptom | Check |
|---|---|
| 502 / no response | `docker compose ps`, app logs, Caddy cert volumes |
| DB connection error | `database` health, `DB_*` in `app.env` |
| Import too large | `deploy/php.ini` upload limits (32M), app validation 10 MB |
| Mail not sent | Resend API key, domain verification, DNS auth, `MAIL_*` vars |
| Health fail after deploy | Previous image in `.deploy-state`, migration errors |

## 32. Updates

Push to `main` triggers CI → build → deploy. Each deploy:

1. Backup DB
2. Migrate
3. Restart containers
4. Internal healthcheck at `http://127.0.0.1:8080/up`

## 33. Security checklist

- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` set
- [ ] `app.env` mode `600`
- [ ] PostgreSQL not publicly exposed
- [ ] No Redis exposed
- [ ] No secrets in git or Docker image
- [ ] GHCR PAT minimal scope (`read:packages`)
- [ ] SSH key-only login
- [ ] Firewall: 22, 80, 443 only
- [ ] Legal env vars filled (`docs/LEGAL_SETUP.md`)
- [ ] `ACCOUNTING_PATIENT_REFERENCE_HMAC_KEY` set

## 34. Go-live checklist

- [ ] DNS A record points to VPS
- [ ] HTTPS valid on `dentalfinance.eu`
- [ ] HTTP → HTTPS redirect works
- [ ] `www` → apex redirect works
- [ ] `/up` returns 200
- [ ] Email verification tested
- [ ] Password reset tested
- [ ] Excel import tested
- [ ] Practice Overview tested
- [ ] Tenant isolation spot-checked
- [ ] Queue worker running
- [ ] Scheduler running
- [ ] Daily backup cron active
- [ ] Off-site backup copy verified
- [ ] Restore tested once
- [ ] Rollback tested once
- [ ] No demo seed data in production

## Related

* ADR-035 — PostgreSQL / SQLite migration
* ADR-037 — Production deployment architecture
* `deploy/app.env.example` — environment template
