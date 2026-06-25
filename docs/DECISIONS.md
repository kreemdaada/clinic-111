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

---

# Future ADR Roadmap

The following architectural topics are expected to receive future ADRs.

ADR-023

Doctor Fixed Fee Administration

ADR-024

Configuration Dashboard

ADR-025

Clinic Entity

ADR-026

Attach clinic_id

ADR-027

Current Clinic Resolver

ADR-028

Query Isolation

ADR-029

Dynamic Business Rules

ADR-030

Multi-Clinic Registration Wizard

ADR-031

Tenant Security

ADR-032

Multi-Currency Strategy

ADR-033

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
