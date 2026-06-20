ch # Business Rules

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

Stored as `lab_jobs.total_cost_aed` per work item. Only treatments where `treatments.has_lab_cost = true` generate lab jobs.

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

Fixed fees come from `doctor_fixed_fees` matched by `doctor_id` + `treatment_id`. USD fees are converted to AED using the default exchange rate.

#### Dr Wa Fixed Fees (seeded)

| Treatment | Code | Fee | Currency |
|---|---|---|---|
| Implant | IMPL | 500 | AED |
| Bone Graft | BG | 300 | USD |
| Sinus Lift | SINUS | 200 | USD |

No percentage calculation is used for Dr Wa.

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

## Treatments Without Lab Cost

| Code | Name |
|---|---|
| CF | Composite Filling |
| AF | Amalgam Filling |
| RCT | Root Canal Treatment |
| RE-RCT | Repeat Root Canal Treatment |
| REPAIR | Repair |
| REMOV | Removable Tooth |
| BG | Bone Graft (Dr Wa fixed fee only) |
| SINUS | Sinus Lift (Dr Wa fixed fee only) |

---

## Daily Report Status Rules

| Status | Meaning |
|---|---|
| `uploaded` | File received, processing started |
| `parsed` | Rows and payments created; treatments parsed |
| `calculated` | Lab jobs calculated |
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
CLINIC INCOME    = NET TOTAL - DOCTOR INCOME
```

Payment breakdowns are also reported separately:

- `total_dhs` — sum of DHS payments
- `total_usd_to_aed` — sum of USD payments (already converted)
- `total_visa` — sum of VISA payments

Treatment counts are grouped by treatment code for the month.

---

## What Changed

**Updated — 2026-06-19**

- MC lab cost corrected: 405 → **105 AED**
- Doctor renamed: Dr Riyadh → **Dr Riyad** (code: `RIYAD`)
- Treatment names updated: RE-RCT, REMOV, ABT descriptions
- Documented lab deduction rule for percentage doctors

**Initial documentation — 2026-06-19**

Created:

- `docs/BUSINESS_RULES.md` — all V1 accounting formulas and seeded price rules

Verified by tests:

- `PaymentCalculationServiceTest`
- `LabPriceResolverTest`
- `LabJobCalculationServiceTest`
- `MonthlyIncomeCalculationServiceTest`
