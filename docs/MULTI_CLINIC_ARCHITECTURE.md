# MULTI_CLINIC_ARCHITECTURE.md

# Dental Clinic Accounting System

## Multi-Clinic Architecture

---

# 1. Purpose

This document defines the Multi-Clinic architecture for the Dental Clinic Accounting System.

The purpose is to make the system support multiple independent clinics using one shared codebase.

Each clinic must be able to manage its own:

* Users
* Doctors
* Laboratories
* Treatments
* Lab Prices
* Doctor Fixed Fees
* Daily Reports
* Accounting Reports
* Currency
* Timezone
* Future accounting rules

without affecting another clinic.

This document is the architectural basis for all milestones after the Clinic Model milestone.

---

# 2. What Multi-Clinic Means

In this system, a clinic is a tenant.

A tenant is an isolated business unit.

One clinic must not see, edit, import, export, or calculate another clinic's data.

Example:

Clinic 111

* Currency: AED
* Timezone: Asia/Dubai
* Doctors: Dr. Name1, Dr. Name2, Dr. Name3, Dr. Name4
* Labs: Main Lab, Name2 Lab
* Accounting rule: percentage after lab cost

Clinic 222

* Currency: EUR
* Timezone: Europe/Berlin
* Different doctors
* Different labs
* Different prices
* May not use doctor percentage at all

Both clinics use the same codebase.

They do not share business data.

---

# 3. Core Rule

One shared Accounting Engine.

Many clinic-specific configurations.

The Accounting Engine must remain shared.

The Configuration Layer becomes clinic-scoped.

---

# 4. Current Architecture

The current system already has:

* Clinic model
* Configuration Dashboard
* Doctors Administration
* Laboratories Administration
* Treatments Administration
* Lab Prices Administration
* Doctor Fixed Fees Administration
* Users Administration
* Excel Import
* Daily Reports
* Monthly Income
* Audit Logs
* RBAC
* Privacy Protection

Currently, Clinic 111 exists as the first clinic.

However, configuration models are now scoped by `clinic_id` (Milestone 07). Accounting and transactional tables are not scoped yet.

The next milestones will gradually attach data to clinics.

---

# 5. Tenant Root

`Clinic` is the tenant root.

All clinic-owned data must eventually belong to one clinic.

Root entity:

```text
clinics
```

A clinic has many:

```text
users
doctors
labs
treatments
lab_prices
doctor_fixed_fees
daily_reports
audit_logs
```

Transactional data is connected either directly or indirectly through `daily_reports` and configuration models.

---

# 6. Tables That Must Be Clinic-Scoped

The following tables must receive `clinic_id` in future milestones.

## Configuration Tables

```text
users
doctors
labs
treatments
lab_prices
doctor_fixed_fees
daily_reports
audit_logs
```

## Transactional Tables

These may receive `clinic_id` directly or be scoped through parent records.

```text
daily_work_rows
payments
work_items
lab_jobs
daily_report_import_warnings
```

Preferred strategy:

* `daily_reports` gets `clinic_id`
* `daily_work_rows` belongs to `daily_report`
* `payments` belong to `daily_work_row`
* `work_items` belong to `daily_work_row`
* `lab_jobs` belong to `work_item`

Direct `clinic_id` on every transactional table is optional and should only be added if performance, reporting, or safety requires it.

---

# 7. Tables That Should Not Be Clinic-Scoped

The following are global technical tables unless future requirements say otherwise:

```text
migrations
password_reset_tokens
personal_access_tokens
jobs
failed_jobs
cache
sessions
```

---

# 8. User and Clinic Relationship

Every authenticated user belongs to exactly one clinic.

Planned structure:

```text
users.clinic_id
```

A user without a clinic is invalid after registration is complete.

Temporary user-without-clinic state is allowed only inside a database transaction during registration.

---

# 9. Registration Workflow

**Status:** Implemented (Milestone 11, ADR-030).

Routes: web `GET/POST /register-clinic`, API `POST /api/register-clinic`.

The registration form is clinic registration, not only user registration.

The form collects:

## Clinic Information

* Clinic Name
* Clinic Code
* Country
* Base Currency
* Timezone

## Owner Information

* Owner Name
* Owner Email
* Owner Password

The registration process runs inside one database transaction via `ClinicOnboardingService`:

```text
Validate request
↓
Start transaction
↓
Create Clinic
↓
Create Owner/Admin User
↓
Assign user.clinic_id = clinic.id
↓
Create default lab ({CLINIC_CODE}_MAIN_LAB)
↓
Commit transaction
↓
Login owner
↓
Redirect to Configuration Dashboard
```

Default configuration is minimal — one lab only. Doctors, treatments, lab prices, and fixed fees are configured after onboarding.

If any step fails, the transaction is rolled back.

A clinic must not exist without an owner.

An owner must not exist without a clinic.

---

# 10. Clinic 111 Migration Strategy

Clinic 111 is the existing first tenant.

The migration strategy is:

```text
Create CLINIC_111
↓
Add clinic_id columns gradually
↓
Backfill existing data with CLINIC_111.id
↓
Make clinic_id required where safe
↓
Resolve current clinic from authenticated user
↓
Add query isolation
↓
Add cross-clinic leakage tests
```

No existing accounting behavior should change during the migration.

Clinic 111 must continue producing the same reports before and after migration.

---

# 11. No Global Scopes

The system will not use Laravel Global Scopes for tenant isolation.

Reason:

* Hidden query behavior
* Harder debugging
* Risky exports
* Risky imports
* Risky background jobs
* Difficult admin/reporting scenarios

Instead, clinic isolation is explicit.

---

# 12. Current Clinic Resolution

Current clinic will be resolved by a dedicated service:

```text
CurrentClinicResolver
```

**Status (Milestone 08):** Implemented. Resolves `auth()->user()->clinic_id` to a `Clinic` model. No fallback clinic. Configuration services assign `clinic_id` on create.

**Status (Milestone 09, ADR-028):** Implemented for configuration reads. Every configuration service list/show/query method filters by `CurrentClinicResolver::resolveId()`.

Expected behavior:

```text
Authenticated user
↓
user.clinic_id
↓
Clinic
```

If a user has no clinic, the request must fail.

The resolver must be used by services that query clinic-owned data.

---

# 13. Query Isolation Strategy

Do not rely on hidden behavior.

Preferred style:

```php
Doctor::query()
    ->where('clinic_id', $currentClinic->id)
    ->get();
```

or service-level methods:

```php
$doctorService->listForClinic($clinic);
```

Every clinic-scoped query must be explicit.

---

# 14. Configuration Ownership

Each clinic owns its configuration.

Clinic A can have:

* Different doctors
* Different labs
* Different treatments
* Different lab prices
* Different doctor fixed fees

Clinic B can have completely different configuration.

The Accounting Engine must not care which clinic is currently active.

It only receives resolved configuration.

---

# 15. Accounting Engine

The Accounting Engine remains shared.

Do not duplicate the Accounting Engine per clinic.

Shared services include:

* PaymentCalculationService
* LabJobCalculationService
* MonthlyIncomeCalculationService
* TreatmentParserService
* MoneyCalculator
* Import pipeline

These services receive clinic context through `CurrentClinicResolver` and explicit `where('clinic_id', …)` filtering (Milestone 10). Core formulas remain shared.

Hard rules (ADR-029):

1. **`clinic_id` is immutable** after create — enforced by `ImmutableClinicOwnership` on accounting models.
2. **Children inherit from parent** — only root creates use `CurrentClinicResolver`; payments/work items/lab jobs copy parent `clinic_id`.
3. **No relation traversal for tenant queries** — use `AccountingScopedQuery` (`WHERE clinic_id AND parent_id`), never `$report->payments()`.

---

# 16. Currency Strategy (ADR-034)

Each clinic has one base currency stored as `clinics.currency` (ISO code).

Supported codes (Milestone 14): AED, EUR, USD, SAR, GBP — defined in `config/currencies.php` via `CurrencyCatalog`.

Symbol and precision are resolved at runtime from the catalog (not duplicated on the clinic row).

**Accounting rule:** All calculations run in the clinic base currency. No implicit conversion inside the accounting engine.

**Display:** `CurrencyFormatter` centralizes UI formatting.

**Future (not Milestone 14):**

* Versioned exchange rates (`ExchangeRateProvider` contract)
* Cross-clinic SaaS reporting (`CurrencyConversionService` contract)
* Rename AED-specific normalized storage columns where necessary
* Store original amount, original currency, and exchange rate at transaction time

Do not perform this refactor until a dedicated Multi-Currency milestone.

---

# 17. Configuration Templates

Future clinic registration may use configuration templates.

Example templates:

```text
Blank Clinic
Dental Clinic Default
Clinic 111 Copy
Europe Basic
```

Initial implementation may start with a blank configuration.

Later, templates can create:

* default treatments
* default labs
* default lab prices
* default doctor compensation modes

Templates must be optional.

---

# 18. Security

Tenant security requires:

* authentication
* email verification for new clinic owners (Milestone 13A)
* clinic_id on user
* explicit clinic-scoped queries
* authorization checks
* audit logs (tenant and platform contexts)
* cross-clinic leakage tests
* CAPTCHA on public registration (config-driven)
* production security headers
* self-service password reset for guests — **planned** (ADR-040; web-only v1; admin reset already exists)

A user from Clinic A must never access Clinic B data.

This must be enforced in:

* Web controllers
* API controllers
* Services
* Exports
* Imports
* Reports
* Audit views

**Milestone 13A (implemented):** Platform audit context, NAT-aware login throttle, email verification, CAPTCHA abstraction, security headers.

**Milestone 13B (implemented):** Full tenant authorization review — `TenantResourceGuard`, cross-clinic 404 enforcement, clinic-scoped FK validation, user admin `{managedUser}` route fix.

**Milestone 14 (implemented):** Multi-currency foundation — currency catalog, `Money` value object, centralized formatting, clinic currency validation; accounting behaviour unchanged (ADR-034).

**Self-service password reset (ADR-040):** Web forgot/reset via Laravel Password Broker and Resend (ADR-038). Admin reset remains available. Public API forgot/reset is out of scope for v1.

**PostgreSQL production (ADR-035):** One shared PostgreSQL database in production; tenant isolation remains via `clinic_id`. Local dev and tests stay on SQLite. Data migration from SQLite uses `app:migrate-sqlite-to-pgsql` without changing tenant boundaries.

---

# 19. Audit Logs

Audit logs belong to a clinic for tenant-scoped events. Platform-scoped events (unknown email login, registration abuse) use `clinic_id = null` and `new_values.audit_context = platform` (Milestone 13A, ADR-033).

Reason:

* Admins should see only their clinic's audit history
* SaaS operators may later need global audit views

Current approach:

```text
audit_logs.clinic_id  — NULL for platform events, clinic ID for tenant events
audit_logs.new_values.audit_context  — "platform" | omitted for tenant events
```

Clinic admin sees clinic audit logs.

Platform admin may see global audit logs.

Platform admin UI is out of scope for current milestones.

---

# 20. Import Isolation

Excel imports must run inside the current clinic context.

Import pipeline must eventually enforce:

```text
Uploaded report
↓
current clinic
↓
doctors/treatments/labs from that clinic only
↓
calculated report for that clinic only
```

Unknown doctor or treatment from another clinic must not be resolved accidentally.

---

# 21. Export Isolation

Exports must use current clinic context.

A clinic must never export another clinic's reports.

Future export services must scope by clinic explicitly.

---

# 22. Configuration Dashboard

**Status (Milestone 09, ADR-028):** Implemented. `ConfigurationDashboardService` scopes module counts, health warnings, and recent audit activity to the authenticated user's clinic.

Behavior:

```text
Current clinic
↓
ConfigurationDashboardService
↓
Counts only for this clinic
Warnings only for this clinic
Audit only for this clinic
```

No global counts for clinic admins.

---

# 23. Milestone Strategy

Multi-Clinic must be implemented gradually.

Recommended sequence:

## Milestone 06

Clinic Model

Status:

Done

## Milestone 07

Attach clinic_id to core configuration tables.

Status:

Done

## Milestone 08

CurrentClinicResolver.

Status:

Done

## Milestone 09

Query Isolation.

## Milestone 10

Accounting Ownership and Isolation (ADR-029) — **Done**

## Milestone 11

Clinic Registration / Onboarding Wizard (ADR-030).

Status:

Done

## Milestone 12

Dynamic Business Rules.

## Future

Multi-Currency.

---

# 24. Milestone 07 Scope

Milestone 07 should attach `clinic_id` to the configuration tables below.

Implemented group (Milestone 07):

```text
users
doctors
labs
treatments
lab_prices
doctor_fixed_fees
```

Deferred to later milestones:

```text
audit_logs (financial events only — now done in M10)
```

Implemented in Milestone 10 (ADR-029):

```text
daily_reports
daily_work_rows
payments
work_items
lab_jobs
audit_logs
```

Milestone 07 must:

* add nullable clinic_id
* backfill with CLINIC_111
* add indexes
* add foreign keys
* update models
* update seeders
* keep existing tests green
* not implement query isolation yet

Milestone 07 must not:

* add global scopes
* change accounting formulas
* change imports
* change exports
* implement registration
* implement multi-currency

---

# 25. Cross-Clinic Leakage Risks

Risks:

* Admin from Clinic A sees Clinic B doctors
* Import resolves treatment from wrong clinic
* Lab price resolver uses another clinic's lab price
* Audit dashboard shows another clinic's audit log
* Monthly report aggregates another clinic's data
* User management edits another clinic's users

These must be tested in later milestones.

---

# 26. Design Principles

```text
Clinic owns configuration.
Configuration feeds Accounting Engine.
Accounting Engine remains shared.
Tenant isolation is explicit.
No global scopes.
No hidden query behavior.
Every clinic-specific query is testable.
```

---

# 27. Definition of Done for Multi-Clinic

Multi-Clinic is complete only when:

* Every user belongs to a clinic
* Every configuration record belongs to a clinic
* Every report belongs to a clinic
* Imports are clinic-scoped
* Exports are clinic-scoped
* Dashboard is clinic-scoped
* Admin screens are clinic-scoped
* Cross-clinic leakage tests exist
* Clinic registration works
* Guided business configuration blocks import until ready (ADR-031)
* Clinic 111 reports remain unchanged
* Documentation is updated
* ADRs are complete

---

# 28. Final Rule

Do not move fast on Multi-Clinic.

Tenant isolation bugs are serious.

Small milestones are mandatory.

Each milestone must end with:

```text
php artisan test
```

and all tests green.

---

# 29. Guided Business Configuration (ADR-031)

After onboarding, each clinic administrator configures business rules independently:

| Step | Required | Completion rule |
|---|---|---|
| Doctors | Yes | ≥ 1 active doctor |
| Laboratories | Yes | ≥ 1 active lab (default lab from onboarding satisfies this) |
| Treatments | Yes | ≥ 1 active treatment |
| Lab Prices | Yes | ≥ 1 active lab price |
| Doctor Fixed Fees | Conditional | Required only when active no-commission doctors exist |
| Import | — | Allowed when all required steps are complete |

Nothing is copied from Clinic 111. Import is blocked until the checklist passes.
