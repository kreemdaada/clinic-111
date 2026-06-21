# Dental Clinic Accounting System - V1

Accounting engine for a dental clinic. Imports daily Excel reports and calculates collected payments, lab costs, doctor income, and clinic income.

## Features

- Excel daily report import with validation warnings and privacy-safe patient references
- All valid treatments stored as work items; lab jobs only for lab-cost codes
- Database-driven doctors, treatments, lab prices, and commission rules
- Original Income Excel export with per-doctor column profiles
- Monthly income summaries per doctor
- Role-based API access (admin, accountant, viewer)
- Audit and extraction logging

## Documentation

Full developer documentation is in the [`docs/`](./docs/) folder:

| Document | Description |
|---|---|
| [PROJECT_OVERVIEW.md](./docs/PROJECT_OVERVIEW.md) | Goal, architecture, terminology |
| [DATABASE_SCHEMA.md](./docs/DATABASE_SCHEMA.md) | All tables, fields, relationships |
| [BUSINESS_RULES.md](./docs/BUSINESS_RULES.md) | Accounting formulas |
| [WORKFLOWS.md](./docs/WORKFLOWS.md) | Import pipeline: extractor → parser → validation → lab calc |
| [SERVICES.md](./docs/SERVICES.md) | Service class reference |
| [API.md](./docs/API.md) | REST API endpoints |
| [DECISIONS.md](./docs/DECISIONS.md) | Architectural decision log |
| [TREATMENT_RULES.md](./docs/TREATMENT_RULES.md) | Treatment text format for Excel staff |

Additional README files:

- [`tests/Unit/README.md`](./tests/Unit/README.md) — unit test map
- [`database/migrations/README.md`](./database/migrations/README.md) — migration order
- [`database/seeders/README.md`](./database/seeders/README.md) — seed data reference

## Setup

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

**Important:** Excel files can be ~4 MB. Use `./bin/serve` (not plain `php artisan serve`) so PHP allows uploads up to 20 MB.

**Alternative — import without HTTP upload (recommended for large files):**

```bash
php -d memory_limit=512M artisan daily-report:import "/path/to/daily report january 2026.xlsm"
```

## Default Users

| Email | Password | Role |
|---|---|---|
| admin@clinic.test | password | admin |
| accountant@clinic.test | password | accountant |
| viewer@clinic.test | password | viewer |

## API Endpoints

All routes except login require `Authorization: Bearer {token}`.

- `POST /api/login`
- `POST /api/logout`
- `POST /api/daily-reports/import` (admin, accountant)
- `GET /api/daily-reports/{id}`
- `GET /api/daily-reports/{id}/validation-summary`
- `GET /api/monthly-income?month=2026-01`
- `GET /api/doctors`
- `GET /api/treatments`
- `GET /api/labs`

## Business Rules

- **TOTAL** = collected payments (DHS + USD converted + VISA), not treatment value
- **JOB** = SUM(lab_job.total_cost_aed) for treatments with `has_lab_cost`
- **work_item** = every valid parsed treatment (CF, REMOV, ZIR, …)
- **NET_TOTAL** = TOTAL - LAB_COST
- Percentage doctors: DOCTOR_INCOME = NET_TOTAL × commission_percentage / 100
- Fixed doctors: DOCTOR_INCOME from `doctor_fixed_fees` × quantity

## Privacy

Patient name, MRN, and file number are never stored or returned by the API. A non-reversible HMAC hash is stored for traceability. See [WORKFLOWS.md](./docs/WORKFLOWS.md).

## Testing

```bash
php artisan test
```

See [tests/Unit/README.md](./tests/Unit/README.md) for unit test coverage map.
