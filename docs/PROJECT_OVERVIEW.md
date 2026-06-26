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

1. **Daily Excel Import** — Upload Excel → extract rows → privacy hash → validate treatments → work items → lab jobs → review warnings if needed.
2. **Daily Report Review** — View calculated report, validation summary, extraction logs (no patient names in API).
3. **Monthly Income Report** — Aggregate payments and lab costs per doctor for a calendar month.

4. **Administration** — Configuration dashboard and modules for clinics, doctors, laboratories, treatments, lab prices, no-commission fees, and users (Milestones 01–06).

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
│  Import: DailyReportImportService, ExcelDailyReportParser,   │
│          TreatmentImportValidationService, ImportExtractionLog│
│  Accounting: PaymentCalculation, TreatmentParser,           │
│              LabJobCalculation, MonthlyIncomeCalculation    │
│  Export: DoctorsIncomeExcelExportService, ExportProfiles    │
│  Support: LabPriceResolver, MoneyCalculator, PatientHash    │
│  Audit: AuditLogService, ImportActivityLogger               │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                   Eloquent Models / DB                      │
│  doctors | labs | treatments | lab_prices | doctor_fixed_fees│
│  clinics | daily_reports | daily_work_rows | work_items | lab_jobs    │
│  payments | daily_report_import_warnings | audit_logs      │
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
| **work_item** | Parsed treatment line (code + quantity) — all valid codes | Only lab-cost codes |
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

- **Clinic:** `CLINIC_111` (Clinic 111, AED, Asia/Dubai — ADR-026)
- **Labs:** `MAIN_LAB`, `RIYADH_LAB`
- **Doctors:** `JACK` (35%), `RIYAD` (35%, Riyad lab), `PURIYA` (25%), `WA` (fixed fees)
- **Treatments with lab cost (JOB):** MC (105 AED), ZIR, IMPL-CR, IMPL-ZIR, POST, ABT, IMPL, REMOV (100 AED)
- **Treatments without lab cost (work_item only):** CF, AF, RCT, RE-RCT, REPAIR, BG, SINUS, …
- **Users:** admin, accountant, viewer (see README)

See also: `database/seeders/README.md`

---

## Privacy Note

Patient name, MRN, and file number are **never stored or exposed** in API responses. During import they are read in memory only and replaced with `patient_reference_hash` (HMAC-SHA256 using `ACCOUNTING_PATIENT_REFERENCE_HMAC_KEY`). Uploaded Excel files are deleted after successful import by default.

---

## Related Documentation

| Document | Contents |
|---|---|
| [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) | All tables, fields, relationships |
| [BUSINESS_RULES.md](./BUSINESS_RULES.md) | All accounting formulas |
| [WORKFLOWS.md](./WORKFLOWS.md) | Import pipeline: extractor → parser → validation |
| [TREATMENT_RULES.md](./TREATMENT_RULES.md) | Excel treatment text format for staff |
| [SERVICES.md](./SERVICES.md) | Service class reference |
| [API.md](./API.md) | REST API endpoints |
| [DECISIONS.md](./DECISIONS.md) | Architectural decision log |
| [../tests/Unit/README.md](../tests/Unit/README.md) | Unit test map |
| [../database/migrations/README.md](../database/migrations/README.md) | Migrations |
| [../database/seeders/README.md](../database/seeders/README.md) | Seeders |

---

## What Changed

**Updated — 2026-06-26**

- Configuration models now belong to `CLINIC_111` via `clinic_id` (Milestone 07, ADR-026)

**Updated — 2026-06-26**

- Clinic tenant model and administration (Milestone 06, ADR-026)

**Updated — 2026-06-26**

- Lab price administration (Milestone 03) added to admin workflows

**Updated — 2026-06-26**

- Treatments administration (Milestone 02) added to admin workflows

**Updated — 2026-06-19**

- Privacy-safe import, validation warnings, work_items for all valid treatments
- Architecture diagram updated with validation + export services

**Initial documentation — 2026-06-19**

Created:

- `docs/PROJECT_OVERVIEW.md` — project goal, architecture, terminology

Documents the V1 MVP accounting engine built from scratch.
