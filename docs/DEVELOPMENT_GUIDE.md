# DEVELOPMENT_GUIDE.md

# Dental Clinic Accounting System

## Development Guide

---

# Purpose

This document defines how this project must be developed.

Every contributor, including AI assistants (Cursor), must follow these rules.

The objective is:

* Maintainability
* Predictability
* Scalability
* Clean Architecture
* Stable Business Logic

The project should remain understandable even after several years of development.

---

# Project Philosophy

This project is **NOT** built around one clinic.

It is built as a reusable accounting engine.

Current customer:

Clinic 111

Future goal:

Multiple independent clinics.

The architecture must always support future expansion without requiring major rewrites.

---

# Golden Rules

## Rule 1

Only one Milestone may be implemented at a time.

Never combine multiple milestones.

---

## Rule 2

Never skip documentation.

Every completed milestone must update:

* ROADMAP.md
* PROJECT_OVERVIEW.md
* DATABASE_SCHEMA.md
* SERVICES.md
* API.md
* WORKFLOWS.md
* DECISIONS.md

if applicable.

---

## Rule 3

Every feature requires automated tests.

No feature is complete without tests.

---

## Rule 4

Existing tests must remain green.

Never sacrifice stability to implement a new feature.

---

## Rule 5

Business rules belong to the database whenever possible.

Avoid hardcoded values.

Bad:

if doctor == "Jack"

Good:

doctor->commission_type

---

## Rule 6

Controllers must remain thin.

Controllers only:

* validate
* authorize
* call services
* return responses

Business logic belongs inside Services.

---

## Rule 7

One Service = One Responsibility

Bad:

AccountingService

Good:

PaymentCalculationService

LabJobCalculationService

MonthlyIncomeCalculationService

TreatmentParserService

---

## Rule 8

Never duplicate Business Logic.

If a calculation exists,

reuse it.

Do not implement the same calculation twice.

---

## Rule 9

Configuration belongs in the database.

Doctors

Labs

Treatments

Lab Prices

Commission Rules

must be configurable.

---

## Rule 10

Never break backwards compatibility without documenting it.

---

# Development Workflow

Every feature follows exactly this workflow.

Business Requirement

↓

Architecture Discussion

↓

Milestone Planning

↓

Prompt

↓

Implementation

↓

Tests

↓

Documentation

↓

Git Commit

↓

Git Push

↓

Next Milestone

---

# Git Workflow

Before starting

git checkout feature/...

After implementation

php artisan test

All tests must pass.

Commit

Push

Merge only after review.

---

# Documentation Workflow

Every milestone must update documentation.

Minimum:

ROADMAP

Development Guide

Architecture Decision

Database

Services

API

Workflow

---

# Cursor Workflow

Before writing code Cursor must:

1.

Read ROADMAP.md

2.

Read DEVELOPMENT_GUIDE.md

3.

Understand current milestone.

4.

Verify scope.

5.

Implement only the requested milestone.

6.

Never start the next milestone automatically.

---

# Scope Rules

Cursor must never:

Refactor unrelated code.

Rename unrelated classes.

Change architecture without request.

Implement future milestones.

Introduce breaking changes without approval.

---

# Testing Rules

Every new feature requires:

Feature Tests

Unit Tests

Authorization Tests

Validation Tests

Edge Case Tests

Regression Tests if applicable.

---

# Business Rules

Accounting calculations must remain deterministic.

No AI.

No guessing.

Every result must be reproducible.

---

# Security Rules

Never expose:

patient identifiers

hashes

audit internals

unless explicitly required.

Every write operation must be authenticated.

Every admin operation must be authorized.

Every important financial modification must create an Audit Log.

---

# Naming Rules

Use descriptive names.

Bad

$data

$tmp

$obj

Good

$dailyReport

$doctorIncome

$labPrice

$treatmentParser

---

# Architecture Rules

Always prefer:

Small Classes

Small Services

Single Responsibility

Dependency Injection

Composition

Avoid:

God Classes

Static Business Logic

Long Controllers

Duplicated Logic

---

# Multi Clinic Rule

Multi Clinic must never be implemented partially.

Implementation order:

Clinic ✅

↓

clinic_id (configuration models) ✅

↓

Current Clinic Resolver

↓

Query Isolation

↓

Dynamic Business Rules

↓

Registration

↓

Testing

No shortcuts.

---

# Configuration clinic_id (Milestone 07)

`clinic_id` is required on configuration tables:

* users
* doctors
* labs
* treatments
* lab_prices
* doctor_fixed_fees

Rules:

* Migration backfills existing rows to `CLINIC_111`
* Seeders resolve clinic by code — never hardcode IDs
* `BelongsToClinic` trait assigns `CLINIC_111` on create when unset (transitional until Milestone 08)
* No query isolation, no resolver, no global scopes
* Accounting engine and import pipeline unchanged

---

# Prompt Rules

Every prompt must contain:

Goal

Current State

Scope

Out of Scope

Acceptance Criteria

Tests

Documentation

Git Workflow

Next Milestone

---

# Definition of Done

A milestone is complete only if:

Implementation finished

Tests green

Documentation updated

Git committed

Roadmap updated

Next milestone identified

---

# Long Term Vision

The goal is not to build software for one clinic.

The goal is to build a professional Dental Clinic Accounting Platform that can support many clinics with different accounting rules while using the same clean codebase.
