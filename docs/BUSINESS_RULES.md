# Business Rules

All accounting formulas live in service classes (primarily `PaymentCalculationService`, `LabJobCalculationService`, `MonthlyIncomeCalculationService`). This document is the single source of truth for **what** is calculated; services implement **how**.

Money is always stored and calculated using decimal columns and `bcmath`. Amounts normalized to AED use the `_aed` suffix.

---

## TOTAL (Collected Payments)

**TOTAL does NOT mean treatment value.** TOTAL means collected payments.

```
TOTAL (paid_total_aed) =
    DHS
  + USD converted to AED
  + VISA
```

Where:

```
USD converted to AED = usd_amount × exchange_rate
```

Default exchange rate: **3.65** (configurable via `ACCOUNTING_USD_EXCHANGE_RATE` in `.env`).

### Example

| Source | Amount | AED equivalent |
|---|---|---|
| DHS | 1,000 AED | 1,000.00 |
| USD | 500 USD × 3.65 | 1,825.00 |
| VISA | 200 AED | 200.00 |
| **TOTAL** | | **3,025.00 AED** |

Each non-zero payment component is also stored as a separate row in the `payments` table.

---

## LAB COST (JOB)

**JOB is NOT a job number.** JOB is the total lab cost.

```
LAB COST =
    SUM(work_item.quantity × lab_price.unit_cost)
```

Stored as `lab_jobs.total_cost_aed` per work item. A lab job is created only when `treatments.has_lab_cost = true` **and** `doctor_lab_billings.bill_lab_job = true` for that doctor and treatment.

### Lab JOB eligibility (per doctor)

| Doctor | bill_lab_job treatments |
|---|---|
| Dr Jack | All lab-cost codes (MC, ZIR, IMPL-CR, …) |
| Dr Riyad | All lab-cost codes (ZIR / IMPL-ZIR use Riyad lab prices) |
| Dr Puriya | **MC, ZIR, POST, REMOV only** |
| Dr Wa | **None** — fixed fees only (IMPL, BG, SINUS) |

Table: `doctor_lab_billings` (`doctor_id`, `treatment_id`, `bill_lab_job`). No row = no JOB.

### Lab Price Resolution

When calculating unit cost, the system looks up `lab_prices` in this order:

1. **Doctor-specific price** — `lab_prices` where `doctor_id` matches the row's doctor.
2. **Default price** — `lab_prices` where `doctor_id IS NULL`.

Both lookups respect `valid_from` and `valid_to` if set.

### Lab Selection

1. Use `doctors.default_lab_id` if set and lab is active.
2. Otherwise fall back to the first active lab.

### Example

Dr Riyad, ZIR × 4:

```
unit_cost = 400 AED  (doctor-specific override)
LAB COST  = 4 × 400 = 1,600 AED
```

Dr Jack, ZIR × 4:

```
unit_cost = 360 AED  (default price)
LAB COST  = 4 × 360 = 1,440 AED
```

---

## NET TOTAL

```
NET TOTAL = TOTAL - LAB COST
```

Both values are in AED.

### Example

```
TOTAL    = 43,701.25 AED
LAB COST =  8,110.00 AED
NET TOTAL = 35,591.25 AED
```

---

## DOCTOR INCOME

Doctor income depends on `doctors.commission_type`.

### Percentage Doctors (`commission_type = percentage`)

```
DOCTOR INCOME = NET TOTAL × commission_percentage / 100
```

Result is rounded to 2 decimal places (standard half-up rounding via `MoneyCalculator`).

#### Example

```
NET TOTAL              = 35,591.25 AED
commission_percentage  = 35%
DOCTOR INCOME          = 12,456.94 AED
```

Applies to: Dr Jack (35%), Dr Riyad (35%), Dr Puriya (25%).

Dr Puriya uses the same JOB / Income Excel layout as other percentage doctors, but only lab treatments **MC, ZIR, POST, REMOV** appear in columns H–P (no IMPL, ABT, etc.).

For all percentage doctors, **lab costs are deducted before commission**:

```
NET TOTAL = TOTAL - LAB COST
DOCTOR INCOME = NET TOTAL × commission_percentage / 100
```

This means lab expenses reduce the doctor's income share (not the clinic's collected total).

### Fixed Doctors (`commission_type = fixed`)

```
DOCTOR INCOME = SUM(fixed_fee_amount_aed × work_item.quantity)
```

Fixed fees come from `doctor_fixed_fees` matched by `doctor_id` + `treatment_id` + work date (`valid_from` / `valid_to`) via `DoctorFixedFeeResolver`. Only **active** rows apply. For Dr Wa, BG/SINUS payout currency follows the patient row payment (see below); monthly totals may still use AED equivalents for USD lines.

Administrators manage fixed fees from `/doctor-fixed-fees` (Milestone 04). Percentage doctors ignore these rules.

#### Dr Wa Fixed Fees (seeded)

| Treatment | Code | Fee | Payout rule |
|---|---|---|---|
| Implant | IMPL | 500 AED | Always AED |
| Bone Graft | BG | 200 USD / unit | USD if row has USD cash; else **(200 × qty) × exchange rate AED** |
| Sinus Lift | SINUS / SINUC | 300 USD / unit | USD if row has USD cash; else **(300 × qty) × exchange rate AED** |

Dr Wa earns income **only** from IMPL, BG, and SINUS work items — no percentage commission, no lab JOB.

Income = **fee × quantity** per treatment line. BG/SINUS: patient pays USD → Wa gets USD; patient pays AED → Wa gets **USD fee converted to AED** (default rate 3.65). IMPL is always AED.

**Example — patient paid USD cash (`BG x2`, `IMPL x1`, `SINUS x1` on one row):**

| Treatment | Calculation | Dr Wa receives |
|-----------|-------------|----------------|
| BG × 2 | 200 × 2 | **400 USD** |
| IMPL × 1 | 500 × 1 | **500 AED** |
| SINUS × 1 | 300 × 1 | **300 USD** |

**Same treatments, patient paid AED (Daily TOTAL in DHS, no USD cash, rate 3.65):**

| Treatment | Calculation | Dr Wa receives |
|-----------|-------------|----------------|
| BG × 2 | 400 × 3.65 | **1,460 AED** |
| IMPL × 1 | 500 × 1 | **500 AED** |
| SINUS × 1 | 300 × 3.65 | **1,095 AED** |

**Single BG × 1 paid in AED:** 200 × 3.65 = **730 AED** (what the customer pays in AED terms; Wa receives the same in AED).

---

## CLINIC INCOME

```
CLINIC INCOME = NET TOTAL - DOCTOR INCOME
```

---

## Seeded Lab Prices

| Code | Treatment | Default (all doctors) | Dr Riyad override |
|---|---|---|---|
| MC | Metal Ceramic Crown | 105 AED | — |
| ZIR | Zircon Crown | 360 AED | 400 AED |
| IMPL-CR | Implant Crown | 160 AED | — |
| IMPL-ZIR | Zircon Implant Crown | 460 AED | 500 AED |
| POST | Post | 55 AED | — |
| ABT | Abutment | 511 AED | — |
| IMPL | Implant | 1,000 AED | — |
| REMOV | Removable Tooth | 100 AED | — |

Dr Wa fixed fees (not lab prices): IMPL **500 AED**, BG **200 USD**, SINUS **300 USD**.

Dr Riyad uses lab `RIYADH_LAB`; all other doctors default to `MAIN_LAB`.

---

## Treatments With Lab Cost

| Code | Name |
|---|---|
| MC | Metal Ceramic Crown |
| ZIR | Zircon Crown |
| IMPL-CR | Implant Crown |
| IMPL-ZIR | Zircon Implant Crown |
| POST | Post |
| ABT | Abutment (the component placed on the implant) |
| IMPL | Implant |
| REMOV | Removable Tooth |

## Treatments Without Lab Cost

| Code | Name |
|---|---|
| CF | Composite Filling |
| AF | Amalgam Filling |
| RCT | Root Canal Treatment |
| RE-RCT | Repeat Root Canal Treatment |
| REPAIR | Repair |
| BG | Bone Graft (Dr Wa fixed fee only) |
| SINUS | Sinus Lift (Dr Wa fixed fee only) |

### Work items vs lab jobs

Every **valid parsed** treatment creates a `work_item` (treatment count, audit, future income rules).

Only treatments with `has_lab_cost = true` create a `lab_job` (column G / JOB).

| Code | work_item | lab_job |
|---|---|---|
| ZIR, MC, POST, REMOV, … | ✓ | ✓ |
| CF, AF, RCT, RE-RCT, REPAIR | ✓ | ✗ |

---

## Daily Report Status Rules

| Status | Meaning |
|---|---|
| `uploaded` | File received, processing started |
| `parsed` | Rows and payments created |
| `calculated` | Work items + lab jobs complete, no parser warnings |
| `needs_review` | Import complete but parser/lab warnings require review |
| `approved` | Report locked — read-only |
| `failed` | Import failed; transaction rolled back |

**Approved reports cannot be overwritten or reprocessed.**

---

## Lab Job Status

| Status | Meaning |
|---|---|
| `calculated` | Automatically computed from lab price |
| `adjusted` | Manually corrected (future use) |
| `cancelled` | Soft-cancelled; excluded from totals (future use) |

Financial records are never physically deleted.

---

## Monthly Income Aggregation

For each active doctor and calendar month:

```
TOTAL COLLECTED  = SUM(payments.amount_aed)     WHERE paid_at in month
LAB COST         = SUM(lab_jobs.total_cost_aed) WHERE work_date in month
NET TOTAL        = TOTAL COLLECTED - LAB COST
DOCTOR INCOME    = per commission_type rules above
CLINIC INCOME    = NET TOTAL - DOCTOR INCOME - NURSE COMMISSION
```

OPG-Normal and OPG-3D columns in monthly income, practice overview, and Excel export are **informative only** (treatment value from `treatment_price_aed × quantity` on nurse commission snapshots). They are **not** added to revenue.

Nurse commission is subtracted **once** from clinic income:

```
NURSE COMMISSION = SUM(nurse_commissions.total_commission_aed)
CLINIC INCOME    = NET TOTAL - DOCTOR INCOME - NURSE COMMISSION
```

Payment breakdowns are also reported separately:

- `total_dhs` — sum of DHS payments
- `total_usd_to_aed` — sum of USD payments (already converted)
- `total_visa` — sum of VISA payments

Treatment counts are grouped by treatment code for the month.

---

## OPG Treatments and Nurse Commission (ADR-039)

### Standard OPG catalog entries

| Code | Name | Treatment price | Flags |
|---|---|---|---|
| `OPG_NORMAL` | OPG-Normal | 200.00 AED | `requires_nurse_commission=true`, `has_lab_cost=false` |
| `OPG_3D` | OPG 3D | 360.00 AED | `requires_nurse_commission=true`, `has_lab_cost=false` |

Provisioned idempotently for each new clinic via `OpgTreatmentProvisioner`. Custom prices on existing codes are not overwritten.

### Treatment price vs lab cost

`treatments.treatment_price` is the **nurse commission basis**, not a lab unit cost and not patient revenue. OPG treatments must not use external lab cost (`has_lab_cost=false`).

### Nurse commission rate

One active rate per nurse + treatment combination (`nurse_commission_rates`). Percentage range 0.01–100.00 with two decimal places.

### Commission formula (per work item)

```
treatment_price_aed = convertToAed(treatment_price, treatment_price_currency)
unit_commission_aed = percentage(treatment_price_aed, commission_percentage)
total_commission_aed = unit_commission_aed × quantity
```

Payment amounts and lab prices do **not** affect nurse commission. Doctor commission rules are unchanged.

### Daily report editor (V1)

- One nurse per treatment code per work row; quantity > 1 applies to the same nurse.
- Different nurses for the same treatment code require separate work rows.

### Import and approval

- Imported OPG without nurse: work item created, warning `nurse_commission_incomplete`, status `needs_review`, no commission snapshot until nurse is assigned in the editor.
- Approve/lock blocked while any eligible work item lacks a complete nurse commission snapshot.

### Expected business errors

Configuration and validation problems (missing nurse, missing rate, incomplete OPG, export reconciliation failures) return friendly web flash messages or structured API 422 responses — never an uncaught HTTP 500 with stack traces for practice users.

---

## What Changed

**Updated — 2026-06-21 (import pipeline)**

- REMOV: lab cost **100 AED** (`has_lab_cost = true`); still creates work_item
- All valid treatments persist as work_items; lab_jobs only when `has_lab_cost`
- Status `needs_review` when import warnings exist
- Patient PII not stored; see WORKFLOWS.md privacy section

**Updated — 2026-06-21**

- MC lab cost corrected: → **105 AED**
- Doctor renamed: Dr Riyadh → **Dr Riyad** (code: `RIYAD`)
- Treatment names updated: RE-RCT, REMOV, ABT descriptions
- Documented lab deduction rule for percentage doctors

**Initial documentation — 2026-06-21**

Created:

- `docs/BUSINESS_RULES.md` — all V1 accounting formulas and seeded price rules

Verified by tests:

- `PaymentCalculationServiceTest`
- `LabPriceResolverTest`
- `LabJobCalculationServiceTest`
- `MonthlyIncomeCalculationServiceTest`
