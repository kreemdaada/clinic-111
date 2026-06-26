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

Accepted

The decision is active.

Deprecated

The decision is no longer recommended.

Superseded

A newer ADR replaces this one.

Proposed

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

## ADR-022

### Title

Database-Driven Treatment Catalog

### Status

Accepted

### Context

Milestone 02 requires administrators to manage treatments from the UI. Runtime code previously used hardcoded arrays in `LabCostTreatmentCatalog` and `NonLabIncomeTreatmentCatalog`.

### Decision

- `LabCostTreatmentCatalog::isLabCostCode()` and `codes()` read from the `treatments` table (`has_lab_cost`, `is_active`).
- `TreatmentManagementService` manages create/update/activate/deactivate with audit logs.
- Initial seed data remains in `TreatmentSeeder` only (not runtime business logic).

### Consequences

- New treatments and lab-cost flags are configurable without code deploy.
- Parser known codes and editor catalog use active treatments only.
- Historical `work_items` keep `treatment_id` references when treatments are deactivated.

### Related Milestone

Milestone 02 — Treatments Administration

### Date

2026-06-26

---

## ADR-023

### Title

Admin-Managed Lab Price Catalog

### Status

Accepted

### Context

Lab unit prices were seeded in `LabPriceSeeder` and partially editable via a minimal admin page. Milestone 03 requires full UI/API administration without changing `LabPriceResolver` or accounting calculations.

### Decision

- `LabPriceManagementService` owns create, update, activate, deactivate, and duplicate
- `LabPriceOverlapValidator` enforces one active price per lab + treatment + doctor scope + validity period
- General prices (`doctor_id IS NULL`) and doctor overrides managed from `/lab-prices` and `/api/admin/lab-prices`
- Soft deactivate only; historical `lab_jobs.lab_price_id` references preserved

### Consequences

- Administrators can change lab costs without code deploy
- `LabPriceResolver` and `LabJobCalculationService` unchanged
- Duplicate creates inactive copy — admin adjusts validity before activation
- Ready for future `clinic_id` scoping without hardcoding a single catalog

### Related Milestone

Milestone 03 — Laboratory Price Administration

### Date

2026-06-26

---

## ADR-024

### Title

Admin-Managed Doctor Fixed Fee Catalog

### Status

Accepted

### Context

Fixed per-procedure fees for doctors with `commission_type = fixed` (e.g. Dr Wa: IMPL, BG, SINUS) were seeded in `DoctorFixedFeeSeeder` only. Milestone 04 requires full UI/API administration without changing `WaelFixedFeeCalculator`, `MonthlyIncomeCalculationService`, or other accounting engine services.

### Decision

- `DoctorFixedFeeManagementService` owns create, update, activate, deactivate, and duplicate
- `DoctorFixedFeeOverlapValidator` enforces one active fee per doctor + treatment + validity period
- `DoctorFixedFeeResolver` resolves active fees by work date (used by editor catalog)
- Soft deactivate only; `is_active` column added; unique `(doctor_id, treatment_id)` removed to allow scheduled fee changes
- Admin UI at `/doctor-fixed-fees` and API at `/api/admin/doctor-fixed-fees`

### Consequences

- Administrators can change fixed fees without code deploy
- Accounting engine calculation logic unchanged; seeded single-row fees remain compatible
- Duplicate creates inactive copy — admin adjusts validity before activation
- Future `clinic_id` scoping can attach without hardcoding a single catalog

### Related Milestone

Milestone 04 — Doctor Fixed Fee Administration

### Date

2026-06-26

---

## ADR-025

### Title

Configuration Layer

### Status

Accepted

### Context

The project has evolved from a single accounting MVP into a configurable accounting platform.

The following modules are now fully managed through the administration interface:

- Doctors
- Laboratories
- Treatments
- Lab Prices
- Doctor Fixed Fees
- Users

These modules are no longer runtime configuration stored inside PHP code or Seeders.

Before introducing Multi-Clinic support, the architecture must explicitly define a Configuration Layer.

### Decision

Introduce a dedicated Configuration Layer.

The Configuration Layer is responsible for managing all business configuration required by the Accounting Engine.

It includes:

- Doctors
- Laboratories
- Treatments
- Lab Prices
- Doctor Fixed Fees
- Users

Business Logic must never read configuration directly from Seeder classes, PHP arrays or hardcoded constants.

Business Logic must access configuration only through:

- Services
- Resolver classes

Controllers must never implement configuration logic.

The Accounting Engine must remain completely independent from the Administration UI.

### Alternatives Considered

**Alternative A — Continue using individual CRUD modules**

Rejected because they do not express the architectural relationship.

**Alternative B — Store configuration inside PHP configuration files**

Rejected because runtime administration would become impossible.

### Consequences

**Advantages**

- Clear separation between Accounting Engine and Configuration.
- Easier Multi-Clinic implementation.
- Easier testing.
- Runtime configuration.
- Better maintainability.

**Disadvantages**

- More service classes.
- Slightly higher architectural complexity.

### Affected Components

- Doctors
- Labs
- Treatments
- Lab Prices
- Doctor Fixed Fees
- Users
- Future Configuration Dashboard
- Future Clinic Module

### Related Documentation

- PROJECT_OVERVIEW.md
- SERVICES.md
- WORKFLOWS.md
- DATABASE_SCHEMA.md
- DEVELOPMENT_GUIDE.md

### Related Milestone

Milestone 05 — Configuration Dashboard

### Date

2026-06-26

---

## ADR-026

### Title

Clinic Entity as Tenant Root

### Status

Accepted

### Date

2026-06-26

### Milestone

Milestone 06 — Clinic Model

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

---

### Decision

Introduce `Clinic` as the root tenant entity.

A clinic represents one independent accounting tenant.

Every authenticated user belongs to exactly one clinic.

Future clinic-scoped data will be linked to `clinics.id` through `clinic_id`.

The following data will eventually be scoped by clinic:

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

Clinic context will be resolved through a dedicated service in a later milestone:

`CurrentClinicResolver`

---

### Registration Decision

The future registration flow will be:

1. User submits registration form.
2. Application creates a new clinic.
3. Application creates the first admin/owner user and assigns it to the clinic.
4. Application seeds or initializes default configuration for that clinic.
5. User is redirected to the Configuration Dashboard.

The clinic and first user must be created inside one database transaction.

A user must not remain permanently without a clinic.

---

## ADR-027

### Title

Current Clinic Resolver

### Status

Accepted

### Date

2026-06-26

### Milestone

Milestone 08

### Context

After Milestone 07, every configuration record belongs to a clinic through `clinic_id`.

However, new records are still assigned to `CLINIC_111` through a temporary default. The application has ownership information but no runtime clinic context.

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

Advantages

* Single source of truth.
* Removes hardcoded Clinic 111 fallback.
* Services become tenant-aware.
* Ready for query isolation in later milestones.
* Easy to test.

Disadvantages

* Resolver becomes required for configuration creation.
* Future background jobs will also require clinic context.

Technical Impact

* Remove transitional default from `BelongsToClinic`.
* Add `CurrentClinicResolver`.
* Inject resolver into configuration services.
* New records receive `clinic_id` from the resolver.

### Affected Components

Models

* User

Services

* CurrentClinicResolver
* ClinicManagementService
* DoctorManagementService
* LabManagementService
* TreatmentManagementService
* LabPriceManagementService
* DoctorFixedFeeManagementService

Controllers

* none (remain thin)

Database

* unchanged

API

* unchanged

UI

* unchanged

Tests

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

Query isolation is intentionally postponed to the next milestone.
---

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

---

### Alternatives Considered

#### Alternative 1 — Separate Database per Clinic

Rejected.

Reason:

* More operational complexity
* Harder backups
* Harder reporting
* Too early for current stage

#### Alternative 2 — Laravel Global Scopes

Rejected.

Reason:

* Hidden query behavior
* Higher debugging risk
* Possible accidental filtering in admin/reporting contexts

#### Alternative 3 — Single Shared Database with Explicit clinic_id

Accepted.

Reason:

* Simple operational model
* Easier SaaS evolution
* Clear tenant boundaries
* Testable isolation
* Fits the current Laravel architecture

---

### Consequences

Advantages:

* The system can evolve toward Multi-Clinic SaaS.
* Each clinic can own independent configuration.
* Future query isolation becomes explicit and testable.
* The accounting engine can remain shared.

Disadvantages:

* More explicit scoping is required in services.
* Developers must consistently pass or resolve clinic context.
* More tests are required to prevent cross-clinic data leakage.

---

### Affected Components

Future changes will affect:

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
* Admin Controllers
* Import Services
* Accounting Resolvers

---

### Related Documentation

* ROADMAP.md
* DEVELOPMENT_GUIDE.md
* ARCHITECTURE_PRINCIPLES.md
* DATABASE_SCHEMA.md
* SERVICES.md
* WORKFLOWS.md
* API.md

---

### Related Future Milestones

* Milestone 06 — Clinic Model
* Milestone 07 — Attach clinic_id
* Milestone 08 — Current Clinic Resolver
* Milestone 09 — Query Isolation
* Milestone 10 — Dynamic Business Rules
* Milestone 11 — Multi-Clinic Testing

---

### Notes

Clinic 111 remains the first tenant.

During migration, all existing data will eventually be assigned to `CLINIC_111`.

Multi-Clinic must be implemented gradually.

No milestone may introduce partial tenant isolation without tests.

---

# ADR Index

| ADR     | Title                               | Status   |
| ------- | ----------------------------------- | -------- |
| ADR-001 | TOTAL Means Collected Payments      | Accepted |
| ADR-002 | JOB Means Lab Cost                  | Accepted |
| ADR-003 | Database Driven Business Rules      | Accepted |
| ADR-004 | Lab Price Fallback Chain            | Accepted |
| ADR-005 | bcmath for Money Calculations       | Accepted |
| ADR-006 | Isolated Excel Parser               | Accepted |
| ADR-007 | Rule-Based Treatment Parser         | Accepted |
| ADR-008 | Shared Accounting Pipeline          | Accepted |
| ADR-009 | Approved Reports Are Read-Only      | Accepted |
| ADR-010 | Soft Delete Strategy                | Accepted |
| ADR-011 | Sanctum Authentication              | Accepted |
| ADR-012 | Private File Storage                | Accepted |
| ADR-013 | Sanitized Raw Import Data           | Accepted |
| ADR-014 | Financial Rounding Rules            | Accepted |
| ADR-015 | Explicit Null Checks                | Accepted |
| ADR-016 | Database Driven Export Profiles     | Accepted |
| ADR-017 | Patient Privacy                     | Accepted |
| ADR-018 | Work Items for All Valid Treatments | Accepted |
| ADR-019 | Import Validation Warnings          | Accepted |
| ADR-020 | Delete Uploaded Excel Files         | Accepted |
| ADR-021 | Laboratory Soft Deactivate          | Accepted |
| ADR-022 | Database-Driven Treatment Catalog   | Accepted |
| ADR-023 | Admin-Managed Lab Price Catalog     | Accepted |
| ADR-024 | Admin-Managed Doctor Fixed Fee Catalog | Accepted |
| ADR-025 | Configuration Layer                 | Accepted |
| ADR-026 | Clinic Entity as Tenant Root        | Accepted |
| ADR-027 | Current Clinic Resolver           | Accepted |

---

# Future ADR Roadmap

The following architectural topics are expected to receive future ADRs.

ADR-028
Attach clinic_id

ADR-029
Query Isolation

ADR-030
Dynamic Business Rules

ADR-031
Multi-Clinic Registration Wizard

ADR-032
Tenant Security

ADR-033
Multi-Currency Strategy

ADR-034
Accounting Rule Engine

---

# Documentation Rule

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

---

# Long-Term Vision

The project evolves through the following stages.

V1

Single Clinic Accounting Engine

↓

V2

Configurable Accounting Platform

↓

V3

Multi-Clinic SaaS Platform

↓

Future

Enterprise Dental Accounting Platform

---

# Architectural Principle

Business requirements evolve.

Configuration changes.

Accounting data grows.

Architecture should remain stable.

Every ADR exists to protect that stability.

---

# Architecture History

All ADRs below represent the historical evolution of the project.

Do not modify them unless correcting factual mistakes.

New architectural decisions must always be appended as new ADR entries.
