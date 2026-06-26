# ROADMAP.md

# Dental Clinic Accounting System

## Project Vision

Build a production-grade Dental Clinic Accounting Platform.

The system must not be tightly coupled to one clinic.

Every accounting rule must be configurable from the database instead of being hardcoded.

The long-term goal is to support multiple clinics using the same codebase.

---

# Current Version

Version: **V2 Stable**

Status:

✅ Production Prototype

Current Features

* Excel Daily Report Import
* Daily Report Parser
* Treatment Parser
* Work Item Engine
* Lab Job Engine
* Payment Calculation
* Monthly Income Calculation
* Doctor Administration
* Laboratory Administration
* Treatments Administration
* User Administration
* Authentication
* Role Based Access Control
* Audit Logs
* Report Locking
* Privacy Protection
* Validation Summary
* Automated Tests

Current Test Status

123+ automated tests passing.

**Updated — 2026-06-26:** Milestone 02 (Treatments Administration) complete — 153 tests.

**Updated — 2026-06-25:** Milestone 01 (Laboratory Administration) complete — 138 tests.

---

# Development Rules

Only one milestone may be implemented at a time.

Never implement two milestones together.

Every milestone must finish with:

* All tests green
* Documentation updated
* Git Commit
* Git Push

Only after that may the next milestone begin.

---

# Test Database Isolation

Local development and automated tests must **never share the same SQLite file**.

| File | Purpose |
|------|---------|
| `database/database.sqlite` | **Dev** — `php artisan serve`, manual UI work, registered clinics |
| `database/testing.sqlite` | **Tests only** — `php artisan test` (via `phpunit.xml` + `.env.testing`) |

Configuration

* `.env` / `.env.example` — dev app (`database/database.sqlite` when using SQLite)
* `.env.testing` — test env with `DB_DATABASE=database/testing.sqlite`
* `phpunit.xml` — sets `APP_ENV=testing` and the test database path

Rules

* Run **`php artisan test`** freely — it uses `testing.sqlite` only; dev data stays intact.
* **`php artisan migrate:fresh`** without `--env=testing` wipes the **dev** database (users, clinics, reports).
* Do not point tests at `database/database.sqlite`.
* Both `*.sqlite` files are gitignored under `database/.gitignore`.

After accidental dev DB loss: `php artisan db:seed` restores Clinic 111 demo users (`admin@clinic.test` / `password`).

See also `docs/DEVELOPMENT_GUIDE.md` (Git Workflow section).

---

# Milestone Roadmap

## Milestone 01

### Labs Administration

Status

**DONE**

Priority

High

Goal

Allow administrators to manage laboratories without touching source code.

Deliverables

* Labs CRUD
* Activate / Deactivate
* Validation
* Audit Logs
* Tests
* Documentation

Definition of Done

* Tests green
* Documentation updated
* Feature reviewed

Next

Milestone 02

---

## Milestone 02

### Treatments Administration

Status

**DONE**

Goal

Allow administrators to configure treatments from the UI.

Deliverables

* CRUD
* Validation
* Audit Logs
* Tests

Next

Milestone 03

---

## Milestone 03

### Doctor Fixed Fee Administration

Status

TODO

Goal

Configure fixed-fee doctors without code changes.

Deliverables

* CRUD
* Effective Dates
* Validation
* Audit Logs

Next

Milestone 04

---

## Milestone 04

### Configuration Dashboard

Status

TODO

Goal

Create one central administration dashboard.

Modules

* Doctors
* Labs
* Treatments
* Lab Prices
* Doctor Fixed Fees

Next

Milestone 05

---

## Milestone 05

### Clinic Model

Status

TODO

Goal

Introduce the Clinic entity.

Important

No accounting calculation changes.

Only create the Clinic domain.

Next

Milestone 06

---

## Milestone 06

### Add clinic_id

Status

TODO

Goal

Attach configuration tables to clinics.

No calculation changes.

Next

Milestone 07

---

## Milestone 07

### Current Clinic Resolver

Status

TODO

Goal

Resolve the current clinic from the authenticated user.

Next

Milestone 08

---

## Milestone 08

### Query Isolation

Status

TODO

Goal

Every query must only return data for the current clinic.

Next

Milestone 09

---

## Milestone 09

### Dynamic Business Rules

Status

TODO

Goal

Allow every clinic to define its own accounting rules.

Examples

* Percentage After Lab
* Percentage Before Lab
* Fixed Procedure
* No Commission
* Clinic Income Only

Next

Milestone 10

---

## Milestone 10

### Registration Wizard

Status

TODO

Goal

Allow a new clinic to register itself.

Workflow

Register

↓

Create Clinic

↓

Create Administrator

↓

Configure System

↓

Ready

Next

Milestone 11

---

## Milestone 11

### Multi Clinic Testing

Status

TODO

Goal

Verify complete tenant isolation.

Test Cases

* Two clinics
* Different currencies
* Different labs
* Different commission rules
* Different treatments
* No data leakage

---

# Version 3 Stable

Requirements

* Multi Clinic
* SaaS Ready
* Production Ready
* Documentation Complete
* 200+ Automated Tests

Version 3 will be considered complete only when all milestones are marked as DONE.
