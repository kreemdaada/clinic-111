# Project Overview

## Project Goal

**Dental Clinic Accounting System — V1** is an accounting engine for a dental clinic. It is **not** a clinic management system. It does not handle appointments, medical records, diagnosis, prescriptions, insurance, or patient medical history.

The main goal is to:

1. Import a daily Excel report (or, in V2, accept manual web entry).
2. Calculate collected payments, lab job costs, treatment counts, doctor income, clinic income, and daily/monthly reports.

All business rules (doctors, treatments, lab prices, commission rules) are stored in the database — never hardcoded in application logic.

---

## What This System Is Not

| Out of scope | Reason |
|---|---|
| Appointments | Accounting only |
| Medical records | No clinical data |
| Diagnosis / prescriptions | No clinical data |
| Insurance modules | Not required for V1 |
| Patient medical history | Only accounting traceability fields stored |

---

## Main Workflows

1. **Daily Excel Import** — Upload Excel → parse rows → create payments → parse treatments → calculate lab jobs.
2. **Daily Report Review** — View a calculated daily report with rows, payments, work items, and lab jobs.
3. **Monthly Income Report** — Aggregate payments and lab costs per doctor for a calendar month.

See [WORKFLOWS.md](./WORKFLOWS.md) for step-by-step details.

---

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        API Layer                            │
│  AuthController | DailyReportController | MonthlyIncome...  │
│  Form Requests | Role Middleware | Sanctum Auth              │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                     Service Layer                           │
│  Import: DailyReportImportService, ExcelDailyReportParser   │
│  Accounting: PaymentCalculation, TreatmentParser,           │
│              LabJobCalculation, MonthlyIncomeCalculation    │
│  Support: LabPriceResolver, MoneyCalculator                 │
│  Audit: AuditLogService                                     │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                   Eloquent Models / DB                      │
│  doctors | labs | treatments | lab_prices | doctor_fixed_fees│
│  daily_reports | daily_work_rows | work_items | lab_jobs    │
│  payments | audit_logs | users                              │
└─────────────────────────────────────────────────────────────┘
```

### Design Principles

- **Thin controllers** — validate, call service, return JSON.
- **One service = one responsibility** — import, parse, calculate, and report are separate.
- **Database-driven rules** — no `if doctor === 'Dr Jack'` in code; use `doctor_id`, `commission_type`, `lab_prices`.
- **V2-ready** — manual entry will create the same `daily_work_rows`, `payments`, `work_items`, and `lab_jobs` without schema changes.
- **Deterministic calculations** — all money uses `bcmath`; no floats, no AI parsing.

---

## Domain Terminology

| Term | Meaning | Not to be confused with |
|---|---|---|
| **TOTAL** | Total collected payments in AED | Treatment value or invoice total |
| **JOB** | Total lab cost for a row/report | A job number or work order ID |
| **NET_TOTAL** | TOTAL minus LAB_COST | Net profit before doctor split |
| **DOCTOR_INCOME** | Amount owed to the doctor | Gross treatment revenue |
| **CLINIC_INCOME** | NET_TOTAL minus DOCTOR_INCOME | — |
| **DHS** | Cash payment in AED (dirhams) | — |
| **USD** | Cash payment in US dollars | Converted to AED using exchange rate |
| **VISA** | Card payment (stored in AED) | — |
| **treatment_text** | Raw free-text field from Excel describing procedures | Structured treatment codes |
| **work_item** | Parsed treatment line (code + quantity) | Excel row |
| **lab_job** | Calculated lab cost for one work item | External lab work order |
| **daily_work_row** | One accounting row from the daily report | A patient medical record |
| **commission_type** | `percentage` or `fixed` — how doctor income is calculated | Lab commission |
| **lab_price** | Unit cost charged by a lab for a treatment | Treatment fee charged to patient |

---

## Technology Stack

| Component | Choice |
|---|---|
| Framework | Laravel 13 |
| Database | SQLite (dev) / MySQL or PostgreSQL (production) |
| Auth | Laravel Sanctum (API tokens) |
| Excel parsing | PhpSpreadsheet |
| Money math | PHP `bcmath` via `MoneyCalculator` |

---

## Default Seed Data

After `php artisan migrate --seed`, the system includes:

- **Labs:** `MAIN_LAB`, `RIYADH_LAB`
- **Doctors:** `JACK` (35%), `RIYAD` (35%, Riyad lab), `PURIYA` (25%), `WA` (fixed fees)
- **Treatments:** MC (105 AED), ZIR, IMPL-CR, IMPL-ZIR, POST, ABT, IMPL (with lab cost); CF, AF, RCT, RE-RCT, REPAIR, REMOV, BG, SINUS (without lab cost)
- **Users:** admin, accountant, viewer (see README)

---

## Privacy Note

The system stores accounting-related patient references (`patient_name`, `mrn`, `file_number`) for traceability only. It does **not** store medical records.

---

## Related Documentation

| Document | Contents |
|---|---|
| [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) | All tables, fields, relationships |
| [BUSINESS_RULES.md](./BUSINESS_RULES.md) | All accounting formulas |
| [WORKFLOWS.md](./WORKFLOWS.md) | Step-by-step process flows |
| [SERVICES.md](./SERVICES.md) | Service class reference |
| [API.md](./API.md) | REST API endpoints |
| [DECISIONS.md](./DECISIONS.md) | Architectural decision log |

---

## What Changed

**Initial documentation — 2026-06-19**

Created:

- `docs/PROJECT_OVERVIEW.md` — project goal, architecture, terminology

Documents the V1 MVP accounting engine built from scratch.
