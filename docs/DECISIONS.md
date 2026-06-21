# Architectural Decisions

Record of important design decisions. Add a new entry whenever a significant choice is made.

Format:

```
Decision: ...
Reason: ...
Date: YYYY-MM-DD
```

---

## ADR-001: TOTAL Means Collected Payments, Not Treatment Value

**Decision:** `paid_total_aed` and `payments.amount_aed` represent money collected from the patient (DHS + USD + VISA), not the treatment invoice value (`total_cost`).

**Reason:** The clinic calculates doctor income from collected payments, not from quoted treatment prices. A patient may pay partially or across multiple methods.

**Date:** 2026-06-19

---

## ADR-002: JOB Means Lab Cost, Not Job Number

**Decision:** The term JOB in business language maps to `lab_jobs.total_cost_aed`, not an external work order ID.

**Reason:** Clinic staff use "JOB" colloquially to mean the lab bill for a day's work. Storing it as calculated lab cost avoids confusion with external lab tracking systems.

**Date:** 2026-06-19

---

## ADR-003: Database-Driven Rules, No Hardcoded Doctor Names

**Decision:** All doctor commission logic reads from `doctors.commission_type`, `doctors.commission_percentage`, and `doctor_fixed_fees`. No `if ($doctor->name === 'Dr Jack')` anywhere in services.

**Reason:** Doctors, rates, and lab assignments change over time. Database configuration allows updates without code deployment.

**Date:** 2026-06-19

---

## ADR-004: Lab Price Fallback Chain

**Decision:** Lab prices resolve in order: (1) doctor-specific override, (2) default price where `doctor_id IS NULL`. Both scoped to the resolved lab.

**Reason:** Dr Riyad has different lab prices and a different default lab. Other doctors share default prices. Nullable `doctor_id` keeps the schema simple without a separate "is_default" flag.

**Date:** 2026-06-19

---

## ADR-005: bcmath for All Money Calculations

**Decision:** Use PHP `bcmath` via `MoneyCalculator` for all arithmetic. Never use float. Database columns are `decimal(12,2)`.

**Reason:** Floating-point arithmetic causes rounding errors in financial systems. bcmath provides deterministic, testable results.

**Date:** 2026-06-19

---

## ADR-006: Isolated Excel Parser

**Decision:** Excel reading is isolated in `ExcelDailyReportParser`, separate from `DailyReportImportService`.

**Reason:** V2 will replace Excel upload with manual web form entry. The import orchestrator stays the same; only the parser/input layer changes.

**Date:** 2026-06-19

---

## ADR-007: Rule-Based Treatment Parser (No AI)

**Decision:** `TreatmentParserService` uses regex pattern matching against known treatment codes from the database. No AI or ML.

**Reason:** Accounting calculations must be deterministic and testable. Regex parsing is predictable, fast, and fully unit-testable.

**Date:** 2026-06-19

---

## ADR-008: Single Pipeline for Excel and Future Manual Entry

**Decision:** V2 manual entry will create the same records (`daily_work_rows`, `payments`, `work_items`, `lab_jobs`) and call the same calculation services.

**Reason:** Avoids duplicating business logic. The only difference is the input source (Excel parser vs. web form).

**Date:** 2026-06-19

---

## ADR-009: Approved Reports Are Read-Only

**Decision:** Reports with `status = approved` cannot be re-imported for the same date or reprocessed.

**Reason:** Financial data integrity. Approved reports represent finalized accounting periods.

**Date:** 2026-06-19

---

## ADR-010: Soft Status Instead of Physical Deletes

**Decision:** Financial records (`lab_jobs`, reports) are never physically deleted. Use status fields (`cancelled`, `failed`) instead.

**Reason:** Audit trail and regulatory compliance. Accounting systems require immutable history.

**Date:** 2026-06-19

---

## ADR-011: Sanctum for API Authentication

**Decision:** Use Laravel Sanctum token-based auth with role middleware, not session-based web auth.

**Reason:** V1 is API-only (no frontend). Sanctum is lightweight and sufficient for MVP. Roles enforced via `EnsureUserHasRole` middleware.

**Date:** 2026-06-19

---

## ADR-012: Private File Storage for Uploads

**Decision:** Excel uploads stored on the `local` disk under `daily-reports/`, outside the public directory.

**Reason:** Uploaded files may contain patient names and financial data. Public access is a security risk.

**Date:** 2026-06-19

---

## ADR-013: Store Raw Excel Data for Audit

**Decision:** Every imported row stores the full parsed row in `daily_work_rows.raw_data_json`.

**Reason:** Enables debugging import issues, supports audit requirements, and allows re-parsing if the parser improves.

**Date:** 2026-06-19

---

## ADR-014: Percentage Rounding — Half-Up to 2 Decimals

**Decision:** `MoneyCalculator::percentage()` rounds half-up to 2 decimal places (e.g. 12456.9375 → 12456.94).

**Reason:** Standard financial rounding. bcmath truncates by default; explicit rounding prevents off-by-one-cent errors in doctor payouts.

**Date:** 2026-06-19

---

## ADR-015: Explicit Null Checks Over Shorthand Operators

**Decision:** Prefer explicit `if ($value === null)` and `if ($object !== null)` over PHP shorthand operators `??`, `??=`, and `?->` in application code.

**Reason:** The team prioritizes readability for developers who may not be familiar with modern PHP syntax. Explicit checks are self-documenting without sacrificing correctness. Array defaults from parsed Excel rows use a dedicated `getParsedRowValue()` helper instead of inline `??`.

**Date:** 2026-06-19

---

## ADR-016: Doctor Income Export Profiles in Database

**Decision:** Server Income Excel layout (sheet name, column letters, layout type) is stored in `doctor_income_export_profiles`, loaded by `DoctorIncomeExportProfileService`. No hardcoded doctor profile arrays in export code.

**Reason:** New or inactive doctors should be configurable via DB/seeder/admin without code deploy. JOB calculation remains one pipeline (`LabJobCalculationService` + `lab_prices`); only Excel layout is profile-driven.

**Date:** 2026-06-19

---

## What Changed

**Updated — 2026-06-19**

- Seed data aligned with clinic commission and lab price sheet (MC 105 AED, Dr Riyad)

**Updated — 2026-06-19**

Added:

- ADR-015 — explicit null checks over shorthand operators

**Initial documentation — 2026-06-19**

Created:

- `docs/DECISIONS.md` — 14 architectural decision records (ADR-001 through ADR-014)

Documents all major design choices made during V1 MVP implementation.
