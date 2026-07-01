# Production Operations Runbook — DentalFinance

**Zielgruppe:** Entwickler mit SSH-Zugang, die Production betreiben oder Störungen analysieren — ohne das Projekt selbst aufgebaut zu haben.

**Domain:** `dentalfinance.eu`  
**Server-IP:** `65.108.82.159`  
**Betriebssystem:** Ubuntu 24.04 LTS (`x86_64`)  
**Deployment-Pfad:** `/opt/dentalfinance`

**Verwandte Dokumente (technische Details, nicht duplizieren):**

- `docs/PRODUCTION_DEPLOYMENT.md` — Ersteinrichtung, Architektur, Go-Live
- `docs/LEGAL_SETUP.md` — rechtliche Env-Variablen
- `docs/DECISIONS.md` — ADR-037 (Deployment), ADR-038 (Resend SMTP)

**Legende für Befehle:**

| Präfix | Bedeutung |
|---|---|
| `[LOCAL MAC]` | Auf dem Entwickler-Mac ausführen |
| `[SERVER: kareem]` | Als Admin-Benutzer `kareem` auf dem Server (ggf. mit `sudo`) |
| `[SERVER: deploy]` | Als Deployment-Benutzer `deploy` (nach `sudo -iu deploy`) |
| `[PSQL]` | Innerhalb einer interaktiven `psql`-Sitzung |
| **GEFÄHRLICH** | Nur mit Backup, Wartungsfenster und klarer Freigabe |

---

## 1. Grundregeln für Production

Production ist **keine lokale Entwicklungsumgebung**. Die folgenden Regeln gelten immer:

### Verboten oder nur mit ausdrücklicher Freigabe

- Secrets in Chat, Git, Screenshots, Tickets oder Terminal-History kopieren oder einfügen
- `app.env` vollständig ausgeben (`cat app.env`, `env`, Screenshots)
- Direkte Änderungen an PostgreSQL-Datendateien oder Docker-Volumes (`dentalfinance_postgres_data` usw.)
- Schemaänderungen per manuellem SQL (`ALTER TABLE`, `DROP`, …)
- Fachliche Datenänderungen per unkontrolliertem `UPDATE`/`DELETE` ohne Tenant-Bedingung
- `docker compose down -v`
- `docker volume rm`
- `docker system prune --volumes`
- Port `5432` öffentlich freigeben
- Container oder Images blind löschen
- Dateien **innerhalb** laufender Container manuell editieren (Änderungen gehen beim Recreate verloren)
- `php artisan migrate:fresh`, `migrate:reset`, `db:wipe` in Production
- `chmod 777` auf Production-Pfade

### Pflicht bei riskanten Eingriffen

- **Vor** Datenbankänderungen oder Restore: Backup erstellen (`backup-database.sh`)
- Schemaänderungen **ausschließlich** über Laravel-Migrationen (`deploy.sh` oder kontrolliertes `migrate --force`)
- Fachliche Korrekturen bevorzugt über die Anwendung oder geprüfte Artisan-Befehle
- Bei Unsicherheit: abbrechen, Logs sammeln, nicht weiter „herumprobieren“

### Normale Arbeitsweise

- Standard-Deployments über **GitHub Actions** (`Deploy Production`)
- Manuelle Serverarbeit: Login als `kareem` → bei Bedarf `sudo -iu deploy`
- Alle Docker-/Compose-/Artisan-Operationen unter `/opt/dentalfinance` als `deploy`

---

## 2. Benutzer und Berechtigungen

### Rollen

**`kareem`** — Betriebssystem-Administrator

- SSH-Login mit Admin-Key (`~/.ssh/dentalfinance_admin`)
- `sudo` für Systemdienste, Pakete, Firewall, `/etc`
- Benutzer- und Rechteverwaltung
- Host-Netzwerk und Speicherplatz
- Einstiegspunkt für manuelle Serverarbeit

**`deploy`** — Anwendungs- und Deployment-Benutzer

- Mitglied der Docker-Gruppe (`docker`, `docker compose` ohne `sudo`)
- Besitzer von `/opt/dentalfinance` und `app.env`
- Container, Backups, Restore, Deployment-Skripte
- Laravel Artisan innerhalb der App-Container

**GitHub Actions** — automatisierter Deploy

- SSH als `deploy` (Secret `DEPLOY_USER`) mit separatem Deploy-Key (`DEPLOY_SSH_PRIVATE_KEY`)
- Führt `deploy/scripts/deploy.sh <40-char-sha>` aus
- Der Deploy-Key kann serverseitig eingeschränkt sein (z. B. `command=`, `restrict`) — **zu prüfen** in `~deploy/.ssh/authorized_keys`

### Aufgabentabelle

| Aufgabe | Benutzer | `sudo` erforderlich | Begründung |
|---|---|---|---|
| SSH-Login (interaktiv) | `kareem` | Nein | Admin-Key; Root-Login deaktiviert |
| Wechsel zu `deploy` | `kareem` | Ja (`sudo -iu deploy`) | `deploy` ist separater Systembenutzer |
| Ubuntu-Updates (`apt`) | `kareem` | Ja | Systempakete |
| `systemctl` (Docker, SSH, Fail2Ban) | `kareem` | Ja | Systemdienste |
| Firewall / Netzwerk prüfen (`ss`, `ufw`) | `kareem` | Ja (für `-p`) | Host-Ebene |
| Dateien unter `/etc` bearbeiten | `kareem` | Ja | Systemkonfiguration |
| `app.env` bearbeiten | `deploy` | Nein (als deploy); `kareem`: `sudo -u deploy` | Datei gehört `deploy:deploy`, Modus `600` |
| Docker / Docker Compose | `deploy` | Nein | `deploy` ∈ `docker`-Gruppe |
| Container-Logs lesen | `deploy` | Nein | Docker-Zugriff |
| PostgreSQL öffnen (`psql` im Container) | `deploy` | Nein | Über `docker exec` |
| Backup starten | `deploy` | Nein | `backup-database.sh` |
| Restore starten | `deploy` | Nein | `restore-database.sh` (interaktiv) |
| Anwendung neu starten / recreate | `deploy` | Nein | Compose |
| Deployment ausführen | `deploy` (manuell) oder GitHub Actions | Nein | `deploy.sh` |
| Deployment-Status prüfen | `deploy` | Nein | Compose, `.deploy-state` |
| Dateirechte unter `/opt/dentalfinance` reparieren | `kareem` | Ja (`chown`/`chmod`) | Nur wenn Besitz/Rechte beschädigt |
| Speicherplatz prüfen (`df`, `free`) | `kareem` | Nein | Host-Metriken |
| Docker-Volumes ansehen | `deploy` | Nein | `docker volume ls` |
| GHCR-Image pull (manuell) | `deploy` | Nein | Benötigt Login in `~deploy/.docker/config.json` |

### Abweichungen / Hinweise

- **`app.env` von `kareem` bearbeiten:** nicht direkt als root schreiben — `sudo -u deploy nano /opt/dentalfinance/app.env` verwenden, damit Besitzer `deploy:deploy` und Modus `600` erhalten bleiben.
- **Fail2Ban:** in der bekannten Umgebung erwähnt, aber nicht im Repository konfiguriert — Installation/Status **auf dem Server prüfen**.
- **Cron für Backups:** Beispiel in `docs/PRODUCTION_DEPLOYMENT.md` nutzt `/var/log/dentalfinance-backup.log` — Cron-Eintrag liegt typischerweise unter `kareem` oder `deploy` (**zu prüfen**: `sudo crontab -l -u deploy`).

---

## 3. Verbindung mit dem Server

### Login vom Mac

```bash
# [LOCAL MAC]
ssh \
  -o IdentitiesOnly=yes \
  -i ~/.ssh/dentalfinance_admin \
  kareem@65.108.82.159
```

### Wechsel zum Deployment-Benutzer

```bash
# [SERVER: kareem]
sudo -iu deploy
```

### Aktiven Benutzer prüfen

```bash
# [SERVER: kareem] oder [SERVER: deploy]
whoami
id
hostname
pwd
```

Erwartung nach Wechsel: `whoami` → `deploy`, `pwd` oft `/home/deploy`.

### `deploy`-Shell verlassen

```bash
# [SERVER: deploy]
exit
```

### Warum nicht direkt als `deploy` einloggen?

- Der interaktive Admin-Zugang ist für `kareem` + Admin-Key vorgesehen.
- Der **GitHub-Actions-Deploy-Key** (`DEPLOY_SSH_PRIVATE_KEY`) ist für `deploy` gedacht und kann auf bestimmte Befehle beschränkt sein — nicht als allgemeiner interaktiver Login-Key gedacht.
- Trennung: OS-Administration (`kareem`) vs. Anwendungsbetrieb (`deploy`).

---

## 4. Wichtige Pfade

| Pfad | Zweck | Besitzer / Rechte (erwartet) |
|---|---|---|
| `/opt/dentalfinance` | Production-Root | `deploy:deploy` |
| `/opt/dentalfinance/app.env` | Secrets, DB, Mail, Legal — **niemals committen** | `deploy:deploy`, `600` |
| `/opt/dentalfinance/compose.production.yml` | Docker-Compose-Definition | `deploy:deploy` |
| `/opt/dentalfinance/deploy/` | Caddyfile, Skripte, `app.env.example` | `deploy:deploy` |
| `/opt/dentalfinance/backups/` | Backup-Verzeichnis | `deploy:deploy` |
| `/opt/dentalfinance/backups/database/` | PostgreSQL-Custom-Format-Dumps | `deploy:deploy` |
| `/opt/dentalfinance/.deploy-state` | Letztes erfolgreiches Image + Zeitstempel | `deploy:deploy` |
| `/opt/dentalfinance/.deploy.lock` | Deployment-Sperre (während `deploy.sh`) | temporär |
| `/home/deploy/.docker/config.json` | GHCR-Login für `docker pull` | `deploy:deploy` |

### `app.env` Rechte prüfen

```bash
# [SERVER: kareem]
sudo stat -c '%U:%G %a %n' /opt/dentalfinance/app.env
```

Erwartet: `deploy:deploy 600 /opt/dentalfinance/app.env`

---

## 5. Aktuellen Zustand der Anwendung prüfen

### Basis-Setup (als `deploy`)

```bash
# [SERVER: deploy]
cd /opt/dentalfinance
export APP_ENV_FILE=/opt/dentalfinance/app.env
```

**`APP_IMAGE` nicht raten.** Sicherste Quellen (in dieser Reihenfolge):

1. `.deploy-state` (nach erfolgreichem Deploy)
2. Laufender App-Container

```bash
# [SERVER: deploy]
export APP_IMAGE="$(
  docker inspect \
    --format '{{.Config.Image}}' \
    dentalfinance_app
)"
echo "APP_IMAGE=${APP_IMAGE}"
```

Alternativ aus State-Datei (nur Image-Zeile, kein Secret):

```bash
# [SERVER: deploy]
grep '^APP_IMAGE=' /opt/dentalfinance/.deploy-state
```

### Compose-Status

```bash
# [SERVER: deploy]
docker compose \
  --env-file "${APP_ENV_FILE}" \
  -f compose.production.yml \
  ps -a
```

```bash
# [SERVER: deploy]
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
```

### Container-Details

```bash
# [SERVER: deploy]
docker inspect dentalfinance_app \
  --format 'Image={{.Config.Image}} Status={{.State.Status}} Health={{if .State.Health}}{{.State.Health.Status}}{{else}}n/a{{end}} Restarts={{.RestartCount}}'
```

Worker/Scheduler (kein HTTP-Healthcheck):

```bash
# [SERVER: deploy]
docker inspect dentalfinance_worker \
  --format 'Status={{.State.Status}} Restarts={{.RestartCount}} StopTimeout={{.HostConfig.StopTimeout}}s'
docker inspect dentalfinance_scheduler \
  --format 'Status={{.State.Status}} Restarts={{.RestartCount}} StopTimeout={{.HostConfig.StopTimeout}}s'
```

### Erwarteter Zustand

| Container | Status | Health |
|---|---|---|
| `dentalfinance_app` | `running` | `healthy` (HTTP `:8080/up`) |
| `dentalfinance_database` | `running` | `healthy` (`pg_isready`) |
| `dentalfinance_worker` | `running` | kein HTTP-Check (explizit deaktiviert) |
| `dentalfinance_scheduler` | `running` | kein HTTP-Check (explizit deaktiviert) |

---

## 6. Healthchecks

### Intern (im App-Container, nur `127.0.0.1`)

```bash
# [SERVER: deploy]
docker compose \
  --env-file /opt/dentalfinance/app.env \
  -f /opt/dentalfinance/compose.production.yml \
  exec -T app curl -fsS http://127.0.0.1:8080/up
```

Caddy bindet `:8080` nur an `127.0.0.1` (`deploy/Caddyfile`) — nicht öffentlich.

### Extern

```bash
# [LOCAL MAC] oder [SERVER: kareem]
curl -fsS https://dentalfinance.eu/up
```

Kompakte Statusprüfung:

```bash
# [LOCAL MAC]
curl -sS -o /dev/null \
  -w 'Health: %{http_code}\n' \
  https://dentalfinance.eu/up
```

Erwartet: `Health: 200`

IPv4 / IPv6:

```bash
# [LOCAL MAC]
curl -4 -fsS https://dentalfinance.eu/up
curl -6 -fsS https://dentalfinance.eu/up
```

### Warum Worker und Scheduler keinen HTTP-Healthcheck auf `:8080` haben

- App-, Worker- und Scheduler-Container nutzen **dasselbe Docker-Image** mit eingebautem `HEALTHCHECK` auf `http://127.0.0.1:8080/up` (`Dockerfile`).
- Worker (`php artisan queue:work`) und Scheduler (`php artisan schedule:work`) starten **keinen HTTP-Server**.
- Ohne Override würde der Image-Healthcheck fehlschlagen → Container fälschlich `unhealthy`.
- In `compose.production.yml` ist deshalb für `worker` und `scheduler` gesetzt: `healthcheck: disable: true`.
- Nur `app` und `database` haben aktive Compose-Healthchecks.

---

## 7. Logs ansehen

### Alle Services (letzte 200 Zeilen)

```bash
# [SERVER: deploy]
cd /opt/dentalfinance
docker compose \
  --env-file app.env \
  -f compose.production.yml \
  logs --tail=200
```

### Live-Logs

```bash
# [SERVER: deploy]
docker compose \
  --env-file app.env \
  -f compose.production.yml \
  logs -f --tail=100
```

**Hinweis:** Mit `Ctrl+C` Live-Logs beenden — der Container wird **nicht** gestoppt.

### Einzelne Services

```bash
# [SERVER: deploy]
docker logs --tail=200 dentalfinance_app
docker logs --tail=200 dentalfinance_worker
docker logs --tail=200 dentalfinance_scheduler
docker logs --tail=200 dentalfinance_database
```

### Zeitraum

```bash
# [SERVER: deploy]
docker compose \
  --env-file app.env \
  -f compose.production.yml \
  logs --since=30m
```

### Fehlersuche (grep)

```bash
# [SERVER: deploy]
docker compose \
  --env-file app.env \
  -f compose.production.yml \
  logs --tail=500 2>&1 \
  | grep -iE 'error|exception|fatal|failed|unhealthy|permission denied|connection refused|out of memory'
```

### Laravel-Logs vs. Docker-Logs

- Production-`app.env` setzt `LOG_CHANNEL=stderr` → Laravel schreibt primär nach **stderr**.
- stderr wird vom Docker **json-file**-Treiber erfasst (Rotation: 10 MB × 5 Dateien, siehe `compose.production.yml`).
- **`docker compose logs app`** ist die Hauptquelle für Anwendungsfehler.
- Datei `storage/logs/laravel.log` im Container (`/app/storage/logs/laravel.log`) ist in Production **nicht** der primäre Kanal — nur prüfen, wenn explizit auf Datei-Logging umgestellt wurde.

```bash
# [SERVER: deploy] — nur bei Bedarf, Ausgabe kann sensible Daten enthalten
docker compose \
  --env-file app.env \
  -f compose.production.yml \
  exec -T app tail -n 100 /app/storage/logs/laravel.log 2>/dev/null || echo "Keine laravel.log oder leer"
```

**Vorsicht:** Logs nicht unkontrolliert vollständig kopieren oder posten — können Request-Daten, E-Mails oder Fehlerdetails enthalten.

---

## 8. Laravel-Artisan-Befehle

Alle Befehle über den **App-Service** (`dentalfinance_app`). Arbeitsverzeichnis im Container: `/app`.

### Standard-Präfix

```bash
# [SERVER: deploy]
cd /opt/dentalfinance
export APP_ENV_FILE=/opt/dentalfinance/app.env
export APP_IMAGE="$(docker inspect --format '{{.Config.Image}}' dentalfinance_app)"

docker compose \
  --env-file "${APP_ENV_FILE}" \
  -f compose.production.yml \
  exec -T app php artisan <befehl>
```

### Häufige Befehle

```bash
# [SERVER: deploy] — Systemübersicht
docker compose --env-file app.env -f compose.production.yml exec -T app php artisan about

# [SERVER: deploy] — Migrationen (nur lesen)
docker compose --env-file app.env -f compose.production.yml exec -T app php artisan migrate:status

# [SERVER: deploy] — Queue-Worker sanft neu laden (kein Container-Neustart)
docker compose --env-file app.env -f compose.production.yml exec -T app php artisan queue:restart

# [SERVER: deploy] — Geplante Tasks auflisten
docker compose --env-file app.env -f compose.production.yml exec -T app php artisan schedule:list

# [SERVER: deploy] — Config-Cache neu aufbauen (nach Deploy automatisch)
docker compose --env-file app.env -f compose.production.yml exec -T app php artisan optimize

# [SERVER: deploy] — Config-Cache leeren (nur bei gezielter Fehlersuche — Ausgabe kann Secrets enthalten)
docker compose --env-file app.env -f compose.production.yml exec -T app php artisan optimize:clear

# [SERVER: deploy] — DB-Konfiguration prüfen (Ausgabe kann Passwörter enthalten — nicht kopieren)
docker compose --env-file app.env -f compose.production.yml exec -T app php artisan config:show database
```

### Regeln

| Befehl | Production |
|---|---|
| `optimize` | Nach Deploy normal; manuell bei Config-Änderungen sinnvoll |
| `optimize:clear` | **Nicht** ohne Grund im laufenden Betrieb — Performance-Einbruch |
| `migrate --force` | Nur über `deploy.sh` oder nach vollständiger Prüfung + Backup |
| `migrate:fresh` | **VERBOTEN** |
| `migrate:reset` | **VERBOTEN** |
| `db:wipe` | **VERBOTEN** |

---

## 9. Production-Datenbank ansehen

PostgreSQL läuft nur im Docker-Netz `dentalfinance_internal` — Port `5432` ist **nicht** öffentlich.

### Interaktive Sitzung

```bash
# [SERVER: deploy]
docker exec -it dentalfinance_database sh -lc '
PGPASSWORD="$POSTGRES_PASSWORD" \
psql \
  -h 127.0.0.1 \
  -U "$POSTGRES_USER" \
  -d "$POSTGRES_DB"
'
```

Credentials kommen aus `app.env` → Compose mappt `POSTGRES_*` aus `DB_*`.

### In `psql`

```sql
-- [PSQL]
\conninfo
\dt
\d+ clinics
\q
```

### Read-only-Transaktion (empfohlen für Ad-hoc-Abfragen)

```sql
-- [PSQL]
BEGIN READ ONLY;

SELECT COUNT(*) FROM clinics;
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM daily_work_rows;
SELECT COUNT(*) FROM payments;
SELECT COUNT(*) FROM lab_jobs;

COMMIT;
```

### Regeln

- Keine echten personenbezogenen Daten in Dokumentation oder Tickets
- `SELECT` bevorzugen
- Kein `UPDATE`, `DELETE`, `INSERT` ohne Backup, Review und klare `clinic_id`-Bedingung
- Direktes SQL umgeht Laravel-Validierung, Audit-Logik und Tenant-Isolation
- Keine Tabellenstruktur in `psql` ändern — nur Migrationen

### Nützliche Diagnose-Queries

```sql
-- [PSQL]
SELECT pg_size_pretty(pg_database_size(current_database()));

SELECT
    pid,
    usename,
    application_name,
    client_addr,
    state,
    query_start
FROM pg_stat_activity
WHERE datname = current_database()
ORDER BY query_start DESC;

SELECT *
FROM migrations
ORDER BY batch DESC, id DESC
LIMIT 30;
```

---

## 10. Datenbank-Backups

Offizielles Skript: `deploy/scripts/backup-database.sh`

### Manuell starten

```bash
# [SERVER: deploy]
APP_ENV_FILE=/opt/dentalfinance/app.env \
  /opt/dentalfinance/deploy/scripts/backup-database.sh
```

Das Skript:

1. Liest `APP_IMAGE` aus `.deploy-state` (falls nicht gesetzt)
2. Führt `pg_dump -Fc --no-owner --no-privileges` im `database`-Container aus
3. Schreibt nach `/opt/dentalfinance/backups/database/dentalfinance-YYYYMMDDTHHMMSSZ.dump`
4. Prüft Integrität mit `pg_restore --list`
5. Löscht Backups älter als `BACKUP_RETENTION_DAYS` (Standard: **14 Tage**)

### Backups auflisten

```bash
# [SERVER: deploy]
find /opt/dentalfinance/backups/database \
  -maxdepth 1 \
  -type f \
  -printf '%TY-%Tm-%Td %TH:%TM %10s %p\n' \
  | sort -r
```

### Datei prüfen

```bash
# [SERVER: deploy]
ls -lh /opt/dentalfinance/backups/database/dentalfinance-*.dump | tail -5

# Integrität (ohne Restore)
docker run --rm -i postgres:16-bookworm pg_restore --list \
  < /opt/dentalfinance/backups/database/dentalfinance-YYYYMMDDTHHMMSSZ.dump \
  | head
```

### Lokales vs. Offsite-Backup

| Art | Schutz vor |
|---|---|
| Server-Backup (`backups/database/`) | Fehlbedienung, einzelne kaputte Migration |
| **Offsite-Backup** (S3, anderer Provider) | Totalausfall VPS, Ransomware, versehentliches Löschen des Servers |

**Ein Backup auf demselben Server schützt nicht vor Verlust des gesamten Servers. Offsite-Backup bleibt erforderlich** (siehe `docs/PRODUCTION_DEPLOYMENT.md` §26).

### Cron (Beispiel)

```cron
15 2 * * * APP_ENV_FILE=/opt/dentalfinance/app.env /opt/dentalfinance/deploy/scripts/backup-database.sh >> /var/log/dentalfinance-backup.log 2>&1
```

Cron-Inhaber **auf dem Server prüfen**.

---

## 11. Restore

**GEFÄHRLICH** — ersetzt die gesamte Production-Datenbank.

Offizielles Skript: `deploy/scripts/restore-database.sh`

### Aufruf

```bash
# [SERVER: deploy] — GEFÄHRLICH
APP_ENV_FILE=/opt/dentalfinance/app.env \
  /opt/dentalfinance/deploy/scripts/restore-database.sh \
  /opt/dentalfinance/backups/database/dentalfinance-YYYYMMDDTHHMMSSZ.dump
```

### Ablauf laut Skript

1. Integritätsprüfung der Dump-Datei (`pg_restore --list`)
2. `APP_IMAGE` aus `.deploy-state` auflösen
3. Interaktive Bestätigung: **`RESTORE`** eintippen
4. **Safety-Backup** via `backup-database.sh`
5. `php artisan down --retry=60` (Wartungsmodus)
6. `compose stop worker scheduler`
7. Aktive DB-Verbindungen beenden (`pg_terminate_backend`)
8. `pg_restore --clean --if-exists --no-owner --no-privileges --exit-on-error --single-transaction`
9. `compose up -d app worker scheduler`
10. `php artisan up`
11. Interner Healthcheck (`http://127.0.0.1:8080/up`)

Bei Fehler: Skript meldet Safety-Backup-Pfad für manuelle Wiederherstellung.

### Vorbedingungen (Checkliste)

- [ ] Zusätzliches aktuelles Backup erstellt
- [ ] Richtige `.dump`-Datei verifiziert (`pg_restore --list`)
- [ ] Wartungsfenster kommuniziert
- [ ] Keine aktiven Benutzer schreiben in die DB
- [ ] **Niemals** aus Versehen gegen falsche Umgebung
- [ ] **Niemals** aus GitHub Actions ausführen

---

## 12. Anwendung sicher neu starten

### A. Nur App-Container neu starten

```bash
# [SERVER: deploy]
cd /opt/dentalfinance
export APP_ENV_FILE=/opt/dentalfinance/app.env
export APP_IMAGE="$(docker inspect --format '{{.Config.Image}}' dentalfinance_app)"

docker compose --env-file app.env -f compose.production.yml restart app
```

**Hinweis:** `restart` übernimmt geänderte `app.env`-Werte **nicht** zuverlässig — für Env-Änderungen **recreate** (siehe D).

### B. Worker neu starten

```bash
# [SERVER: deploy]
docker compose --env-file app.env -f compose.production.yml restart worker
```

Besser vor Deploy: `queue:restart` (siehe unten).

### C. Scheduler neu starten

```bash
# [SERVER: deploy]
docker compose --env-file app.env -f compose.production.yml restart scheduler
```

### D. Gesamten Application-Stack neu erstellen (nach Image- oder Env-Änderung)

Verwende dieselbe Variante wie `deploy.sh` (`compose_up_application` in `deploy-common.sh`):

```bash
# [SERVER: deploy]
cd /opt/dentalfinance
export APP_ENV_FILE=/opt/dentalfinance/app.env
export APP_IMAGE="$(grep '^APP_IMAGE=' .deploy-state | cut -d= -f2-)"

docker compose \
  --env-file "${APP_ENV_FILE}" \
  -f compose.production.yml \
  up -d \
  --timeout 30 \
  --wait \
  --wait-timeout 180 \
  --remove-orphans
```

### E. Vollständiges Deployment (bevorzugt)

**GitHub Actions → Deploy Production** (Merge nach `main` oder `workflow_dispatch`)

### Bevorzugte Reihenfolge

| Priorität | Maßnahme |
|---|---|
| 1 | GitHub Actions Deploy |
| 2 | Nur Queue neu laden: `php artisan queue:restart` |
| 3 | Einzelner Service: `compose restart <service>` |
| 4 | Recreate nach `app.env`-Änderung: `compose up -d --timeout 30 --wait --wait-timeout 180 --remove-orphans` |
| 5 | Manuell `deploy.sh <40-char-sha>` nur wenn Actions nicht verfügbar |

### Warnungen

- **Kein** `docker compose down` oder `down -v`
- Datenbank **nicht** unnötig neu starten
- Kein unkontrolliertes `kill -9`
- Vor Deployment stoppt `deploy.sh` Worker/Scheduler kontrolliert (`queue:restart` → `compose stop`)

---

## 13. `app.env` ändern

### Bearbeiten

```bash
# [SERVER: kareem]
sudo -u deploy nano /opt/dentalfinance/app.env
```

oder nach `sudo -iu deploy`:

```bash
# [SERVER: deploy]
nano /opt/dentalfinance/app.env
```

### Regeln

- Nur serverseitig — **niemals** in Git committen
- Vor größeren Änderungen: Kopie anlegen (`cp app.env app.env.bak.$(date -u +%Y%m%dT%H%M%SZ)`)
- Secrets nur auf **Vorhandensein** prüfen, nicht anzeigen

### Sichere Prüfung (Beispiel)

```bash
# [SERVER: kareem] oder [SERVER: deploy]
if sudo grep -Eq '^MAIL_PASSWORD=.+$' /opt/dentalfinance/app.env 2>/dev/null \
   || grep -Eq '^MAIL_PASSWORD=.+$' /opt/dentalfinance/app.env 2>/dev/null; then
    echo "MAIL_PASSWORD: set"
else
    echo "MAIL_PASSWORD: MISSING OR EMPTY"
fi
```

Weitere Pflichtvariablen: `APP_KEY`, `DB_PASSWORD`, `ACCOUNTING_PATIENT_REFERENCE_HMAC_KEY` (siehe `docs/PRODUCTION_DEPLOYMENT.md` §33).

### Nach Änderung

1. Rechte prüfen: `stat -c '%U:%G %a' app.env` → `deploy:deploy 600`
2. Container **neu erstellen** (`compose up -d …`), nicht nur `restart`
3. Healthcheck: `curl -fsS https://dentalfinance.eu/up`

---

## 14. Deployment über GitHub Actions

**Workflow:** `.github/workflows/deploy-production.yml`  
**Name in GitHub:** `Deploy Production`

### Trigger

| Auslöser | Verhalten |
|---|---|
| Push auf `main` | Automatisch (nach Tests + Build) |
| `workflow_dispatch` | Manuell in GitHub UI |

### Jobs (Reihenfolge)

1. **test** — `php artisan test`, `pint --test`
2. **build-and-push** — Docker-Image nach GHCR (`ghcr.io/<repo>:<sha>` + `:latest`)
3. **deploy** — SSH zu Production, rsync, `deploy.sh ${GITHUB_SHA}`

### Deploy-Schritte (remote)

1. `compose.production.yml` rsync (ohne `--delete` auf Root)
2. `deploy/` rsync (mit `--delete` nur auf `deploy/`)
3. SSH: `APP_ENV_FILE=/opt/dentalfinance/app.env deploy/scripts/deploy.sh <SHA>`
4. SSH-Keepalive: `ServerAliveInterval=30`, `ServerAliveCountMax=10`, `TCPKeepAlive=yes`

Geschützte Server-Pfade (werden **nicht** überschrieben): `app.env`, `.deploy-state`, `.deploy.lock`, `backups/`

### Fehlgeschlagenen Run analysieren

1. GitHub → Actions → fehlgeschlagener Run
2. Job identifizieren (`test`, `build-and-push`, `deploy`)
3. Log des fehlgeschlagenen Steps lesen
4. Bei `deploy`: SSH-Verbindung, `deploy.sh`-Ausgabe, Rollback-Meldung prüfen

### Re-run

| Situation | Re-run sinnvoll? |
|---|---|
| Flüchtiger Netzwerkfehler (SSH Broken pipe) | Ja, nach Prüfung ob kein halbes Deploy läuft (`.deploy.lock`) |
| Fehlerhafter Code / Migration | **Nein** — erst Fix auf `main`, neuer Commit |
| Alter Run erneut starten | Verwendet **immer den Commit des Runs** — nicht den aktuellen `main`-Stand |

### CI vs. Production-Deploy

| Workflow | Zweck |
|---|---|
| `ci.yml` | PR/Push-Qualität (Tests, Pint, Shellcheck) |
| `deploy-production.yml` | Production-Build + Deploy |

### Rollback-Verhalten

- Bei fehlgeschlagenem Healthcheck nach Deploy: `deploy.sh` setzt `APP_IMAGE` auf vorherigen Wert aus `.deploy-state`
- Separate Meldungen für Rollback-Erfolg vs. Rollback-Fehlschlag
- **Kein** automatisches DB-Rollback
- **Erster erfolgreicher Deploy:** kein vorheriges Image → kein automatischer Rollback möglich

### `.deploy-state`

Nach erfolgreichem Deploy:

```dotenv
APP_IMAGE=ghcr.io/owner/repo:<40-char-sha>
DEPLOYED_AT=2026-06-30T12:00:00Z
```

---

## 15. Manuelles Deployment und Rollback

### Manuelles Deployment

Nur wenn GitHub Actions nicht verfügbar:

```bash
# [SERVER: deploy]
APP_ENV_FILE=/opt/dentalfinance/app.env \
  /opt/dentalfinance/deploy/scripts/deploy.sh <40-zeichen-git-commit-sha>
```

Voraussetzungen:

- SHA muss **exakt 40 Hex-Zeichen** sein
- Image muss in GHCR existieren (`GHCR_IMAGE` in `app.env` + Tag)
- Kein paralleles Deploy (`.deploy.lock` via `flock`)

### Ablauf `deploy.sh` (Repository-Stand)

1. Lock prüfen
2. `docker pull` neues Image
3. `wait_for_database`
4. `queue:restart` → Worker/Scheduler stoppen
5. `backup-database.sh`
6. `compose run … migrate --force`
7. `compose up -d --timeout 30 --wait --wait-timeout 180 --remove-orphans`
8. `php artisan optimize`
9. Interner Healthcheck
10. Bei Fehler: Rollback auf `PREVIOUS_IMAGE` aus `.deploy-state`
11. `.deploy-state` aktualisieren

### Manueller Rollback (nur App-Image)

```bash
# [SERVER: deploy]
cd /opt/dentalfinance
export APP_ENV_FILE=/opt/dentalfinance/app.env
export APP_IMAGE="$(grep '^APP_IMAGE=' .deploy-state | tail -1 | cut -d= -f2-)"

# Vorheriges Image manuell setzen, wenn bekannt:
# export APP_IMAGE=ghcr.io/owner/clinic-111:<previous-sha>

docker compose \
  --env-file "${APP_ENV_FILE}" \
  -f compose.production.yml \
  up -d \
  --timeout 30 \
  --wait \
  --wait-timeout 180 \
  --remove-orphans
```

**Warnung:** Rollback der App **kehrt Migrationen nicht um**. Bei Migrationsproblemen: Backup/Restore oder manuelle DBA-Maßnahme.

---

## 16. Betriebssystem und Docker verwalten

Als `kareem` mit `sudo`:

```bash
# [SERVER: kareem]
sudo systemctl status docker
sudo systemctl status ssh
sudo systemctl status fail2ban    # zu prüfen — ggf. nicht installiert

sudo journalctl -u docker --since "1 hour ago" --no-pager
sudo journalctl -u ssh --since "1 hour ago" --no-pager
```

### Speicher

```bash
# [SERVER: kareem]
free -h
swapon --show
df -h
df -ih
```

### Docker-Speicher

```bash
# [SERVER: deploy]
docker system df
docker image ls
docker volume ls
```

### Regeln

- **Kein** blindes `docker system prune --volumes`
- **Keine** Volumes entfernen (`dentalfinance_postgres_data`, `dentalfinance_app_storage`, …)
- Alte Images nur löschen, wenn Rollback-Image in `.deploy-state` / GHCR noch verfügbar ist

---

## 17. Netzwerk und Ports

```bash
# [SERVER: kareem]
sudo ss -lntup
```

### Erwartet öffentlich

| Port | Dienst |
|---|---|
| 22 | SSH |
| 80 | HTTP (Caddy → HTTPS-Redirect) |
| 443 | HTTPS (FrankenPHP/Caddy) |

### Nicht öffentlich

| Port | Dienst |
|---|---|
| 5432 | PostgreSQL (nur Docker-intern) |
| 8080 | Interner Healthcheck (nur `127.0.0.1` im Container) |
| 2019 | Caddy-Admin-API (nicht publiziert in Compose) |
| 2375/2376 | Docker API |

### Schutzebenen

1. **Provider-Firewall** (IONOS laut `docs/PRODUCTION_DEPLOYMENT.md`) — nur 22/80/443
2. **Host-`ss`-Prüfung** — nichts Unerwartetes auf `0.0.0.0`
3. **Compose** — PostgreSQL ohne `ports:`-Mapping

`8080` ist in `deploy/Caddyfile` mit `bind 127.0.0.1` gebunden — nicht von außen erreichbar.

---

## 18. Queue-Worker und Scheduler

### Konfiguration (`compose.production.yml`)

| Service | Befehl | `stop_grace_period` | Healthcheck |
|---|---|---|---|
| `worker` | `php artisan queue:work database --sleep=3 --tries=3 --timeout=3600 --max-time=3600` | `120s` | `disable: true` |
| `scheduler` | `php artisan schedule:work` | `30s` | `disable: true` |

### PCNTL

Erforderlich für zuverlässige Queue-Timeouts (`Dockerfile`: `install-php-extensions pcntl`).

```bash
# [SERVER: deploy]
docker exec dentalfinance_worker php -r '
echo extension_loaded("pcntl") ? "pcntl=yes\n" : "pcntl=no\n";
'
```

Erwartet: `pcntl=yes`

### Prozesse prüfen

```bash
# [SERVER: deploy]
docker top dentalfinance_worker -eo pid,ppid,stat,etime,cmd
docker top dentalfinance_scheduler -eo pid,ppid,stat,etime,cmd
```

### `queue:restart`

Signalisiert Workern, nach dem aktuellen Job zu beenden. Wird in `deploy.sh` **vor** Backup/Migration ausgeführt.

### Zukunft: lange Queue-Jobs

Aktuell keine produktiven lang laufenden Queue-Jobs (Excel-Importe sind synchron). Bei Einführung gemeinsam neu bewerten:

- `--timeout` / `--max-time`
- `retry_after` (Queue-Config)
- `stop_grace_period`
- Job-Idempotenz
- Deployment-Dauer

---

## 19. Häufige Störungen

### Website nicht erreichbar

| Schritt | Aktion |
|---|---|
| Symptom | Timeout, DNS-Fehler, Connection refused |
| Prüfung | `curl -I https://dentalfinance.eu/up`; `docker ps`; `sudo systemctl status docker` |
| Logs | `docker logs dentalfinance_app`; Caddy/FrankenPHP in App-Logs |
| Ursache | Container down, Docker daemon stopped, Firewall, DNS |
| Maßnahme | Container/Stack starten; Docker daemon starten |
| Abbruch | Wenn Datenbank beschädigt vermutet — kein blindes `down -v` |

### HTTP 500

| Prüfung | `docker compose logs app --tail=100` |
| Logs | Laravel-Exception in App-Logs |
| Maßnahme | Fehlerursache identifizieren; ggf. Rollback Image; **nicht** `optimize:clear` ohne Plan |

### Container `unhealthy` (app)

| Prüfung | `docker inspect dentalfinance_app`; intern `curl :8080/up` |
| Ursache | Laravel-Boot-Fehler, DB nicht erreichbar, Migration fehlgeschlagen |
| Maßnahme | Logs, `migrate:status`, DB-Health |

### Container `unhealthy` (worker/scheduler)

| Prüfung | Sollte **keinen** HTTP-Healthcheck haben — wenn doch: alte `compose.production.yml` |
| Maßnahme | Aktuelle Compose-Datei deployen (`healthcheck: disable: true`) |

### PostgreSQL nicht erreichbar

| Prüfung | `docker compose ps database`; `pg_isready` im Container |
| Logs | `docker logs dentalfinance_database` |
| Maßnahme | DB-Container starten; Speicherplatz prüfen |

### Speicherplatz voll

| Prüfung | `df -h`; `docker system df` |
| Maßnahme | Alte Logs/Backups bereinigen (Retention); **keine** Volumes löschen |

### GitHub Actions „Broken pipe“

| Ursache | Langer `deploy.sh`-Lauf, SSH-Idle |
| Maßnahme | Re-run nach Prüfung von `.deploy.lock` und Container-Status; Keepalive ist im Workflow konfiguriert |

### SMTP / Resend

| Prüfung | `MAIL_*` in `app.env` (nur Set/Not-Set); Resend-Dashboard |
| Test | `php artisan tinker` + `Mail::raw(...)` (siehe `docs/PRODUCTION_DEPLOYMENT.md` §21) |
| Hinweis | Production nutzt **Resend**, nicht Brevo (ADR-038) |

### Migration fehlgeschlagen

| Maßnahme | Deploy bricht ab; ggf. Rollback Image; DB **nicht** automatisch zurückgesetzt |
| Abbruch | Kein `migrate:fresh`; Restore aus Backup erwägen |

### `APP_IMAGE` / `APP_ENV_FILE` fehlt

| Symptom | Compose-Fehler „Set APP_IMAGE…“ |
| Maßnahme | `export APP_ENV_FILE=…`; Image aus Container oder `.deploy-state` |

### Falsche Rechte auf `app.env`

```bash
# [SERVER: kareem]
sudo chown deploy:deploy /opt/dentalfinance/app.env
sudo chmod 600 /opt/dentalfinance/app.env
```

---

## 20. Gefährliche und verbotene Befehle

> **WARNUNG:** Die folgenden Befehle können Production dauerhaft zerstören oder Daten unwiederbringlich löschen.

```bash
# GEFÄHRLICH — Datenverlust
docker compose down -v
docker volume rm dentalfinance_postgres_data
docker system prune --volumes

# GEFÄHRLICH — Laravel / DB
php artisan migrate:fresh
php artisan migrate:reset
php artisan db:wipe

# GEFÄHRLICH — SQL
DROP DATABASE ...
DROP TABLE ...
TRUNCATE ...
DELETE FROM ...;          -- ohne WHERE und ohne clinic_id
UPDATE ...;              -- ohne WHERE und ohne clinic_id

# GEFÄHRLICH — Sonstiges
# PostgreSQL-Dateien im Volume manuell editieren
chmod 777 /opt/dentalfinance/app.env
cat /opt/dentalfinance/app.env    # Secrets leaken
git add app.env && git commit     # Secrets in Git
# Port 5432 in Firewall oder Compose öffnen
```

---

## 21. Tägliche, wöchentliche und monatliche Checks

### Täglich

- [ ] `https://dentalfinance.eu/up` → HTTP 200
- [ ] `docker compose ps` — alle Container `running`, app + DB `healthy`
- [ ] Keine kritischen Fehler in App-Logs (`grep -i error`)
- [ ] Resend-Dashboard: Zustellfehler / Bounces prüfen

### Wöchentlich

- [ ] Backup-Datei in `backups/database/` vorhanden und > 0 Byte
- [ ] Backup-Größe plausibel (nicht plötzlich 0 oder stark geschrumpft)
- [ ] `df -h` — ausreichend freier Speicher
- [ ] Fail2Ban-Status (**zu prüfen**)
- [ ] `docker inspect` — ungewöhnliche `RestartCount`
- [ ] Ausstehende Ubuntu-Sicherheitsupdates (`apt list --upgradable`)

### Monatlich

- [ ] Restore-Test in **isoliierter** Umgebung (nicht auf Production)
- [ ] Offsite-Backup-Kopie verifizieren
- [ ] API-/SMTP-Key-Rotation bewerten
- [ ] Benutzerkonten (`kareem`, `deploy`) und SSH-Keys prüfen
- [ ] Alte Docker-Images kontrolliert bereinigen (Rollback-Fähigkeit beachten)
- [ ] Legal-Env (`docs/LEGAL_SETUP.md`) auf Vollständigkeit
- [ ] Dependabot / Security Advisories im Repository prüfen

---

## 22. Schnellreferenz

```bash
# [LOCAL MAC] — Serverlogin
ssh -o IdentitiesOnly=yes -i ~/.ssh/dentalfinance_admin kareem@65.108.82.159

# [SERVER: kareem] — Wechsel zu deploy
sudo -iu deploy

# [SERVER: deploy] — Basis
cd /opt/dentalfinance
export APP_ENV_FILE=/opt/dentalfinance/app.env
export APP_IMAGE="$(docker inspect --format '{{.Config.Image}}' dentalfinance_app)"

# Status
docker compose --env-file app.env -f compose.production.yml ps -a

# Health (extern)
curl -sS -o /dev/null -w 'Health: %{http_code}\n' https://dentalfinance.eu/up

# Logs
docker compose --env-file app.env -f compose.production.yml logs --tail=200 app

# Datenbank
docker exec -it dentalfinance_database sh -lc 'PGPASSWORD="$POSTGRES_PASSWORD" psql -h 127.0.0.1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"'

# Backup
APP_ENV_FILE=/opt/dentalfinance/app.env /opt/dentalfinance/deploy/scripts/backup-database.sh

# Queue neu laden
docker compose --env-file app.env -f compose.production.yml exec -T app php artisan queue:restart

# Worker-Prozess
docker top dentalfinance_worker -eo pid,ppid,stat,etime,cmd

# App sicher neu erstellen
docker compose --env-file app.env -f compose.production.yml up -d --timeout 30 --wait --wait-timeout 180 --remove-orphans

# Speicher
df -h && docker system df
```

**Production-URL:** https://dentalfinance.eu
