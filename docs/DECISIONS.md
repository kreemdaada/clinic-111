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

Every new ADR must follow this structure and section order:

```markdown
## ADR-XXX

### Title

Short descriptive title.

### Status

Accepted | Proposed | Deprecated | Superseded

### Date

YYYY-MM-DD

### Milestone

Milestone XX

### Context

Describe the business or technical problem.

### Decision

Describe the chosen solution.

### Alternatives Considered

Alternative A — reason accepted or rejected.

### Consequences

Advantages and disadvantages.

### Affected Components

Models, services, controllers, database, API, UI, tests.

### Related Documentation

PROJECT_OVERVIEW.md, SERVICES.md, WORKFLOWS.md, API.md, etc.

### Implementation

(optional) Milestone delivery notes, files, tests.

### Notes

(optional) Dependencies, follow-up ADRs, clarifications.
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

### Context

Milestones 06–10 completed the multi-clinic foundation:

* Clinic entity as tenant root
* `clinic_id` ownership on configuration data
* `CurrentClinicResolver`
* Explicit configuration query isolation
* `clinic_id` ownership on accounting data
* Explicit accounting isolation
* Immutable accounting ownership
* Cross-clinic isolation tests

The platform is ready to allow new clinics to join without developer intervention.

Before this milestone, clinics could only be created manually by an admin or through seeders. That is not acceptable for a SaaS-style platform.

### Decision

Introduce a transactional **Clinic Onboarding Workflow** as the only supported way to create production clinics.

The workflow creates, inside one database transaction:

* Clinic
* Owner/admin user (`role = admin`, `clinic_id` assigned internally)
* Minimal default configuration (one default laboratory only)

If any step fails, all changes roll back. Partial clinics are forbidden.

**Onboarding flow:**

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
Create Default Laboratory ({CLINIC_CODE}_MAIN_LAB)
        ↓
Activate Clinic
        ↓
Commit Transaction
        ↓
Login Owner
        ↓
Redirect to Configuration Dashboard
```

**Required form data — clinic:** name, code, country, base currency, timezone.

**Required form data — owner:** name, email, password, password confirmation.

**Default configuration rules:**

Allowed during onboarding:

* Clinic record
* Owner/admin user
* One default laboratory

Not allowed during onboarding:

* Accounting records (daily reports, payments, work items, lab jobs)
* Doctors, treatments, lab prices, doctor fixed fees
* Imported Excel files or doctor income data

Business configuration is created **after** onboarding through the Configuration Dashboard (ADR-031).

Clinic 111 must not be copied as a template.

**Ownership rules:** All created records belong to the new clinic. The client must never submit `clinic_id` or ownership overrides.

**Transaction rules:** Allowed end states are full success or full rollback. Forbidden: clinic without owner, owner without clinic, partial configuration beyond the default lab.

### Alternatives Considered

**Manual admin setup** — Rejected. Requires platform-admin intervention; not scalable.

**Seeder-based clinic creation** — Rejected. Seeders are for development and bootstrap data, not production tenant creation.

**Transactional onboarding service** — Accepted. Provides consistency, testability, and SaaS readiness.

### Consequences

**Advantages**

* Clinics can onboard without developer intervention
* Repeatable tenant creation with no partial tenants
* Foundation for future billing and subscription integration

**Disadvantages**

* More validation and transactional testing required
* Future onboarding templates require additional design

### Affected Components

**Services:** `ClinicOnboardingService`, `ClinicManagementService`, `UserManagementService`, `AuditLogService`

**Controllers:** `ClinicOnboardingController` (web + API)

**Requests:** `RegisterClinicRequest`

**UI:** `/register-clinic` (GET form, POST submit)

**Tests:** `ClinicOnboardingTest`, `ClinicOnboardingServiceTest`

### Related Documentation

* PROJECT_OVERVIEW.md
* DATABASE_SCHEMA.md
* SERVICES.md
* WORKFLOWS.md
* API.md
* DEVELOPMENT_GUIDE.md
* MULTI_CLINIC_ARCHITECTURE.md

### Implementation (Milestone 11)

Implemented 2026-06-27 on branch `feature/clinic-onboarding`:

* `ClinicOnboardingService` — transactional clinic + owner + default lab creation
* `RegisterClinicRequest` — validates clinic and owner fields; rejects `clinic_id`, role, and accounting data
* Web `/register-clinic` — guest-only POST; logs in owner and redirects to `/configuration`
* API `POST /api/register-clinic` — returns Sanctum token, clinic, and user (201)
* Default configuration: one lab (`{CLINIC_CODE}_MAIN_LAB`) only
* Audit logs: `clinic_created`, `user_created`, `lab_created`, `clinic_registered`

### Notes

Depends on ADR-029 (Accounting Ownership and Isolation).

Post-onboarding business configuration is defined in ADR-031. Platform authentication controls are defined in ADR-032.

---

## ADR-031

### Title

Clinic Business Configuration

### Status

Accepted

### Date

2026-06-27

### Milestone

Milestone 12 — Guided Business Configuration

### Context

A newly registered clinic contains only infrastructure:

* Clinic
* Owner
* Default laboratory

The accounting engine cannot operate until the clinic defines its own business configuration. Each clinic must independently configure its accounting rules without copying Clinic 111.

### Decision

Every clinic owns its business configuration. Business configuration is never shared between clinics. The accounting engine remains identical for every tenant; only configuration differs.

**Configuration modules (per clinic):**

* Doctors
* Laboratories
* Treatments
* Lab Prices
* Doctor Fixed Fees (conditional)
* Currency and timezone (set at registration)

**Guided setup order:**

1. Doctors
2. Laboratories
3. Treatments
4. Lab Prices
5. Doctor Fixed Fees (when required)
6. Import first report

**Completion rules (dynamic, per clinic):**

| Module | Required | Complete when |
|---|---|---|
| Doctors | Yes | ≥ 1 active doctor |
| Laboratories | Yes | ≥ 1 active laboratory |
| Treatments | Yes | ≥ 1 active treatment |
| Lab Prices | Yes | ≥ 1 active lab price |
| Doctor Fixed Fees | Conditional | Required only when active no-commission doctors exist; each such doctor needs active fee rules |
| Import | — | Allowed when all required modules are complete |

Introduce `ConfigurationProgressService` to calculate progress, missing modules, current step, and `ready_for_import`.

Introduce `BusinessConfigurationService` as the facade for dashboard, import guard, and API status.

Block import (web and API) when required configuration is missing. Show a friendly message; never crash or allow partial accounting configuration.

Never auto-create doctors, treatments, or prices during onboarding.

### Alternatives Considered

**Copy Clinic 111 as a template** — Rejected. Violates tenant independence; each clinic defines its own rules.

**Manual developer setup after registration** — Rejected. Not self-service; does not scale.

**Static checklist without dynamic detection** — Rejected. Fixed-fee doctors require conditional completion logic.

### Consequences

**Advantages**

* Administrators are guided through setup with visible progress
* Import is blocked until minimum viable configuration exists
* Percentage-only clinics complete without fixed-fee configuration
* No accounting logic changes

**Disadvantages**

* Additional setup steps before the first import
* Progress UI must stay aligned with completion rules

### Affected Components

**Services:** `ConfigurationProgressService`, `BusinessConfigurationService`, `ConfigurationDashboardService`

**Controllers:** `ConfigurationDashboardController`, `ImportController`, `ConfigurationStatusController` (API)

**Requests:** `ImportDailyReportRequest` (readiness validation)

**UI:** Configuration Dashboard wizard; import page guard

**Tests:** `BusinessConfigurationTest`, `ConfigurationProgressServiceTest`, `BusinessConfigurationServiceTest`

### Related Documentation

* PROJECT_OVERVIEW.md
* SERVICES.md
* WORKFLOWS.md
* API.md
* DEVELOPMENT_GUIDE.md
* MULTI_CLINIC_ARCHITECTURE.md

### Implementation (Milestone 12)

Implemented 2026-06-27 on branch `feature/clinic-business-configuration`:

* `ConfigurationProgressService` — dynamic completion rules per clinic
* `BusinessConfigurationService` — import guard + status facade for UI/API
* Configuration Dashboard — Business Configuration wizard with progress percentage and next-step links
* Import page — friendly block when configuration is incomplete
* `ImportDailyReportRequest` — validates readiness after file validation
* API `GET /api/admin/configuration/status` — admin-only, non-breaking
* Tests: `BusinessConfigurationTest`, `ConfigurationProgressServiceTest`, `BusinessConfigurationServiceTest`

### Notes

Onboarding infrastructure (clinic, owner, default lab) remains in ADR-030 only. This ADR covers post-onboarding business configuration and import readiness.

---

## ADR-032

### Title

Platform Authentication Security

### Status

Accepted

### Date

2026-06-27

### Milestone

Security Hardening (pre–Milestone 13)

### Context

Public clinic registration (`/register-clinic`) and login endpoints are exposed before multi-tenant SaaS launch. The platform must mitigate brute-force attacks, registration abuse, credential stuffing, user enumeration, and weak passwords without changing accounting or business logic.

### Decision

Treat platform authentication security as first-class infrastructure.

**Login protection**

* Laravel `RateLimiter` via `LoginThrottleService`
* Key: `login|{email}|{ip}`
* Maximum 5 failed attempts per email + IP
* 5-minute lockout; successful login clears the counter
* Generic error: `The provided credentials are invalid.` (never distinguish wrong email vs wrong password vs deactivated account)

**Registration protection**

* Named rate limiter `register-clinic`: maximum 3 POST attempts per minute per IP
* Duplicate clinic codes and emails rejected with generic messages (no enumeration)

**Password policy**

* `Password::defaults()` in `AppServiceProvider`
* Minimum 12 characters, uppercase, lowercase, number, special character
* Applies to registration, admin user create, and password reset

**Session security**

* On login: regenerate session ID (`regenerate(true)`) and CSRF token
* On logout: invalidate session and regenerate token

**HTTP cookie settings**

* `HttpOnly` enabled
* `SameSite=lax` (configurable)
* `Secure` in production via `SESSION_SECURE_COOKIE` / `config/session.php`

**Security audit logging**

* Actions: `login_succeeded`, `login_failed`, `login_lockout`, `clinic_registered`
* Never log passwords, tokens, or session IDs

**Shared authentication service**

* `AuthenticationService` used by web and API auth controllers
* Configuration in `config/auth_security.php`

**Out of scope (later milestones):** CAPTCHA, email verification, two-factor authentication, OAuth, subscription, billing.

### Alternatives Considered

**Custom cache-based throttle** — Rejected. Use Laravel `RateLimiter` exclusively.

**Per-IP login limit only** — Rejected. Credential-based throttle (email + IP) reduces collateral lockout while stopping targeted attacks.

**Distinct error messages for wrong email vs wrong password** — Rejected. Enables user enumeration.

### Consequences

**Advantages**

* Reduced brute-force and registration abuse surface
* Consistent security behavior across web session and API token login
* Auditable authentication events

**Disadvantages**

* ~~Unknown-email login audit rows currently fall back to legacy clinic for `clinic_id` (see ADR-033)~~ — resolved in Milestone 13A (ADR-033)
* ~~Shared NAT may cause false lockouts with email + IP key alone (see ADR-033)~~ — mitigated in Milestone 13A via user-agent segment (ADR-033)

### Affected Components

**Services:** `LoginThrottleService`, `AuthenticationService`, `AuditLogService`

**Controllers:** `AuthController` (web + API), `ClinicOnboardingController`

**Configuration:** `config/auth_security.php`, `config/session.php`, `AppServiceProvider`

**Requests:** `LoginRequest`, `RegisterClinicRequest`

**Tests:** `AuthenticationSecurityTest`, `LoginThrottleServiceTest`, `PasswordPolicyTest`

### Related Documentation

* PROJECT_OVERVIEW.md
* SERVICES.md
* WORKFLOWS.md
* API.md
* DEVELOPMENT_GUIDE.md

### Implementation

Implemented 2026-06-27 on branch `feature/security-hardening`:

* `app/Services/Auth/LoginThrottleService.php`
* `app/Services/Auth/AuthenticationService.php`
* `app/Support/SecurePassword.php`
* `config/auth_security.php`
* Web + API auth controllers delegate to `AuthenticationService`
* `AppServiceProvider` configures `Password::defaults()` and `register-clinic` rate limiter
* Audit actions: `LoginSucceeded`, `LoginFailed`, `LoginLockout`, `ClinicRegistered`
* Tests: `tests/Feature/AuthenticationSecurityTest.php`, `tests/Unit/LoginThrottleServiceTest.php`, `tests/Unit/PasswordPolicyTest.php`

### Notes

Platform audit context, NAT-aware login throttling, CAPTCHA abstraction, email verification, and security headers are implemented in Milestone 13A (ADR-033). Full tenant authorization review remains Milestone 13B.

---

## ADR-033

### Title

Tenant Security

### Status

Accepted

### Date

2026-06-27

### Milestone

Milestone 13 — Tenant Security (13A platform security + 13B tenant authorization review)

### Context

Milestones 06–12 introduced the complete multi-tenant foundation:

* Clinic entity
* Configuration ownership
* Accounting ownership
* `CurrentClinicResolver`
* Explicit query isolation
* Accounting isolation
* Transactional clinic onboarding
* Business configuration wizard
* Authentication security

The platform now supports multiple independent clinics within the same application.

The next architectural objective is protecting tenant data against malicious users, configuration mistakes, privilege escalation, and future SaaS attacks.

Tenant isolation must remain secure even if developers accidentally write incorrect application code.

### Decision

Introduce a dedicated **Tenant Security Layer**.

Tenant Security is independent from authentication.

* **Authentication** answers: Who is the user?
* **Tenant Security** answers: Is the user allowed to access this tenant?

Every authenticated request must satisfy both.

**Security principles**

1. Every authenticated user belongs to exactly one clinic.
2. `clinic_id` is the root security boundary. No request may cross that boundary.
3. `clinic_id` is never accepted from HTTP requests. Ownership is resolved internally.
4. Services never trust client-supplied identifiers. Every resource access must verify ownership.
5. Every cross-clinic access attempt is treated as unauthorized. Return HTTP 404 instead of revealing resource existence.
6. Authorization belongs inside the Service Layer. Controllers remain thin.
7. Background jobs must execute inside an explicit clinic context. Jobs must never guess the tenant.
8. Every security-relevant action must generate an audit log (login, failed login, lockout, password reset, clinic onboarding, privilege changes, account activation/deactivation).

**Security areas**

| Area | Scope |
|---|---|
| Authentication | Login throttling, registration throttling, password policy, session regeneration, secure cookies (see ADR-032) |
| Authorization | RBAC, tenant ownership validation, service-layer authorization |
| Abuse protection | Rate limiting, CAPTCHA / Turnstile, email verification, spam prevention |
| Audit | Every security event traceable; unknown users use Platform Audit Context |
| Platform security | Future: CSP, HSTS, trusted proxies, reverse-proxy headers, HTTPS-only, secret rotation |

**Platform audit context**

Authentication events occurring before tenant resolution must never fall back to Clinic 111.

Examples: unknown email, invalid password, registration abuse, password reset request.

These events belong to the platform itself. Future implementation may introduce `platform` or `platform_id` instead of assigning them to any clinic.

**Login throttling**

| | Key |
|---|---|
| ADR-032 (original) | `login\|email\|ip` |
| ADR-033 / Milestone 13A (current) | `login\|email\|ip\|user-agent` |

Reason: users behind the same NAT should not accidentally lock each other out. User-agent is stored as a SHA-256 hash in the throttle key (never logged in audit JSON).

### Milestone 13A Implementation Notes

Implemented 2026-06-27 on branch `feature/platform-security`:

**Platform audit context**

* `audit_logs.clinic_id` is nullable for platform-scoped events
* Unknown authentication events (`login_failed`, `login_lockout` for unknown email, `registration_abuse`) use `clinic_id = null` and `new_values.audit_context = platform`
* No fallback to `CLINIC_111` for pre-tenant events
* `PlatformAuditContext` tags platform logs; `AuditLogService::logPlatform()` is the entry point

**Login throttle**

* `LoginThrottleService` key: `login|{email}|{ip}|{sha256(user-agent)}` (or `unknown` when absent)
* Config unchanged: 5 attempts, 5-minute decay (`config/auth_security.php`)

**Email verification**

* `User` implements `MustVerifyEmail`
* New clinic owners start unverified; seeded and admin-provisioned users have `email_verified_at` set
* Web routes use `verified` middleware; verification notice at `/email/verify`
* `ClinicOnboardingService` sends verification notification after registration
* Audit: `email_verification_sent`, `email_verified`

**CAPTCHA abstraction**

* Contract: `App\Contracts\Security\CaptchaVerifier`
* Service: `CaptchaVerificationService` (controllers/requests delegate here)
* Default driver: `FakeCaptchaVerifier` (`config/auth_security.php` → `captcha.enabled`, `captcha.driver`, `captcha.fake_token`)
* `RegisterClinicRequest` validates CAPTCHA when enabled

**Security headers**

* `SecurityHeadersMiddleware` + `config/security.php`
* HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, basic CSP
* Disabled outside production by default (safe for local dev)

**Security audit (extended)**

* Added actions: `logout`, `email_verification_sent`, `email_verified`, `registration_abuse`
* Registration rate-limit 429 triggers platform `registration_abuse` audit
* Never log passwords, tokens, session IDs, or raw secrets

**Tests:** `tests/Feature/PlatformSecurityTest.php`; updates to `AuthenticationSecurityTest`, `ClinicOnboardingTest`, `LoginThrottleServiceTest`

### Milestone 13B Implementation Notes

Implemented 2026-06-27 on branch `feature/tenant-authorization-review`:

**Tenant ownership rule**

* Every clinic-owned resource access verifies `resource.clinic_id === CurrentClinicResolver::resolveId()`
* Cross-clinic access returns HTTP **404** (never 403) via `assertSameClinic()` / `TenantResourceGuard`

**New components**

* `TenantResourceGuard` — `assertAccessible(Model)` and `findAccessibleOrAbort(modelClass, id)` for route-bound resources
* `BelongsToCurrentClinic` validation rule — clinic-scoped foreign keys in form requests
* User admin routes use `{managedUser}` int parameter + `TenantResourceGuard` (avoids Laravel `{user}` / auth binding conflict)

**Gaps closed**

* `DailyReportEditorController::doctorTreatments()` — tenant guard on route-bound `Doctor`
* Daily report editor `store` / `rows` / `preview` — doctor resolved via `TenantResourceGuard`, not unscoped `exists:doctors,id`
* `DoctorManagementService`, `LabPriceManagementService`, `DoctorFixedFeeManagementService` — related FK ownership validated on create/update
* Form requests — clinic-scoped FK rules for doctors, lab prices, fixed fees, daily work rows
* `ConfigurationDashboardService::recentActivity()` — explicit `clinic_id` filter (platform events excluded)
* `ReportLockController` — explicit `assertAccessible()` before approve/unlock

**Review outcome**

* Configuration, accounting, import, export, audit views, user admin, and clinic admin paths reviewed
* Existing service-layer `assertSameClinic()` / `assertAccessible()` patterns confirmed; gaps above patched
* Clinic admins see only their own clinic in clinic admin (list scoped to current clinic)

**Background jobs (documented rule)**

* No queued tenant jobs exist yet
* Future jobs processing tenant data must receive explicit `clinic_id`; must not rely on `CurrentClinicResolver` without authenticated context

**Tests:** `tests/Feature/CrossClinicAuthorizationTest.php` (22 tests); existing `CrossClinicIsolationTest`, `CrossClinicAccountingIsolationTest` unchanged and passing

### Alternatives Considered

**Global middleware only** — Rejected. Security must remain enforceable inside services.

**Controller authorization** — Rejected. Business logic becomes duplicated across controllers.

**Dedicated tenant security layer** — Accepted. Provides centralized authorization, easy testing, and future SaaS readiness.

### Consequences

**Advantages**

* Strong tenant isolation
* Better SaaS security
* Easier auditing and compliance
* Predictable authorization

**Disadvantages**

* More authorization checks
* More integration tests
* Slightly more service complexity

### Affected Components

**Models:** `User`, `Clinic`

**Services:** `CurrentClinicResolver`, `AuthenticationService`, authorization services, `AuditLogService`

**Controllers:** All authenticated controllers

**Database:** `audit_logs`

**API:** All authenticated endpoints

**Tests:** Cross-tenant security tests, authorization tests, abuse tests

### Related Documentation

* PROJECT_OVERVIEW.md
* DEVELOPMENT_GUIDE.md
* MULTI_CLINIC_ARCHITECTURE.md
* SERVICES.md
* API.md
* WORKFLOWS.md

### Notes

**Success criteria — Milestone 13 is complete only when:**

* Cross-tenant access is impossible
* Platform audit context replaces Clinic 111 fallback
* Login throttling uses email + IP + user-agent
* CAPTCHA can be enabled
* Email verification is supported
* All security events are auditable
* All security tests pass

Builds on ADR-028 (Explicit Query Isolation), ADR-029 (Accounting Ownership), and ADR-032 (Platform Authentication Security).

---

## ADR-034

### Title

Multi-Currency Strategy

### Status

Accepted

### Date

2026-06-28

### Milestone

Milestone 14 — Multi-Currency Foundation

### Context

The platform has evolved into a secure multi-tenant SaaS accounting system.

Every clinic owns its own accounting configuration.

Future clinics may operate in different countries using different currencies.

Examples:

* AED
* EUR
* USD
* SAR
* GBP

The accounting engine must support multiple currencies without duplicating business logic.

Financial correctness has priority over convenience.

### Decision

Each clinic owns exactly one **Base Currency**.

All accounting calculations inside a clinic are performed exclusively in that base currency.

The accounting engine never mixes currencies internally.

Currency conversion is a presentation concern unless explicitly required by business rules.

### Base Currency

Every clinic stores:

* `currency_code`
* `currency_symbol`
* `currency_precision`

Examples:

| Clinic | Base currency |
| --- | --- |
| Clinic 111 | AED |
| Clinic Germany | EUR |
| Clinic USA | USD |

### Money Object

Every monetary value shall conceptually consist of:

* Amount
* Currency

Example:

```text
1250.00 AED
890.00 EUR
150.00 USD
```

Currency is part of the value.

Money without currency is invalid.

### Accounting Rules

Calculations never convert currencies.

Examples:

* Doctor commission
* Lab costs
* Income
* Expenses
* Monthly reports

All remain inside the clinic base currency.

### Currency Conversion

Currency conversion is isolated.

Possible future uses:

* Management dashboards
* Global SaaS reporting
* Cross-country analytics
* Exchange rate history

It is never part of the accounting engine itself.

### Exchange Rates

Future exchange rates are versioned.

Each rate contains:

* `from_currency`
* `to_currency`
* `rate`
* `valid_from`
* `provider`

Historical reports always use historical rates.

Rates are immutable.

### Precision

Never use floating point arithmetic.

Continue using `MoneyCalculator` with BCMath.

Currency precision follows ISO 4217.

Examples:

* JPY → 0 decimals
* EUR → 2 decimals
* KWD → 3 decimals

### Alternatives Considered

**Convert everything to USD** — Rejected. Introduces rounding errors and breaks local accounting.

**Per-transaction currency conversion** — Rejected. Unnecessary complexity and financial inconsistencies.

**Base currency per clinic** — Accepted. Simple, deterministic, and accounting remains stable.

### Consequences

**Advantages**

* Stable accounting engine
* Country-independent platform
* Future exchange-rate support
* Easy SaaS reporting

**Disadvantages**

* Exchange-rate subsystem required later
* Global financial reports require conversion

### Affected Components

**Database:** `clinics`, future `exchange_rates`

**Services:** `MoneyCalculator`, future `CurrencyConversionService`, future `ExchangeRateService`

**API:** Configuration, reporting

**UI:** Currency formatting

**Tests:** Money precision, currency validation, historical exchange-rate tests

### Related Documentation

* PROJECT_OVERVIEW.md
* DATABASE_SCHEMA.md
* SERVICES.md
* WORKFLOWS.md
* DEVELOPMENT_GUIDE.md
* MULTI_CLINIC_ARCHITECTURE.md

### Notes

**Success criteria — Milestone 14 is complete only when:**

* Every clinic has exactly one base currency
* Every money value belongs to one currency
* No accounting calculation performs implicit currency conversion
* Money precision is preserved
* The architecture is ready for future exchange-rate support

### Milestone 14 Implementation Notes

Implemented 2026-06-28 on branch `feature/multi-currency-foundation`:

**Currency catalog**

* `config/currencies.php` — ISO code, symbol, precision, display format for AED, EUR, USD, SAR, GBP
* `CurrencyCatalog` — single registry; no hardcoded currency lists in controllers
* `ClinicRegistrationOptions::currencies()` delegates to catalog

**Money value object**

* `App\Domain\Currency\Money` — amount + currency; BCMath via `MoneyCalculator::roundToPrecision()`
* Cross-currency math rejected at the value-object layer

**Formatting**

* `CurrencyFormatter` — centralized display (`AED 1250.00`, `€890.00`, `$150.00`)
* Shared with authenticated views via `ClinicContextComposer`

**Clinic base currency**

* Existing `clinics.currency` column retained (ISO code)
* Symbol and precision resolved from catalog at runtime (`Clinic::baseCurrency()`, `currencyMetadata()`)
* `SupportedCurrency` validation rule on clinic onboarding, clinic admin, lab prices, doctor fixed fees

**Extension points (not implemented)**

* `ExchangeRateProvider` contract
* `CurrencyConversionService` contract
* No live rates, no conversion service binding, no scheduled jobs

**Accounting behaviour**

* No changes to calculation outputs — legacy Clinic 111 layout and foreign-cash conversion unchanged
* `ClinicCurrencySupport` documented as presentation/legacy import only; engine stays in clinic base currency

**API**

* `ClinicApiPresenter` adds `currency_name`, `currency_symbol`, `currency_precision` alongside existing `currency` field

**Tests:** `CurrencyCatalogTest`, `MoneyTest`, `CurrencyFormatterTest`, `MultiCurrencyFoundationTest`

---

## ADR-035

**Title:** PostgreSQL Production Readiness and SQLite Data Migration

**Status:** Accepted

**Date:** 2026-06-29

**Context:**

Clinic 111 customer data currently lives in `database/database.sqlite` during development. Production on Hetzner (or any VPS) should use PostgreSQL for concurrency, backups, and operational tooling — without changing business logic, accounting rules, or tenant isolation.

**Decision:**

* **Local dev and automated tests** remain on SQLite (`database/database.sqlite` / `database/testing.sqlite`).
* **Production** uses PostgreSQL via `DB_CONNECTION=pgsql`.
* **Schema** is shared — same Laravel migrations for both engines.
* **Data migration** uses `php artisan app:migrate-sqlite-to-pgsql`:
  * Read-only access to source SQLite — never delete or overwrite the source file
  * Import preserves primary keys where possible
  * Ordered import respecting foreign keys
  * `--dry-run` for row-count preview
  * Post-import validation (row counts + payment/lab aggregates)
* **No business logic changes** in this milestone.

**Alternatives rejected:**

* Separate database per clinic — already rejected in ADR-026
* MySQL instead of PostgreSQL — PostgreSQL chosen for production readiness milestone scope
* Manual CSV export — error-prone; loses FK integrity

**Implementation:**

* `SqliteToPostgresMigrationService`, `PostgresMigrationValidationService`, `SqliteToPostgresTableRegistry`
* Command: `app:migrate-sqlite-to-pgsql`
* `.env.example` documents both SQLite and PostgreSQL
* Migrations reviewed for PostgreSQL compatibility; `->change()` migrations may require `doctrine/dbal` on PostgreSQL

**Tests:** `SqliteToPostgresTableRegistryTest`, `PostgresMigrationValidationServiceTest`, `MigrateSqliteToPgsqlCommandTest`

---

## ADR-036

**Title:** Clinic Financial Overview

**Status:** Accepted

**Date:** 2026-06-29

**Milestone:** Milestone 15 — Clinic Financial Overview

**Context:**

The public landing page shows a practice overview mockup with KPIs, trends, and top treatments. Authenticated users had no data-driven equivalent. Accounting data already exists in `payments`, `daily_work_rows`, `lab_jobs`, and `work_items`, with rules defined in ADR-001 (TOTAL = collected payments), ADR-002 (JOB = lab cost), and `MonthlyIncomeCalculationService` for per-doctor summaries.

**Decision:**

* Add a **Practice Overview** screen for the current clinic only (`CurrentClinicResolver`, explicit `clinic_id` filters — ADR-028/029).
* Compute KPIs **server-side** in `ClinicFinancialOverviewService`; no financial logic in Blade, JavaScript, or controllers.
* **Revenue** = `SUM(payments.amount_aed)` for rows whose `daily_work_rows.work_date` falls in the selected calendar month (clinic timezone). Matches `MonthlyIncomeCalculationService` (ADR-001).
* **Lab cost** = `SUM(lab_jobs.total_cost_aed)` for jobs with status `calculated` or `adjusted`, linked to work rows in the same month (ADR-002). Exclude `cancelled`.
* **Calculated result** = revenue − lab cost only. Label **“Calculated result”** — not profit or net income. UI explains that doctor commissions, overhead, and taxes are excluded.
* **Month-over-month comparison** for revenue, lab cost, and calculated result using `((current − previous) / previous) × 100`. If previous is zero or missing → display “No comparison available” (no division by zero).
* **Six-month revenue trend** ending at the selected month (inclusive), same revenue definition.
* **Top treatments by revenue (top 5):** allocate each row’s collected payments to work items **proportionally by quantity** on that row (only method available — payments are row-level, not treatment-level). Sort by revenue desc, then treatment name asc.
* **Period:** single selectable month (`YYYY-MM`), default = current month in clinic timezone.
* **Currency:** clinic base currency via `ClinicCurrencySupport` / `CurrencyFormatter` (ADR-034). No hard-coded symbols.
* **Data stand:** show count of non-failed `daily_reports` for the selected month and latest import filename when present — do not claim “month complete”.
* **No** analytics snapshots, materialized views, cache layer, or new chart libraries in v1. CSS bar chart + accessible text values.
* Exclude `daily_reports` with status `failed` from aggregates.
* Include only reports with status **`calculated`**, **`approved`**, or **`locked`** in all financial KPIs (variant B). Exclude **`needs_review`**, **`parsed`**, **`uploaded`**, and **`failed`**. When `needs_review` reports exist for the selected month, the UI shows an explicit warning — unreviewed data must not silently inflate figures.

**Alternatives Considered:**

1. Controller queries — rejected (logic duplication, untestable views).
2. Blade/JS calculation — rejected.
3. Persisted analytics snapshot table — rejected for v1 (premature).
4. Materialized views — rejected for v1.
5. Cache — rejected until performance proven.
6. New Chart.js/Recharts dependency — rejected; CSS bars sufficient.
7. Treatment revenue = quantity counts only — rejected (does not satisfy “Umsatz”).

**Consequences:**

* Positive: real KPIs for practice managers; reuses canonical accounting data; tenant-safe.
* Negative: treatment revenue uses proportional allocation (documented limitation); full P&L not provided.

**Affected Components:**

* `ClinicFinancialOverviewController`, `ClinicFinancialOverviewService`, DTOs under `app/DTOs/Analytics/`, `FinancialPeriod`, route `clinic.financial-overview`, Blade view, topbar navigation, tests, `docs/CLINIC_FINANCIAL_OVERVIEW.md`.

**Related Documentation:**

* `docs/BUSINESS_RULES.md`, `docs/SERVICES.md`, ADR-001, ADR-002, ADR-014, ADR-034, ADR-028, ADR-029.

**Implementation:**

Implemented 2026-06-29 on branch `feature/clinic-financial-overview`:

* Route `GET /practice-overview` → `clinic.financial-overview` (auth, verified, roles: admin/accountant/viewer).
* `ClinicFinancialOverviewController` (invokable) + `ClinicFinancialOverviewRequest` (`month` regex `YYYY-MM`).
* `ClinicFinancialOverviewService` with explicit `clinic_id` filters on `payments`, `lab_jobs`, `daily_work_rows`, `work_items`, `daily_reports`.
* DTOs: `ClinicFinancialOverviewData`, `FinancialKpiData`, `MonthlyRevenueData`, `TreatmentRevenueData`.
* Support: `FinancialPeriod` (clinic timezone month boundaries), `MonthOverMonthComparison`.
* Blade view `resources/views/clinic-financial-overview/index.blade.php` (CSS bar chart, KPI cards, top treatments, empty state).
* Topbar navigation link “Overview”; `ClinicContextComposer` registration.
* Documentation: `docs/CLINIC_FINANCIAL_OVERVIEW.md`.

Tests:

* `tests/Feature/ClinicFinancialOverviewTest.php` — access control, tenant isolation, KPI calculation, MoM comparison, top treatments, timezone default, empty/invalid month, navigation, report status filter (17 tests).
* Full suite: 471 tests passing.

**Pre-merge refinement:** Financial KPIs include only `calculated`, `approved`, and `locked` reports (variant B). `needs_review` data is excluded with an explicit UI warning. Top treatments labeled “allocated revenue”.

**Notes:**

This overview is operational reporting on imported DentalFinance data — not tax advice or full bookkeeping.

---

## ADR-037

**Title:** Production Deployment Architecture

**Status:** Proposed

**Date:** 2026-06-29

**Milestone:** Milestone 16 — Production Deployment Foundation

**Context:**

DentalFinance runs Laravel 13 with Blade, PostgreSQL in production (ADR-035), database-backed cache/session/queue, synchronous Excel imports, and `/up` health routing. Production target is `dentalfinance.eu` on a single IONOS VPS L+ (Ubuntu 24.04 LTS) without Plesk, Kubernetes, or managed PostgreSQL. The codebase had no Docker, Compose, CI, or deployment automation prior to this milestone.

**Decision:**

* **Domain:** `dentalfinance.eu` (canonical); `www` redirects to apex.
* **Host:** single IONOS VPS L+, Ubuntu 24.04 LTS, Docker Compose.
* **Application image:** multi-stage build, FrankenPHP 1.9 + PHP 8.3 (classic Laravel request cycle, not Octane worker mode).
* **Same image** for `app`, `worker`, and `scheduler` services.
* **PostgreSQL 16** in internal Docker network — no public DB port.
* **No Redis** in v1 — existing config uses `database` for cache, session, and queue.
* **HTTPS:** automatic certificates via Caddy (FrankenPHP); persistent Caddy volumes.
* **Registry:** GitHub Container Registry; image tags = full Git commit SHA (optional `latest`, never deploy-only-latest).
* **CI:** GitHub Actions — tests + Pint on PR/push; production deploy on `main` with concurrency lock.
* **Secrets:** application secrets only on server (`/opt/dentalfinance/app.env`); GitHub stores deploy SSH secrets only.
* **Deploy flow:** backup DB → `migrate --force` → rolling container update → `optimize` → internal Laravel healthcheck at `http://127.0.0.1:8080/up` (container-only listener) → application rollback on failure (previous image tag, with separate rollback success/failure reporting).
* **No automatic database rollback** after failed migrations.
* **SMTP:** Brevo relay (`smtp-relay.brevo.com:587`); `system@dentalfinance.eu` as From; credentials server-side only.
* **Logging:** Laravel to `stderr`; Docker log rotation.
* **No** demo seeders, SQLite production file, or `.env` baked into images.

**Alternatives Considered:**

1. IONOS Shared Hosting — rejected (no worker/scheduler/Docker control).
2. Plesk — rejected (extra dependency).
3. Kubernetes — rejected (overengineering for v1).
4. Separate app/DB servers — deferred until load/HA requires it.
5. Managed PostgreSQL — deferred (cost at launch).
6. Manual `git pull` deploy — rejected (non-reproducible rollback).
7. `latest` as sole deploy tag — rejected (non-traceable versions).

**Consequences:**

* Positive: reproducible deploys, SHA-tagged images, controlled app rollback, low start cost.
* Negative: single VPS SPOF; operator runs backups/restore tests; no horizontal scaling in v1.

**Affected Components:**

* `Dockerfile`, `compose.production.yml`, `.dockerignore`, `deploy/*`, `.github/workflows/*`, `docs/PRODUCTION_DEPLOYMENT.md`.

**Related Documentation:**

* ADR-035, ADR-036, `docs/LEGAL_SETUP.md`, `.env.example`.

**Implementation:**

Revision 2026-06-26 on branch `feature/production-deployment` (status remains **Proposed** until GitHub Actions confirms a successful production Docker build):

* **Safe rsync:** CI/CD syncs `compose.production.yml` and `deploy/` separately; `--delete` only on `/opt/dentalfinance/deploy/`. Protected server paths: `app.env`, `.deploy-state`, `.deploy.lock`, `backups/`.
* **GHCR tags:** lowercase image base derived in Bash (`tr '[:upper:]' '[:lower:]'`) and emitted via `$GITHUB_OUTPUT` for SHA and optional `latest` tags — no `${{ env.IMAGE_NAME,, }}` syntax.
* **Internal healthcheck:** Caddy listens on `127.0.0.1:8080` (not published); app/worker health uses `http://127.0.0.1:8080/up` so Laravel boots — not merely HTTPS redirect.
* **Compose command:** all scripts use `docker compose --env-file /opt/dentalfinance/app.env -f /opt/dentalfinance/compose.production.yml` via `deploy/scripts/lib/deploy-common.sh` — never `source app.env`.
* **DB credentials:** operator sets `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` only; PostgreSQL container maps `POSTGRES_*` from those values.
* **Manual backup/restore:** scripts resolve `APP_IMAGE` from env or `.deploy-state`; restore creates safety backup, maintenance mode, stops worker/scheduler, terminates DB connections, uses `pg_restore --clean --if-exists --no-owner --no-privileges --exit-on-error --single-transaction`.
* **Rollback:** separate messages for rollback success vs rollback failure; no automatic database rollback.
* **Vite:** `resources/views/welcome.blade.php` contains `@vite` but is **not routed** in production (`/` → `LandingController`); no Node build in Docker image required for v1.
* **Dockerfile:** Composer and runtime both PHP 8.3; `org.opencontainers.image.source` OCI label.

**Implementation notes (verification):**

* Laravel tests: **passed** (local run).
* Compose static validation: **passed** (`APP_IMAGE`, `DB_PASSWORD`, `APP_ENV_FILE=deploy/app.env.example`).
* Pint: **passed**.
* Shell checks: `bash -n` and `shellcheck` on deploy scripts — **passed** (local run).
* Local Docker build: **not available** (no Docker daemon in local environment).
* Docker build verification: **pending GitHub Actions** (`ci.yml` / `deploy-production.yml` docker-build job).

Remaining risks: single VPS SPOF; Docker build must be confirmed in GitHub Actions before ADR acceptance; external off-site backups are operator responsibility.

**Notes:**

External off-site backup copy is an operational requirement — local VPS backups alone are insufficient.

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
| ADR-031 | Clinic Business Configuration          | Accepted |
| ADR-032 | Platform Authentication Security       | Accepted |
| ADR-033 | Tenant Security                        | Accepted |
| ADR-034 | Multi-Currency Strategy                | Accepted |
| ADR-035 | PostgreSQL Production Readiness        | Accepted |
| ADR-036 | Clinic Financial Overview              | Accepted |
| ADR-037 | Production Deployment Architecture   | Accepted |

---

# Future ADR Roadmap

The following architectural topics are expected to receive future ADRs.

**ADR-038** — Subscription & Licensing (Proposed)

**ADR-039** — Public SaaS Platform (Proposed)

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

