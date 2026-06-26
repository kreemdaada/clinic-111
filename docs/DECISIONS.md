# Dental Clinic Accounting System

# Architecture Decision Records (ADR)

---

# Purpose

This document records every significant architectural decision made during the lifetime of this project.

Its objectives are:

* Explain why a decision was made.
* Document the business and technical context.
* Record alternative solutions that were considered.
* Describe the long-term consequences.
* Prevent repeating old discussions.
* Help future developers understand the architecture.

This document is the **Single Source of Truth** for all architectural decisions.

Every developer must read this document before introducing major architectural changes.

---

# Scope

This document contains only architecture-level decisions.

Examples include:

* Database design
* Accounting engine
* Security architecture
* Multi-Clinic architecture
* Service Layer decisions
* Import pipeline
* Privacy strategy
* API architecture

This document must **not** contain:

* Bug fixes
* Small refactoring
* UI improvements
* CSS changes
* Minor optimizations

---

# Rules

Every important architectural decision must receive a new ADR.

Existing ADRs must never be deleted.

Existing ADRs should rarely be modified.

If an old decision changes:

* Create a new ADR.
* Reference the previous ADR.
* Explain why the architecture changed.

Never rewrite project history.

Architecture history is valuable.

---

# ADR Lifecycle

Each ADR has one of the following statuses.

**Accepted**

The decision is active.

**Deprecated**

The decision is no longer recommended.

**Superseded**

A newer ADR replaces this one.

**Proposed**

Under discussion.

---

# ADR Template

Every new ADR should follow this structure.

```markdown
## ADR-XXX

### Title

Short descriptive title.

### Status

Accepted

### Date

YYYY-MM-DD

### Milestone

Milestone XX

### Context

Describe the business or technical problem.

### Decision

Describe the chosen solution.

### Alternatives Considered

Alternative A

Alternative B

Alternative C

### Consequences

Advantages

Disadvantages

Technical impact

### Affected Components

Models

Services

Controllers

Database

API

UI

Tests

### Related Documentation

DATABASE_SCHEMA.md

SERVICES.md

WORKFLOWS.md

API.md

ROADMAP.md

### Related Commit

git commit message

### Notes

Optional notes.
```

---

## ADR-001

### Title

TOTAL Means Collected Payments

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

The clinic calculates doctor income from collected payments, not from quoted treatment prices. A patient may pay partially or across multiple methods.

### Decision

`paid_total_aed` and `payments.amount_aed` represent money collected from the patient (DHS + USD + VISA), not the treatment invoice value (`total_cost`).

---

## ADR-002

### Title

JOB Means Lab Cost

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Clinic staff use "JOB" colloquially to mean the lab bill for a day's work, not an external work order ID.

### Decision

The term JOB in business language maps to `lab_jobs.total_cost_aed`, not an external work order ID.

---

## ADR-003

### Title

Database Driven Business Rules

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Doctors, rates, and lab assignments change over time. Hardcoded doctor names in services would require code deployment for every business change.

### Decision

All doctor commission logic reads from `doctors.commission_type`, `doctors.commission_percentage`, and `doctor_fixed_fees`. No `if ($doctor->name === 'Dr Jack')` anywhere in services.

---

## ADR-004

### Title

Lab Price Fallback Chain

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Dr Riyad has different lab prices and a different default lab. Other doctors share default prices.

### Decision

Lab prices resolve in order: (1) doctor-specific override, (2) default price where `doctor_id IS NULL`. Both scoped to the resolved lab.

---

## ADR-005

### Title

bcmath for Money Calculations

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Floating-point arithmetic causes rounding errors in financial systems.

### Decision

Use PHP `bcmath` via `MoneyCalculator` for all arithmetic. Never use float. Database columns are `decimal(12,2)`.

---

## ADR-006

### Title

Isolated Excel Parser

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

V2 will replace Excel upload with manual web form entry. The import orchestrator should stay stable while the input layer changes.

### Decision

Excel reading is isolated in `ExcelDailyReportParser`, separate from `DailyReportImportService`.

---

## ADR-007

### Title

Rule-Based Treatment Parser

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Accounting calculations must be deterministic and testable.

### Decision

`TreatmentParserService` uses regex pattern matching against known treatment codes from the database. No AI or ML.

---

## ADR-008

### Title

Shared Accounting Pipeline

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

V2 manual entry must not duplicate business logic.

### Decision

V2 manual entry will create the same records (`daily_work_rows`, `payments`, `work_items`, `lab_jobs`) and call the same calculation services. The only difference is the input source (Excel parser vs. web form).

---

## ADR-009

### Title

Approved Reports Are Read-Only

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Financial data integrity requires finalized accounting periods to remain immutable.

### Decision

Reports with `status = approved` cannot be re-imported for the same date or reprocessed.

---

## ADR-010

### Title

Soft Delete Strategy

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Audit trail and regulatory compliance require immutable history in accounting systems.

### Decision

Financial records (`lab_jobs`, reports) are never physically deleted. Use status fields (`cancelled`, `failed`) instead.

---

## ADR-011

### Title

Sanctum Authentication

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

V1 is API-first. Role enforcement must remain lightweight for the MVP.

### Decision

Use Laravel Sanctum token-based auth with role middleware, not session-based web auth. Roles enforced via `EnsureUserHasRole` middleware.

---

## ADR-012

### Title

Private File Storage

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Uploaded files may contain patient names and financial data.

### Decision

Excel uploads stored on the `local` disk under `daily-reports/`, outside the public directory.

---

## ADR-013

### Title

Sanitized Raw Import Data

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Import debugging and audit requirements must not retain plain-text patient names.

### Decision

Every imported row stores a **sanitized** parsed row in `daily_work_rows.raw_data_json` (patient identifier keys removed, PII cells redacted).

---

## ADR-014

### Title

Financial Rounding Rules

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Standard financial rounding prevents off-by-one-cent errors in doctor payouts.

### Decision

`MoneyCalculator::percentage()` rounds half-up to 2 decimal places (e.g. 12456.9375 → 12456.94). bcmath truncates by default; explicit rounding is required.

---

## ADR-015

### Title

Explicit Null Checks

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

The team prioritizes readability for developers who may not be familiar with modern PHP syntax.

### Decision

Prefer explicit `if ($value === null)` and `if ($object !== null)` over PHP shorthand operators `??`, `??=`, and `?->` in application code. Array defaults from parsed Excel rows use a dedicated `getParsedRowValue()` helper instead of inline `??`.

---

## ADR-016

### Title

Database Driven Export Profiles

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

New or inactive doctors should be configurable without code deploy. JOB calculation must remain one pipeline.

### Decision

Server Income Excel layout (sheet name, column letters, layout type) is stored in `doctor_income_export_profiles`, loaded by `DoctorIncomeExportProfileService`. No hardcoded doctor profile arrays in export code.

---

## ADR-017

### Title

Patient Privacy

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Accounting traceability is required without storing reversible patient identifiers. GDPR-aligned minimization applies to this non-clinical system.

### Decision

Do not persist `patient_name`, `mrn`, or `file_number`. During import, read them in memory only, store `patient_reference_hash` (HMAC-SHA256), sanitize `raw_data_json`, and never return patient identifiers in API responses. Use dedicated `ACCOUNTING_PATIENT_REFERENCE_HMAC_KEY`, not `APP_KEY`.

---

## ADR-018

### Title

Work Items for All Valid Treatments

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

CF, RCT, REPAIR, and REMOV (among others) affect treatment counts and doctor income context even when they do not all follow the same JOB rules.

### Decision

Every valid parsed treatment code creates a `work_item`. `lab_jobs` are created only when `treatments.has_lab_cost = true`. REMOV has lab cost (100 AED) and creates both work_item and lab_job.

---

## ADR-019

### Title

Import Validation Warnings

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Invalid treatments must not be silently ignored. Staff need a review queue before approving a month.

### Decision

`TreatmentImportValidationService` emits explicit warnings (`invalid_format`, `missing_quantity`, `unknown_treatment_code`, `lab_price_not_found`). Reports with warnings get status `needs_review` instead of `calculated`. Expose summary via `GET /api/daily-reports/{id}/validation-summary`.

---

## ADR-020

### Title

Delete Uploaded Excel Files

### Status

Accepted

### Date

2026-06-19

### Milestone

V1 MVP

### Context

Uploaded files contain patient names; retention should be minimized once data is extracted and sanitized in the database.

### Decision

Remove uploaded Excel from private storage after successful import unless `ACCOUNTING_DELETE_UPLOAD_AFTER_IMPORT=false`.

---

## ADR-021

### Title

Laboratory Soft Deactivate

### Status

Accepted

### Date

2026-06-25

### Milestone

Milestone 01

### Context

Accounting history must remain intact when a laboratory is retired from active use.

### Decision

Laboratories are configuration data managed via `LabManagementService`. Admins deactivate labs with `is_active = false` instead of deleting rows. Historical `lab_jobs` retain their `lab_id`. Inactive labs are excluded from active-lab queries used for new calculations only.

---


## ADR-022

### Title

Database-Driven Treatment Catalog

### Status

Accepted

### Date

2026-06-26

### Milestone

Milestone 02 — Treatments Administration

### Context

Milestone 02 requires administrators to manage treatments from the UI. Runtime code previously used hardcoded arrays in `LabCostTreatmentCatalog` and `NonLabIncomeTreatmentCatalog`.

### Decision

* `LabCostTreatmentCatalog::isLabCostCode()` and `codes()` read from the `treatments` table (`has_lab_cost`, `is_active`).
* `TreatmentManagementService` manages create/update/activate/deactivate with audit logs.
* Initial seed data remains in `TreatmentSeeder` only (not runtime business logic).

### Consequences

* New treatments and lab-cost flags are configurable without code deploy.
* Parser known codes and editor catalog use active treatments only.
* Historical `work_items` keep `treatment_id` references when treatments are deactivated.

---

## ADR-023

### Title

Admin-Managed Lab Price Catalog

### Status

Accepted

### Date

2026-06-26

### Milestone

Milestone 03 — Laboratory Price Administration

### Context

Lab unit prices were seeded in `LabPriceSeeder` and partially editable via a minimal admin page. Milestone 03 requires full UI/API administration without changing `LabPriceResolver` or accounting calculations.

### Decision

* `LabPriceManagementService` owns create, update, activate, deactivate, and duplicate.
* `LabPriceOverlapValidator` enforces one active price per lab + treatment + doctor scope + validity period.
* General prices (`doctor_id IS NULL`) and doctor overrides managed from `/lab-prices` and `/api/admin/lab-prices`.
* Soft deactivate only; historical `lab_jobs.lab_price_id` references preserved.

### Consequences

* Administrators can change lab costs without code deploy.
* `LabPriceResolver` and `LabJobCalculationService` unchanged.
* Duplicate creates inactive copy — admin adjusts validity before activation.
* Ready for future `clinic_id` scoping without hardcoding a single catalog.

---

## ADR-024

### Title

Admin-Managed Doctor Fixed Fee Catalog

### Status

Accepted

### Date

2026-06-26

### Milestone

Milestone 04 — Doctor Fixed Fee Administration

### Context

Fixed per-procedure fees for doctors with `commission_type = fixed` (e.g. Dr Wa: IMPL, BG, SINUS) were seeded in `DoctorFixedFeeSeeder` only. Milestone 04 requires full UI/API administration without changing `WaelFixedFeeCalculator`, `MonthlyIncomeCalculationService`, or other accounting engine services.

### Decision

* `DoctorFixedFeeManagementService` owns create, update, activate, deactivate, and duplicate.
* `DoctorFixedFeeOverlapValidator` enforces one active fee per doctor + treatment + validity period.
* `DoctorFixedFeeResolver` resolves active fees by work date (used by editor catalog).
* Soft deactivate only; `is_active` column added; unique `(doctor_id, treatment_id)` removed to allow scheduled fee changes.
* Admin UI at `/doctor-fixed-fees` and API at `/api/admin/doctor-fixed-fees`.

### Consequences

* Administrators can change fixed fees without code deploy.
* Accounting engine calculation logic unchanged; seeded single-row fees remain compatible.
* Duplicate creates inactive copy — admin adjusts validity before activation.
* Future `clinic_id` scoping can attach without hardcoding a single catalog.

---

## ADR-025

### Title

Configuration Layer

### Status

Accepted

### Date

2026-06-26

### Milestone

Milestone 05 — Configuration Dashboard

### Context

The project has evolved from a single-clinic accounting MVP into a configurable accounting platform.

The following modules are now fully managed through the administration interface:

* Doctors
* Laboratories
* Treatments
* Lab Prices
* Doctor Fixed Fees
* Users

These modules are no longer runtime configuration stored inside PHP code or Seeders.

Before introducing Multi-Clinic support, the architecture must explicitly define a Configuration Layer.

### Decision

Introduce a dedicated Configuration Layer.

The Configuration Layer is responsible for managing all business configuration required by the Accounting Engine.

It includes:

* Doctors
* Laboratories
* Treatments
* Lab Prices
* Doctor Fixed Fees
* Users

Business Logic must never read configuration directly from Seeder classes, PHP arrays or hardcoded constants.

Business Logic must access configuration only through:

* Services
* Resolver classes

Controllers must never implement configuration logic.

The Accounting Engine must remain completely independent from the Administration UI.

### Alternatives Considered

**Alternative A — Continue using individual CRUD modules**

Rejected because they do not express the architectural relationship.

**Alternative B — Store configuration inside PHP configuration files**

Rejected because runtime administration would become impossible.

### Consequences

**Advantages**

* Clear separation between Accounting Engine and Configuration.
* Easier Multi-Clinic implementation.
* Easier testing.
* Runtime configuration.
* Better maintainability.

**Disadvantages**

* More service classes.
* Slightly higher architectural complexity.

### Affected Components

* Doctors
* Labs
* Treatments
* Lab Prices
* Doctor Fixed Fees
* Users
* Configuration Dashboard
* Future Clinic Module

### Related Documentation

* PROJECT_OVERVIEW.md
* SERVICES.md
* WORKFLOWS.md
* DATABASE_SCHEMA.md
* DEVELOPMENT_GUIDE.md

---

## ADR-026

### Title

Clinic Entity as Tenant Root

### Status

Accepted

### Date

2026-06-26

### Milestone

Milestone 06 — Clinic Model (Milestone 07 — configuration `clinic_id` ownership)

### Context

The system started as a single-clinic accounting application for Clinic 111.

The project has now evolved into a configurable accounting platform.

The following modules are already configurable:

* Doctors
* Laboratories
* Treatments
* Lab Prices
* Doctor Fixed Fees
* Users
* Configuration Dashboard

The next architectural step is to prepare the system for multiple independent clinics.

Each clinic may have:

* Different doctors
* Different laboratories
* Different treatment catalog
* Different lab prices
* Different fixed fees
* Different users
* Different currency
* Different timezone
* Different accounting rules in the future

The application must support this without duplicating the codebase.

### Decision

Introduce `Clinic` as the root tenant entity.

A clinic represents one independent accounting tenant.

Every authenticated user belongs to exactly one clinic.

Clinic-scoped data is linked to `clinics.id` through `clinic_id`.

The following data is scoped by clinic:

* Users
* Doctors
* Laboratories
* Treatments
* Lab Prices
* Doctor Fixed Fees
* Daily Reports
* Daily Work Rows
* Payments
* Work Items
* Lab Jobs
* Audit Logs

The initial implementation must not introduce global query scopes.

Tenant isolation must be explicit.

Clinic context will be resolved through `CurrentClinicResolver` (ADR-027).

### Registration Decision

The future registration flow will be:

1. User submits registration form.
2. Application creates a new clinic.
3. Application creates the first admin/owner user and assigns it to the clinic.
4. Application seeds or initializes default configuration for that clinic.
5. User is redirected to the Configuration Dashboard.

The clinic and first user must be created inside one database transaction.

A user must not remain permanently without a clinic.

### No Global Scope Decision

The project will not use Laravel Global Scopes for clinic isolation.

Reason:

* Global scopes hide query behavior.
* They can break admin/reporting queries.
* They make debugging harder.
* They may accidentally affect imports, exports, audits, and background jobs.

Instead, clinic isolation will be implemented explicitly using:

* `CurrentClinicResolver`
* route middleware
* service-layer query scoping
* authorization checks
* tests verifying no cross-clinic leakage

### Alternatives Considered

**Alternative 1 — Separate Database per Clinic**

Rejected.

Reason:

* More operational complexity
* Harder backups
* Harder reporting
* Too early for current stage

**Alternative 2 — Laravel Global Scopes**

Rejected.

Reason:

* Hidden query behavior
* Higher debugging risk
* Possible accidental filtering in admin/reporting contexts

**Alternative 3 — Single Shared Database with Explicit clinic_id**

Accepted.

Reason:

* Simple operational model
* Easier SaaS evolution
* Clear tenant boundaries
* Testable isolation
* Fits the current Laravel architecture

### Consequences

**Advantages**

* The system can evolve toward Multi-Clinic SaaS.
* Each clinic can own independent configuration.
* Future query isolation becomes explicit and testable.
* The accounting engine can remain shared.

**Disadvantages**

* More explicit scoping is required in services.
* Developers must consistently pass or resolve clinic context.
* More tests are required to prevent cross-clinic data leakage.

### Affected Components

* Clinic Model
* User Model
* Doctor Model
* Lab Model
* Treatment Model
* LabPrice Model
* DoctorFixedFee Model
* DailyReport Model
* AuditLog Model
* ConfigurationDashboardService
* ClinicManagementService
* Admin Controllers
* Import Services
* Accounting Resolvers

### Related Documentation

* PROJECT_OVERVIEW.md
* DATABASE_SCHEMA.md
* SERVICES.md
* WORKFLOWS.md
* API.md
* DEVELOPMENT_GUIDE.md
* MULTI_CLINIC_ARCHITECTURE.md
* ARCHITECTURE_PRINCIPLES.md

### Implementation (Milestone 06)

Implemented 2026-06-26:

* `Clinic` model and `clinics` table with `code`, `name`, `currency`, `timezone`, `is_active`
* `ClinicManagementService` for admin CRUD
* Web `/clinics` and API `/api/admin/clinics`
* `ClinicSeeder` seeds `CLINIC_111`
* No accounting, import, or login integration in Milestone 06

### Implementation (Milestone 07)

Implemented 2026-06-26:

* `clinic_id` added to configuration tables: `users`, `doctors`, `labs`, `treatments`, `lab_prices`, `doctor_fixed_fees`
* Existing rows backfilled to `CLINIC_111`
* Models updated with `BelongsToClinic` concern
* Transitional default on create until ADR-027 removed the hardcoded fallback
* No global scopes, no query isolation, no accounting changes in Milestone 07

### Notes

Clinic 111 remains the first tenant.

Multi-Clinic must be implemented gradually.

No milestone may introduce partial tenant isolation without tests.

Public clinic registration is enabled via `/register-clinic` and `POST /api/register-clinic` (Milestone 11, ADR-030).

---

## ADR-027

### Title

Current Clinic Resolver

### Status

Accepted

### Date

2026-06-26

### Milestone

Milestone 08 — Current Clinic Resolver

### Context

After Milestone 07, every configuration record belongs to a clinic through `clinic_id`.

However, new records were still assigned to `CLINIC_111` through a temporary default. The application had ownership information but no runtime clinic context.

The system now requires a single source that determines the active clinic for each authenticated request.

### Decision

Introduce a `CurrentClinicResolver`.

The resolver determines the current clinic from the authenticated user's `clinic_id`.

Rules:

* Every authenticated user belongs to exactly one clinic.
* Every request has exactly one current clinic.
* Services create new configuration records using the current clinic.
* No Global Scopes.
* No automatic query filtering yet.
* Controllers never resolve the clinic directly.
* Services receive the clinic from the resolver.

The transitional `CLINIC_111` default introduced in Milestone 07 must be removed.

### Alternatives Considered

**Global Scope**

Rejected.

Hidden query modifications make debugging difficult and increase maintenance complexity.

**Passing clinic_id through every controller**

Rejected.

Creates duplicated code and increases the chance of inconsistencies.

**Resolver Service**

Accepted.

Provides a single, explicit source of the current clinic while keeping business logic independent from authentication.

### Consequences

**Advantages**

* Single source of truth.
* Removes hardcoded Clinic 111 fallback.
* Services become tenant-aware.
* Ready for query isolation in later milestones.
* Easy to test.

**Disadvantages**

* Resolver becomes required for configuration creation.
* Future background jobs will also require clinic context.

**Technical Impact**

* Remove transitional default from `BelongsToClinic`.
* Add `CurrentClinicResolver`.
* Inject resolver into configuration services.
* New records receive `clinic_id` from the resolver.

### Affected Components

**Models**

* User

**Services**

* CurrentClinicResolver
* ClinicManagementService
* DoctorManagementService
* LabManagementService
* TreatmentManagementService
* LabPriceManagementService
* DoctorFixedFeeManagementService

**Controllers**

* none (remain thin)

**Database**

* unchanged

**API**

* unchanged

**UI**

* unchanged

**Tests**

* Resolver tests
* Create ownership tests
* Cross-clinic ownership tests

### Related Documentation

* PROJECT_OVERVIEW.md
* SERVICES.md
* WORKFLOWS.md
* DEVELOPMENT_GUIDE.md
* MULTI_CLINIC_ARCHITECTURE.md

### Notes

Query isolation is intentionally postponed to ADR-028.

---

## ADR-028

### Title

Explicit Query Isolation

### Status

Accepted

### Date

2026-06-26

### Milestone

Milestone 09 — Explicit Query Isolation

### Context

After Milestone 08, every authenticated request has a resolved current clinic through `CurrentClinicResolver`.

New configuration records already belong to the authenticated clinic.

However, read operations were still global.

For example:

* Doctors
* Labs
* Treatments
* Lab Prices
* Fixed Fees
* Configuration Dashboard

still returned data from every clinic.

The application now requires tenant isolation for all configuration queries.

### Decision

The application shall implement **explicit query isolation**.

Every configuration query must explicitly filter by:

```php
clinic_id = CurrentClinicResolver::resolveId()
```

Query isolation belongs inside the Service Layer.

Controllers must never build tenant-aware queries.

Every query that reads configuration data must use the current clinic.

Examples:

* DoctorManagementService
* LabManagementService
* TreatmentManagementService
* LabPriceManagementService
* DoctorFixedFeeManagementService
* ConfigurationDashboardService

Every create operation already uses the resolver.

Now every read operation must also use it.

Laravel Global Scopes are explicitly forbidden.

### Alternatives Considered

**Laravel Global Scope**

Rejected.

Advantages:

* Automatic filtering.

Disadvantages:

* Hidden behavior.
* Difficult debugging.
* Complicated administrative queries.
* Harder testing.

**Explicit Service Filtering**

Accepted.

Advantages:

* Every query is visible.
* Easy debugging.
* Easy testing.
* Clear architecture.
* Easier future maintenance.

### Consequences

**Advantages**

* No accidental cross-clinic data leakage.
* Every service becomes tenant-aware.
* Clear and predictable query behavior.
* Easy unit testing.
* Easy future extension.

**Disadvantages**

* Developers must remember to use the resolver in every configuration service.
* More explicit code.

**Technical Impact**

* `CurrentClinicResolver` becomes mandatory for all configuration read operations.
* Every configuration service becomes tenant-aware.
* Configuration Dashboard displays only the current clinic.
* Reference APIs return only records of the authenticated clinic.

### Affected Components

**Models**

* none

**Services**

* DoctorManagementService
* LabManagementService
* TreatmentManagementService
* LabPriceManagementService
* DoctorFixedFeeManagementService
* ConfigurationDashboardService
* ReferenceDataService

**Controllers**

* No architectural changes.

**Database**

* No schema changes.

**API**

* Reference APIs become clinic-aware.

**UI**

* Configuration pages display only the current clinic.

**Tests**

* Cross-clinic leakage tests become mandatory.

### Related Documentation

* PROJECT_OVERVIEW.md
* SERVICES.md
* WORKFLOWS.md
* DEVELOPMENT_GUIDE.md
* MULTI_CLINIC_ARCHITECTURE.md

### Implementation (Milestone 09)

Implemented 2026-06-26 on branch `feature/query-isolation`:

* `ScopesConfigurationQueries` trait — `forCurrentClinic()`, `assertSameClinic()`
* All configuration management services expose clinic-scoped `listQuery()` / list helpers
* `ReferenceDataService` for clinic-scoped reference API reads
* `ConfigurationDashboardService` uses resolver for counts, health, and filtered audit activity
* Overlap validators accept `clinicId` as first parameter
* Cross-clinic mutations return HTTP 404

---

## ADR-029

### Title

Accounting Ownership and Isolation

### Status

Accepted

### Date

2026-06-27

### Milestone

Milestone 10 — Accounting Ownership and Isolation

### Context

Milestones 06–09 established the multi-clinic foundation:

* Clinic entity introduced.
* Configuration data owns `clinic_id`.
* `CurrentClinicResolver` provides the active clinic.
* Configuration queries are isolated through explicit service-layer filtering.

However, the accounting engine still stored and processed accounting data without explicit tenant ownership.

To safely support multiple clinics, every accounting record must belong to exactly one clinic.

Accounting isolation must be completed before introducing the Registration Wizard (ADR-030).

### Decision

Every accounting entity shall explicitly own a `clinic_id`.

Accounting services must only create, read, update, and calculate records that belong to the current clinic.

The authenticated clinic resolved by `CurrentClinicResolver` is the only runtime source of tenant context.

No accounting service may infer, guess, or hardcode clinic ownership.

### Accounting Ownership Chain

```text
Clinic
    │
    ▼
DailyReport
    │
    ▼
DailyWorkRow
    │
    ├──────────────┐
    ▼              ▼
Payment       WorkItem
                    │
                    ▼
                 LabJob
```

The following tables must own `clinic_id`:

* daily_reports
* daily_work_rows
* payments
* work_items
* lab_jobs
* audit_logs (financial events only)

Future accounting tables must also contain `clinic_id`.

### Import Pipeline

Every import must follow this ownership flow:

```text
Resolve Current Clinic
        ↓
Create Daily Report
        ↓
Create Daily Work Rows
        ↓
Create Payments
        ↓
Create Work Items
        ↓
Create Lab Jobs
```

Every created record inherits the same `clinic_id`.

No step may overwrite the clinic context.

### Immutable Ownership

`clinic_id` is **immutable** after create.

Once assigned at insert time, no runtime code may change it — not admin UI, not services, not migrations after the initial backfill.

Only **new** records receive `clinic_id`.

### Parent Inheritance (No Resolver on Children)

Child entities must **never** resolve their own clinic from `CurrentClinicResolver`.

They inherit `clinic_id` from the parent entity:

| Child | Inherits from |
|---|---|
| `daily_work_rows` | `daily_reports.clinic_id` |
| `payments` | `daily_work_rows.clinic_id` |
| `work_items` | `daily_work_rows.clinic_id` |
| `lab_jobs` | `work_items.clinic_id` |

Only **root** accounting creates (e.g. `DailyReport`) use `CurrentClinicResolver`.

This prevents tenant mismatches when the resolver and parent disagree.

### Query Rules

Every accounting query must explicitly filter by:

```php
->where('clinic_id', $this->currentClinicResolver->resolveId())
```

**Never traverse parent relations** for tenant-scoped reads or writes.

Forbidden:

```php
$report->payments()
$report->dailyWorkRows()
$workRow->workItems()->delete()
```

Required:

```php
Payment::query()
    ->where('clinic_id', $clinicId)
    ->where('daily_work_row_id', $workRowId)
```

Use `App\Support\AccountingScopedQuery` helpers in services.

Filtering belongs inside the Service Layer.

Controllers must never build tenant-aware accounting queries.

### Calculation Rules

Accounting calculations must never mix data from different clinics.

Examples:

* Daily Income
* Monthly Income
* Doctor Income
* Clinic Income
* Lab Cost
* Payment Totals

Every calculation must operate only on records belonging to one clinic.

### No Global Scopes

Laravel Global Scopes remain forbidden.

Accounting isolation must always be explicit and visible inside the Service Layer.

Hidden tenant filtering is not allowed.

### Security Rules

The following must never be accepted from HTTP requests:

* clinic_id
* clinic_code

These values are assigned exclusively by `CurrentClinicResolver`.

Any request attempting to submit a clinic identifier must be ignored or rejected.

### Alternatives Considered

**Global Scopes**

Rejected.

Reason:

* Hidden behaviour
* Difficult debugging
* Harder testing
* Administrative queries become unpredictable

**Explicit Service Layer Isolation**

Accepted.

Reason:

* Predictable
* Readable
* Easy to debug
* Easy to test
* Consistent with ADR-028

**Resolver on Every Entity**

Rejected.

Child entities already have a trusted parent. Resolving the clinic multiple times introduces unnecessary complexity.

**Mutable Ownership**

Rejected.

Financial ownership must remain permanent. Changing ownership after creation would compromise accounting integrity.

### Consequences

**Advantages**

* Complete tenant ownership
* No accounting data leakage
* Safe multi-clinic accounting
* Predictable service behaviour
* Deterministic ownership
* Stable audit trail
* SaaS-ready architecture

**Disadvantages**

* Every accounting service must explicitly use `CurrentClinicResolver`
* More explicit queries throughout the accounting layer
* Additional helper services are required

### Affected Components

**Database**

* daily_reports
* daily_work_rows
* payments
* work_items
* lab_jobs
* audit_logs (financial)

**Models**

* DailyReport
* DailyWorkRow
* Payment
* WorkItem
* LabJob
* AuditLog

**Services**

* DailyReportImportService
* DailyReportQueryService
* AccountingScopedQuery
* PaymentCalculationService
* LabJobCalculationService
* MonthlyIncomeCalculationService
* DoctorIncomeCalculationService
* DailyReportEditorService
* Import Services
* Export Services
* AuditLogService

**Controllers**

* No architectural changes. Controllers remain thin.

**API**

* No endpoint changes. Only ownership and filtering behaviour changes.

**UI**

* No functional changes. Existing screens automatically display only accounting data belonging to the authenticated clinic.

**Tests**

* AccountingOwnershipTest
* CrossClinicAccountingIsolationTest
* Mandatory regression tests
* Mandatory import ownership tests

### Related Documentation

* PROJECT_OVERVIEW.md
* DATABASE_SCHEMA.md
* SERVICES.md
* WORKFLOWS.md
* DEVELOPMENT_GUIDE.md
* MULTI_CLINIC_ARCHITECTURE.md

### Implementation (Milestone 10)

Implemented 2026-06-27 on branch `feature/accounting-ownership`:

* Migration `2026_06_27_000003_attach_clinic_id_to_accounting_tables` — NOT NULL `clinic_id` on all accounting tables, backfilled to `CLINIC_111`
* `ScopesAccountingQueries` trait — `forCurrentClinic()`, `assertSameClinic()`, `currentClinicId()`
* `DailyReportQueryService` for clinic-scoped report lists and route-bound access checks
* Import pipeline assigns same `clinic_id` through report → work rows → payments → work items → lab jobs
* All accounting services inject `CurrentClinicResolver` and filter reads explicitly
* `AuditLogService` persists `clinic_id` on every log row
* Cross-clinic accounting access returns HTTP 404
* `CrossClinicAccountingIsolationTest` + `AccountingOwnershipTest` added
* `ImmutableClinicOwnership` trait enforces `clinic_id` immutability on accounting models
* `AccountingScopedQuery` — explicit `clinic_id` + parent-key queries (no `$report->payments()`)

### Notes

This ADR isolates **accounting data** after configuration isolation (ADR-028).

Clinic 111 remains the first tenant. During migration, all existing data was assigned to `CLINIC_111`.

* Registration Wizard (ADR-030) is implemented — see `/register-clinic`

---

## ADR-030

### Title

Clinic Onboarding Workflow

### Status

Accepted

### Date

2026-06-27

### Milestone

Milestone 11 — Clinic Registration / Onboarding Wizard

---

## Context

Milestones 06–10 completed the Multi-Clinic foundation.

The system now has:

* Clinic entity as tenant root
* clinic_id ownership on configuration data
* CurrentClinicResolver
* Explicit configuration query isolation
* clinic_id ownership on accounting data
* Explicit accounting isolation
* Immutable accounting ownership
* Cross-clinic isolation tests

The platform is now ready to allow new clinics to join without developer intervention.

Before this milestone, clinics could only be created manually by an admin or through seeders.

That is not acceptable for a SaaS-style platform.

---

## Decision

Introduce a transactional Clinic Onboarding Workflow.

The onboarding workflow is the only supported way to create production clinics.

The workflow creates:

* Clinic
* Owner/Admin user
* Minimal default clinic configuration

The workflow must run inside one database transaction.

If any step fails, all changes must roll back.

Partial clinics are forbidden.

---

## Onboarding Flow

```text
Submit Onboarding Form
        ↓
Validate Clinic Data
        ↓
Validate Owner Data
        ↓
Start Database Transaction
        ↓
Create Clinic
        ↓
Create Owner/Admin User
        ↓
Assign owner.clinic_id
        ↓
Create Minimal Default Configuration
        ↓
Activate Clinic
        ↓
Commit Transaction
        ↓
Login Owner
        ↓
Redirect to Configuration Dashboard
```

---

## Required Form Data

### Clinic Data

* Clinic name
* Clinic code
* Country
* Base currency
* Timezone

### Owner Data

* Owner name
* Owner email
* Owner password
* Password confirmation

---

## Default Configuration Rules

A new clinic must start with minimal configuration only.

Allowed during onboarding:

* Clinic record
* Owner/Admin user
* Optional default laboratory
* Optional default settings

Not allowed during onboarding:

* Accounting records
* Daily reports
* Payments
* Work items
* Lab jobs
* Imported Excel files
* Doctor income data

Business configuration such as doctors, treatments, lab prices, and fixed fees should be created after onboarding through the Configuration Dashboard.

Clinic 111 must not be copied as a template.

---

## Ownership Rules

All created records must belong to the newly created clinic.

The client must never submit:

* clinic_id
* clinic_code for ownership override

Ownership is assigned internally.

---

## Security Rules

Onboarding must not expose existing clinic data.

A new clinic owner must only see their own clinic after login.

No accounting data may be created during onboarding.

No existing clinic may be modified during onboarding.

---

## Transaction Rules

The onboarding workflow must be atomic.

Allowed states:

```text
Everything succeeds
```

or

```text
Everything rolls back
```

Forbidden states:

```text
Clinic exists without owner
Owner exists without clinic
Clinic partially configured
```

---

## Alternatives Considered

### Manual Admin Setup

Rejected.

Requires developer or platform-admin intervention.

Not scalable.

---

### Seeder-Based Clinic Creation

Rejected.

Seeders are for development and default bootstrap data, not production tenant creation.

---

### Transactional Onboarding Service

Accepted.

Provides consistency, testability, and SaaS readiness.

---

## Consequences

### Advantages

* Clinics can onboard without developer intervention.
* Tenant creation becomes repeatable.
* No partial tenants.
* SaaS onboarding becomes possible.
* Future billing/subscription integration becomes easier.

### Disadvantages

* More validation required.
* Onboarding service must be carefully tested.
* Future templates require additional design.

---

## Affected Components

### Services

* ClinicOnboardingService
* ClinicManagementService
* UserManagementService

### Controllers

* ClinicRegistrationController
* OnboardingController or RegistrationController

### Requests

* StoreClinicRegistrationRequest

### UI

* Public or protected onboarding form
* Registration wizard
* Redirect to Configuration Dashboard

### Tests

* Onboarding success test
* Rollback test
* Duplicate clinic code test
* Duplicate owner email test
* Owner login test
* Tenant isolation after onboarding

---

## Related Documentation

* PROJECT_OVERVIEW.md
* DATABASE_SCHEMA.md
* SERVICES.md
* WORKFLOWS.md
* API.md
* DEVELOPMENT_GUIDE.md
* MULTI_CLINIC_ARCHITECTURE.md

---

## Success Criteria

Milestone 11 is complete only when:

* A new clinic can be created through the onboarding workflow.
* The first owner/admin user is created automatically.
* The owner belongs to the new clinic.
* The owner can log in immediately.
* The owner sees only their clinic data.
* No accounting records are created during onboarding.
* The workflow is fully transactional.
* Rollback is tested.
* Cross-clinic isolation remains intact.
* All tests pass.

---

### Implementation (Milestone 11)

Implemented 2026-06-27 on branch `feature/clinic-onboarding`:

* `ClinicOnboardingService` — transactional clinic + owner + default lab creation (ADR-030)
* `RegisterClinicRequest` — validates clinic and owner fields; rejects `clinic_id`, role, and accounting data
* Web `/register-clinic` (GET form, POST submit) — guest-only POST; logs in owner and redirects to `/configuration`
* API `POST /api/register-clinic` — returns Sanctum token, clinic, and user (201)
* Default configuration: one lab (`{CLINIC_CODE}_MAIN_LAB`) only — no doctors, treatments, prices, or accounting records
* `ClinicOnboardingTest` + `ClinicOnboardingServiceTest` (313 tests green)

### Notes

Depends on ADR-029 (Accounting Ownership and Isolation).

Tenant security hardening (ADR-032) should follow or run in parallel before public launch.

---


# ADR Index

| ADR     | Title                                  | Status   |
| ------- | -------------------------------------- | -------- |
| ADR-001 | TOTAL Means Collected Payments         | Accepted |
| ADR-002 | JOB Means Lab Cost                     | Accepted |
| ADR-003 | Database Driven Business Rules         | Accepted |
| ADR-004 | Lab Price Fallback Chain               | Accepted |
| ADR-005 | bcmath for Money Calculations          | Accepted |
| ADR-006 | Isolated Excel Parser                  | Accepted |
| ADR-007 | Rule-Based Treatment Parser            | Accepted |
| ADR-008 | Shared Accounting Pipeline             | Accepted |
| ADR-009 | Approved Reports Are Read-Only         | Accepted |
| ADR-010 | Soft Delete Strategy                   | Accepted |
| ADR-011 | Sanctum Authentication                 | Accepted |
| ADR-012 | Private File Storage                   | Accepted |
| ADR-013 | Sanitized Raw Import Data              | Accepted |
| ADR-014 | Financial Rounding Rules               | Accepted |
| ADR-015 | Explicit Null Checks                   | Accepted |
| ADR-016 | Database Driven Export Profiles        | Accepted |
| ADR-017 | Patient Privacy                        | Accepted |
| ADR-018 | Work Items for All Valid Treatments    | Accepted |
| ADR-019 | Import Validation Warnings             | Accepted |
| ADR-020 | Delete Uploaded Excel Files            | Accepted |
| ADR-021 | Laboratory Soft Deactivate             | Accepted |
| ADR-022 | Database-Driven Treatment Catalog      | Accepted |
| ADR-023 | Admin-Managed Lab Price Catalog        | Accepted |
| ADR-024 | Admin-Managed Doctor Fixed Fee Catalog | Accepted |
| ADR-025 | Configuration Layer                    | Accepted |
| ADR-026 | Clinic Entity as Tenant Root           | Accepted |
| ADR-027 | Current Clinic Resolver                | Accepted |
| ADR-028 | Explicit Query Isolation               | Accepted |
| ADR-029 | Accounting Ownership and Isolation     | Accepted |
| ADR-030 | Clinic Onboarding Workflow             | Accepted |

---

# Future ADR Roadmap

The following architectural topics are expected to receive future ADRs.

**ADR-031** — Clinic Business Configuration

**ADR-032** — Tenant Security

**ADR-033** — Multi-Currency Strategy

**ADR-034** — Subscription & Licensing

**ADR-035** — Public SaaS Platform

---

# Documentation Rules

Whenever an architectural decision affects one or more of the following:

* Database
* Accounting Engine
* API
* Authentication
* Authorization
* Services
* Security
* Multi-Clinic
* Import Pipeline
* Business Rules

A new ADR must be created.

Minor implementation details do not require an ADR.

Implementation notes for a milestone belong inside the ADR that introduced the architecture, under an **Implementation** subsection — not as a duplicate ADR.

Business requirements evolve. Configuration changes. Accounting data grows. Architecture should remain stable. Every ADR exists to protect that stability.

Do not modify accepted ADRs unless correcting factual mistakes. New architectural decisions must always be appended as new ADR entries.

---

# Long-Term Vision

The project evolves through the following stages.

**V1**

Single Clinic Accounting Engine

↓

**V2**

Configurable Accounting Platform

↓

**V3**

Multi-Clinic SaaS Platform

↓

**Future**

Enterprise Dental Accounting Platform
