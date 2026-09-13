# Befehle — Dev, Test & Production

Kurzreferenz für alle relevanten Artisan-, Composer- und Git-Befehle in diesem Projekt.

Siehe auch: [ROADMAP.md](./ROADMAP.md) (Test-DB-Trennung), [MULTI_CLINIC_ARCHITECTURE.md](./MULTI_CLINIC_ARCHITECTURE.md), [PROJECT_OVERVIEW.md](./PROJECT_OVERVIEW.md).

---

## SaaS & Datenbank — eine DB für alle Clinics

**Nein — jede Kunden-Clinic bekommt keine eigene Datenbank.**

Laut **ADR-026** (Accepted): **eine gemeinsame Datenbank**, Mandantentrennung über **`clinic_id`**.

| Modell | Bedeutung |
|--------|-----------|
| **Clinic 111** | Erster Mandant (`CLINIC_111`) — Demo/Referenz in Dev |
| **Neue Kunden-Clinic** | Eigene Zeile in `clinics`, eigene User, Doctors, Reports — **gleiche DB** |
| **Isolation** | Explizit in Services/Middleware (`CurrentClinicResolver`, `TenantResourceGuard`) — nicht per separate DB |

**Abgelehnt** (ADR-026): Separate Database per Clinic — zu komplex für Backups, Reporting und Betrieb.

**Production:** typisch **MySQL oder PostgreSQL** (eine Instanz, alle Tenants).  
**Lokal Dev:** `database/database.sqlite`  
**Tests:** `database/testing.sqlite` (strikt getrennt)

Neue Production-Clinics nur über **Clinic Onboarding** (`/register-clinic` / `POST /api/register-clinic`) — **nicht** über `db:seed` (ADR-030).

---

## Goldene Regeln (Datenbank)

| Regel | Details |
|-------|---------|
| Dev-Daten | `database/database.sqlite` — manuelle Kliniken, Reports, UI-Arbeit |
| Test-Daten | `database/testing.sqlite` — nur `php artisan test` |
| Tests ausführen | **Immer sicher** — berührt Dev-DB nicht |
| `migrate:fresh` ohne `--env=testing` | **Löscht Dev-DB** — verboten außer bewusst |
| Debuggen / Agent-Skripte | **Nur** `--env=testing` oder PHPUnit |
| Production | `migrate --force` — **kein** `migrate:fresh`, **kein** `db:seed` für echte Kunden |

Explizit in `.env` setzen (empfohlen):

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

---

## Erstes Setup (Development)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

`.env` ergänzen:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
ACCOUNTING_PATIENT_REFERENCE_HMAC_KEY=<openssl rand -hex 32>
```

Datenbank anlegen & migrieren:

```bash
touch database/database.sqlite
php artisan migrate
php artisan db:seed          # optional: Clinic 111 Demo-Daten
```

Frontend (falls UI-Assets geändert):

```bash
npm install
npm run build                # Production-Build
npm run dev                  # Vite Dev-Server (mit composer dev)
```

---

## Development — Server starten

```bash
./bin/serve                  # empfohlen: Upload bis 20 MB (Excel-Import)
# oder
php artisan serve            # Standard; kleinere Upload-Limits
```

Alles auf einmal (Server + Queue + Logs + Vite):

```bash
composer dev
```

Demo-Login nach Seed:

| E-Mail | Passwort | Rolle |
|--------|----------|-------|
| admin@clinic.test | password | admin |
| accountant@clinic.test | password | accountant |
| viewer@clinic.test | password | viewer |

---

## Tests (sicher — nur testing.sqlite)

```bash
composer test                # config:clear + phpunit (512M)
php artisan test
php artisan test --filter=DailyReportRowEditTest
php artisan test --filter=BusinessConfigurationTest
php artisan test tests/Unit/MoneyCalculatorTest.php
```

Test-DB zurücksetzen (**nur** Test-Datei):

```bash
php artisan migrate:fresh --seed --env=testing --force
```

---

## Debugging — erlaubt vs. verboten

### Erlaubt (Dev-Daten bleiben)

```bash
php artisan test
php artisan migrate:fresh --seed --env=testing --force
php artisan tinker --env=testing
php -r '...' # nur wenn bootstrap mit --env=testing oder PHPUnit
```

### Verboten beim Debuggen (Dev-Daten weg)

```bash
php artisan migrate:fresh          # ❌ löscht database.sqlite
php artisan migrate:fresh --seed   # ❌ löscht + Seed nur Dev
php artisan migrate:refresh        # ❌
php artisan db:wipe                # ❌
```

**Regel für Cursor / AI-Assistenten:** Kein `migrate:fresh`, `db:wipe` oder ad-hoc-PHP mit App-Bootstrap auf Default-`.env` — nur `--env=testing` oder PHPUnit.

---

## Gefährliche Befehle (Übersicht)

| Befehl | Wirkung |
|--------|---------|
| `migrate:fresh` | Alle Tabellen drop + neu — **Dev-DB leer** |
| `migrate:refresh` | Rollback aller Migrationen + migrate |
| `db:wipe` | Alle Tabellen löschen |
| Tests gegen `database.sqlite` zeigen | `.env.testing` / `phpunit.xml` prüfen |

Nach versehentlichem Dev-Verlust (nur Demo wiederherstellen):

```bash
php artisan migrate
php artisan db:seed
```

Echte Kunden-Clinics aus Seed **nicht** wiederherstellbar — nur aus Backup.

---

## Production — Deployment

### Environment (`.env`)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ihre-domain.de

DB_CONNECTION=mysql          # oder pgsql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_SECURE_COOKIE=true
LOG_LEVEL=warning
```

Weitere Keys: siehe `.env.example` (HMAC, Mail, CAPTCHA, Security).

### Deploy-Befehle (typische Reihenfolge)

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### Production — Queue / Scheduler (falls aktiv)

```bash
php artisan queue:work --tries=3
# Cron (Beispiel):
# * * * * * cd /pfad/zum/projekt && php artisan schedule:run >> /dev/null 2>&1
```

### Production — nicht ausführen

```bash
php artisan db:seed              # nur Dev/Demo — keine echten Tenants
php artisan migrate:fresh          # Datenverlust
php artisan tinker                 # nur mit Vorsicht / Wartungsfenster
```

---

## Neue Kunden-Clinic (SaaS)

| Weg | Wann |
|-----|------|
| **Web:** `/register-clinic` | Production — Owner registriert Klinik |
| **API:** `POST /api/register-clinic` | Programmatisch / Mobile |
| **`php artisan db:seed`** | **Nur Dev** — erzeugt `CLINIC_111` Demo |

Nach Registrierung: Configuration Wizard (Doctors → Labs → Treatments → Lab Prices → Import).

---

## Excel-Import

### Web-UI

Import unter `/imports` (nach Business Configuration).

### CLI (große Dateien, empfohlen)

```bash
php -d memory_limit=512M artisan daily-report:import "/pfad/zum/daily report january 2026.xlsm"
```

Monat wird aus dem **Dateinamen** gelesen, nicht aus dem heutigen Datum.

---

## Daily Report Editor & API (Kurz)

Web-Routen (eingeloggt): `/daily-report`, `/daily-report/{id}`.

```bash
php artisan route:list --path=daily-report
php artisan route:list --path=imports
php artisan route:list --path=api
```

---

## Cache & Wartung

### Development

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```

### Production (nach Deploy)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

---

## Code-Qualität

```bash
./vendor/bin/pint                 # Laravel Pint (Code-Style)
./vendor/bin/pint --test          # nur prüfen
```

---

## Git & Pull Requests

```bash
git status
git diff
git checkout -b feature/mein-feature
git add ...
git commit -m "Beschreibung"
git push -u origin HEAD
gh pr create --title "..." --body "..."
```

Vor Commit:

```bash
composer test
```

---

## Nützliche Diagnose

```bash
php artisan about
php artisan env
php artisan migrate:status
php artisan db:show               # Laravel 11+
php artisan tinker --env=testing  # nur Test-DB
tail -f storage/logs/laravel.log
composer dev                      # Logs live via Pail
```

---

## PostgreSQL Production (ADR-035)

### Backup vor Migration (Pflicht)

```bash
cp database/database.sqlite database/database.sqlite.backup-$(date +%Y%m%d-%H%M%S)
pg_dump clinic_accounting > backup.sql   # nach PostgreSQL-Setup
```

### SQLite → PostgreSQL Daten migrieren

```bash
# Schema auf PostgreSQL
php artisan migrate --force

# Dry-run (nur Zählung, keine Writes)
php artisan app:migrate-sqlite-to-pgsql \
  --sqlite=database/database.sqlite \
  --pgsql=pgsql \
  --dry-run

# Import (SQLite-Datei wird NICHT gelöscht)
php artisan app:migrate-sqlite-to-pgsql \
  --sqlite=database/database.sqlite \
  --pgsql=pgsql
```

### Tests gegen PostgreSQL (optional)

```bash
DB_CONNECTION=pgsql DB_DATABASE=clinic_accounting_test php artisan migrate:fresh --seed --force
DB_CONNECTION=pgsql DB_DATABASE=clinic_accounting_test php artisan test
```

Standard-Tests bleiben auf `database/testing.sqlite` (`php artisan test`).

---

## Schnell-Checkliste

| Aufgabe | Befehl |
|---------|--------|
| Lokal starten | `./bin/serve` |
| Tests | `composer test` |
| Dev-DB migrieren | `php artisan migrate` |
| Test-DB reset | `migrate:fresh --env=testing --force` |
| Production deploy | `migrate --force` + `optimize` |
| Neue echte Clinic | `/register-clinic` (nicht Seed) |
| SQLite → PostgreSQL | `app:migrate-sqlite-to-pgsql --dry-run` dann import |

---

*Stand: Projekt-Docs ADR-026, ADR-030, ADR-031, ROADMAP Test Database Isolation, PROJECT_OVERVIEW Technology Stack.*
