# DentalFinance

**DentalFinance** is a multi-clinic dental accounting application. Clinics import daily Excel reports; the system calculates collected payments, lab costs, doctor income, nurse commissions where configured, and clinic income — with role-based access and audit logging.

Built with **Laravel**, **Blade**, and **PostgreSQL** in production (SQLite for local tests).

## What it does

- Import daily Excel / XLSM reports with validation warnings
- Store treatments as work items; create lab jobs only for lab-cost treatments
- Database-driven doctors, treatments, labs, prices, and commission rules (percentage or fixed fees)
- Per-clinic configuration — each practice sets its own doctors, fees, and labs
- Original Income Excel export with configurable per-doctor layouts
- Monthly income summaries and practice financial overview
- Roles: admin, accountant, viewer
- Privacy-safe patient references (HMAC — names/MRNs are not stored in the API)

Doctor codes and display names in public docs use **placeholders** (`NAME1` … `NAME4`). Real clinic master data lives only in each deployment’s database.

## Quick start (local)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Add to `.env`:

```env
ACCOUNTING_PATIENT_REFERENCE_HMAC_KEY=<openssl rand -hex 32>
```

```bash
php artisan migrate --seed
./bin/serve
```

Use `./bin/serve` (not plain `php artisan serve`) so PHP allows larger Excel uploads.

**Import a large file without HTTP upload:**

```bash
php -d memory_limit=512M artisan daily-report:import "/path/to/report.xlsm"
```

### Demo users (local seed only)

| Email | Password | Role |
|---|---|---|
| admin@clinic.test | password | admin |
| accountant@clinic.test | password | accountant |
| viewer@clinic.test | password | viewer |

## Documentation

Public docs live in [`docs/`](./docs/):

| Document | Description |
|---|---|
| [PROJECT_OVERVIEW.md](./docs/PROJECT_OVERVIEW.md) | Goals, architecture, terminology |
| [LEARNING_ROADMAP.md](./docs/LEARNING_ROADMAP.md) | Step-by-step orientation for new developers |
| [DATABASE_SCHEMA.md](./docs/DATABASE_SCHEMA.md) | Tables and relationships |
| [WORKFLOWS.md](./docs/WORKFLOWS.md) | Import and accounting pipelines |
| [SERVICES.md](./docs/SERVICES.md) | Service reference |
| [API.md](./docs/API.md) | REST / web API surface |
| [MULTI_CLINIC_ARCHITECTURE.md](./docs/MULTI_CLINIC_ARCHITECTURE.md) | Tenant isolation |
| [DECISIONS.md](./docs/DECISIONS.md) | Architecture decision records |
| [TREATMENT_RULES.md](./docs/TREATMENT_RULES.md) | Treatment text conventions for Excel |
| [LEGAL_SETUP.md](./docs/LEGAL_SETUP.md) | Legal page configuration |

Also useful:

- [`tests/Unit/README.md`](./tests/Unit/README.md) — unit test map
- [`database/migrations/README.md`](./database/migrations/README.md) — migration order
- [`database/seeders/README.md`](./database/seeders/README.md) — seed reference (placeholder doctor codes)

Detailed internal business-rule and development playbooks are **not** published in this repository.

## Core accounting ideas

- **TOTAL** = collected payments (cash / card / converted foreign currency), not list price of treatments
- **JOB** = sum of lab job costs for treatments with `has_lab_cost`
- **NET_TOTAL** = TOTAL − lab cost
- Percentage doctors: income = NET_TOTAL × commission %
- Fixed-fee doctors: income from configured `doctor_fixed_fees` × quantity (any doctor can use this mode — not tied to a specific person)

## Privacy

Patient name, MRN, and file number are not stored or returned by the API. A non-reversible HMAC reference is kept for traceability. See [WORKFLOWS.md](./docs/WORKFLOWS.md).

## Testing

```bash
php artisan test
```

## License / use

This repository is published for transparency and development. Do not commit secrets (`.env`, `app.env`, API keys, or production ops runbooks). Production credentials stay on the server only.
