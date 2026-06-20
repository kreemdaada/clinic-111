# Dental Clinic Accounting System - V1

Accounting engine for a dental clinic. Imports daily Excel reports and calculates collected payments, lab costs, doctor income, and clinic income.

## Features

- Excel daily report import with full calculation pipeline
- Database-driven doctors, treatments, lab prices, and commission rules
- Monthly income summaries per doctor
- Role-based API access (admin, accountant, viewer)
- Audit logging for imports and sensitive actions

## Documentation

Full developer documentation is in the [`docs/`](./docs/) folder:

| Document | Description |
|---|---|
| [PROJECT_OVERVIEW.md](./docs/PROJECT_OVERVIEW.md) | Goal, architecture, terminology |
| [DATABASE_SCHEMA.md](./docs/DATABASE_SCHEMA.md) | All tables, fields, relationships |
| [BUSINESS_RULES.md](./docs/BUSINESS_RULES.md) | Accounting formulas |
| [WORKFLOWS.md](./docs/WORKFLOWS.md) | Step-by-step process flows |
| [SERVICES.md](./docs/SERVICES.md) | Service class reference |
| [API.md](./docs/API.md) | REST API endpoints |
| [DECISIONS.md](./docs/DECISIONS.md) | Architectural decision log |

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
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
- `GET /api/monthly-income?month=2026-01`
- `GET /api/doctors`
- `GET /api/treatments`
- `GET /api/labs`

## Business Rules

- **TOTAL** = collected payments (DHS + USD converted + VISA), not treatment value
- **JOB** = SUM(quantity × lab_price) for treatments with lab cost
- **NET_TOTAL** = TOTAL - LAB_COST
- Percentage doctors: DOCTOR_INCOME = NET_TOTAL × commission_percentage / 100
- Fixed doctors: DOCTOR_INCOME from `doctor_fixed_fees` × quantity

## Privacy Note

This system stores accounting-related patient references (name, MRN, file number) for traceability only — not medical records.

## Testing

```bash
php artisan test
```
