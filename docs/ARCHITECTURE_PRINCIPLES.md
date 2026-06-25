# ARCHITECTURE_PRINCIPLES.md

# Dental Clinic Accounting System

## Architecture Principles

---

# Purpose

This document defines the architectural principles of the project.

These principles should rarely change.

Every architectural decision must respect this document.

---

# Vision

This project is not built for one dental clinic.

The objective is to build a reusable accounting platform capable of supporting multiple clinics with different accounting rules while using one shared codebase.

---

# Core Philosophy

The application consists of two major parts.

## Configuration Layer

Defines:

* Clinics
* Doctors
* Labs
* Treatments
* Lab Prices
* Commission Rules
* Users

Everything inside this layer is configurable.

No source code modifications should be required when business configuration changes.

---

## Accounting Engine

Responsible for:

* Excel Import
* Manual Entry
* Treatment Parsing
* Payment Calculation
* Lab Cost Calculation
* Doctor Income
* Clinic Income
* Daily Reports
* Monthly Reports

This layer should remain deterministic.

---

# Layered Architecture

Presentation Layer

↓

Application Layer

↓

Domain Layer

↓

Persistence Layer

Business logic must never live inside controllers.

---

# Service Layer

Every service has exactly one responsibility.

Examples

PaymentCalculationService

MonthlyIncomeCalculationService

TreatmentParserService

LabJobCalculationService

CurrentClinicResolver

Never create generic services such as:

AccountingService

UtilityService

HelperService

---

# Database Driven Rules

Business configuration belongs inside the database.

Examples

Doctor commission

Lab prices

Treatment catalog

Clinic settings

Fixed doctor fees

Never hardcode accounting values.

---

# Separation of Configuration and Transactions

Configuration changes rarely.

Transactions happen every day.

Examples

Configuration

Doctors

Labs

Treatments

Prices

Reports

Transactions

Payments

Daily Reports

Work Items

Lab Jobs

These domains must remain separated.

---

# Multi Clinic Isolation

Every clinic must be completely isolated.

No clinic should ever access another clinic's data.

Isolation is enforced using:

clinic_id

CurrentClinicResolver

Authorization

Query Isolation

---

# Single Source of Truth

Every business rule must exist in only one place.

Examples

Lab Price

One resolver.

Commission

One calculation service.

Treatment Parsing

One parser.

Avoid duplicated logic.

---

# Extensibility

Future features should require configuration rather than code changes.

Examples

New Doctor

Database

New Treatment

Database

New Lab

Database

New Currency

Configuration

New Clinic

Configuration

---

# Security Principles

Every request must be authenticated.

Every action must be authorized.

Financial modifications must create Audit Logs.

Patient identifiers should never be exposed unnecessarily.

Uploaded Excel files should not remain permanently on the server.

---

# Testing Philosophy

Every business rule requires automated tests.

Whenever a bug is fixed:

A regression test must be added.

The same bug should never appear twice.

---

# Documentation First

Architecture is part of the product.

Every major architectural decision must be documented.

If a new developer cannot understand the system by reading the documentation, the documentation is incomplete.

---

# Stability Before Features

Never sacrifice stability for speed.

New features are added only when:

Tests remain green.

Documentation is updated.

Architecture remains clean.

---

# Backward Compatibility

Existing accounting calculations should remain compatible whenever possible.

Breaking changes require:

Architecture decision

Migration strategy

Documentation update

Test update

---

# Long-Term Goal

The final system should support:

Multiple Clinics

Different Currencies

Different Accounting Rules

Different Doctor Compensation Models

Different Lab Pricing Strategies

Without changing the core Accounting Engine.

The Accounting Engine should remain reusable regardless of clinic-specific configuration.

---

# Architectural Motto

Configuration changes.

Business data grows.

Architecture remains stable.

