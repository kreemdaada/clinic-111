# Database Schema

All money columns use `decimal(12, 2)`. Foreign keys use cascade or null-on-delete as noted. Enum values are stored as strings and cast to PHP enums in models.

---

## Reference Tables

### `labs`

**Purpose:** Dental laboratories that produce crowns, implants, and other lab work.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | Display name |
| `code` | string unique | e.g. `MAIN_LAB`, `RIYADH_LAB` |
| `is_active` | boolean | Inactive labs are skipped |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `hasMany` lab_prices
- `hasMany` lab_jobs

**Example data:**

| id | name | code | is_active |
|---|---|---|---|
| 1 | Main Lab | MAIN_LAB | true |
| 2 | Riyadh Lab | RIYADH_LAB | true |

---

### `doctors`

**Purpose:** Stores doctor commission settings and default lab assignment.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | Display name |
| `code` | string unique | e.g. `JACK`, `RIYAD`, `PURIYA`, `WA` |
| `commission_type` | string | `percentage` or `fixed` |
| `commission_percentage` | decimal(5,2) nullable | Used only for percentage doctors |
| `default_lab_id` | FK → labs nullable | Doctor's preferred lab |
| `is_active` | boolean | |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` defaultLab (labs)
- `hasMany` daily_work_rows
- `hasMany` lab_prices
- `hasMany` doctor_fixed_fees

**Important business rules:**

- Percentage doctors: `DOCTOR_INCOME = NET_TOTAL × commission_percentage / 100`
- Fixed doctors: income from `doctor_fixed_fees × quantity`; percentage field is ignored
- Never match doctors by name in code — always use `id` or `code`

**Example data:**

| code | name | commission_type | commission_percentage | default_lab |
|---|---|---|---|---|
| JACK | Dr Jack | percentage | 35.00 | MAIN_LAB |
| RIYAD | Dr Riyad | percentage | 35.00 | RIYADH_LAB |
| PURIYA | Dr Puriya | percentage | 25.00 | MAIN_LAB |
| WA | Dr Wa | fixed | null | MAIN_LAB |

**Commission rules:**

- **Percentage doctors (Jack, Riyad, Puriya):** lab cost deducted from collected total before commission (`NET_TOTAL × %`)
- **Fixed doctor (Wa):** income from completed procedures only (IMPL, BG, SINUS)

---

### `treatments`

**Purpose:** Catalog of procedure codes parsed from treatment text.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `code` | string unique | e.g. `ZIR`, `IMPL-CR`, `BG` |
| `name` | string | Full name |
| `has_lab_cost` | boolean | If false, no lab_job is created |
| `is_active` | boolean | |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `hasMany` work_items
- `hasMany` lab_prices
- `hasMany` doctor_fixed_fees

**Example data:**

| code | name | has_lab_cost |
|---|---|---|
| ZIR | Zircon Crown | true |
| MC | Metal Ceramic Crown | true |
| CF | Composite Filling | false |
| BG | Bone Graft | false |

---

### `lab_prices`

**Purpose:** Unit cost per treatment per lab, with optional doctor-specific overrides.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `lab_id` | FK → labs | Required |
| `treatment_id` | FK → treatments | Required |
| `doctor_id` | FK → doctors nullable | `null` = default price for all doctors |
| `unit_cost` | decimal(12,2) | |
| `currency` | string(3) | Default `AED` |
| `valid_from` | date nullable | Price effective start |
| `valid_to` | date nullable | Price effective end |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` lab
- `belongsTo` treatment
- `belongsTo` doctor (nullable)

**Price resolution order:**

1. Row where `doctor_id` = row doctor AND `lab_id` = resolved lab
2. Row where `doctor_id IS NULL` AND `lab_id` = resolved lab

**Example data:**

| treatment | lab | doctor_id | unit_cost |
|---|---|---|---|
| MC | MAIN_LAB | null | 105.00 |
| ZIR | MAIN_LAB | null | 360.00 |
| ZIR | RIYADH_LAB | RIYAD | 400.00 |
| IMPL-ZIR | MAIN_LAB | null | 460.00 |
| IMPL-ZIR | RIYADH_LAB | RIYAD | 500.00 |

---

### `doctor_fixed_fees`

**Purpose:** Fixed per-procedure fees for doctors with `commission_type = fixed`.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `doctor_id` | FK → doctors | |
| `treatment_id` | FK → treatments | |
| `fee_amount` | decimal(12,2) | |
| `currency` | string(3) | Default `AED`; may be `USD` |
| `valid_from` | date nullable | |
| `valid_to` | date nullable | |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` doctor
- `belongsTo` treatment

**Unique constraint:** `(doctor_id, treatment_id)`

**Example data (Dr Wa):**

| treatment | fee_amount | currency |
|---|---|---|
| IMPL | 500.00 | AED |
| BG | 300.00 | USD |
| SINUS | 200.00 | USD |

---

## Transaction Tables

### `daily_reports`

**Purpose:** One imported or manually entered daily accounting report.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `report_date` | date | Business date of the report |
| `source_type` | string | `excel_upload` or `manual_entry` |
| `source_file_name` | string nullable | Original Excel filename |
| `status` | string | See status enum below |
| `created_at`, `updated_at` | timestamps | |

**Status values:** `uploaded`, `parsed`, `calculated`, `approved`, `failed`

**Relationships:**

- `hasMany` daily_work_rows

**Example data:**

| id | report_date | source_type | status |
|---|---|---|---|
| 1 | 2026-01-15 | excel_upload | calculated |

---

### `daily_work_rows`

**Purpose:** One accounting row from the daily report (one patient visit / payment line).

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `daily_report_id` | FK → daily_reports | |
| `doctor_id` | FK → doctors | |
| `work_date` | date | Date work was performed |
| `patient_name` | string nullable | Accounting traceability only |
| `mrn` | string nullable | Medical record number |
| `file_number` | string nullable | Clinic file number |
| `treatment_text` | text nullable | Raw text parsed into work_items |
| `total_cost` | decimal(12,2) | Treatment value (not used for TOTAL) |
| `discount_amount` | decimal(12,2) | |
| `dhs_amount` | decimal(12,2) | Cash AED collected |
| `usd_amount` | decimal(12,2) | Cash USD collected |
| `usd_to_aed_amount` | decimal(12,2) | USD converted to AED |
| `visa_amount` | decimal(12,2) | Card payment in AED |
| `paid_total_aed` | decimal(12,2) | TOTAL collected (computed) |
| `balance_dhs` | decimal(12,2) | Outstanding DHS balance |
| `balance_usd` | decimal(12,2) | Outstanding USD balance |
| `crown_count` | integer | Crown count from Excel |
| `raw_data_json` | json nullable | Full original row for audit |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` dailyReport
- `belongsTo` doctor
- `hasMany` work_items
- `hasMany` payments

---

### `work_items`

**Purpose:** Parsed treatment items extracted from `treatment_text`.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `daily_work_row_id` | FK → daily_work_rows | |
| `treatment_id` | FK → treatments | |
| `quantity` | integer | Default 1 |
| `confidence` | integer | 100 = certain; lower = uncertain parse |
| `warning_message` | text nullable | e.g. quantity unclear |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` dailyWorkRow
- `belongsTo` treatment
- `hasOne` labJob

**Example data:**

| treatment | quantity | confidence |
|---|---|---|
| ZIR | 4 | 100 |
| POST | 2 | 100 |

---

### `lab_jobs`

**Purpose:** Calculated lab cost for one work item. Source of truth for LAB COST.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `work_item_id` | FK → work_items | |
| `lab_id` | FK → labs | Lab used for pricing |
| `lab_price_id` | FK → lab_prices nullable | Price record used |
| `quantity` | integer | Copied from work_item |
| `unit_cost` | decimal(12,2) | In AED |
| `total_cost_aed` | decimal(12,2) | quantity × unit_cost |
| `status` | string | `calculated`, `adjusted`, `cancelled` |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` workItem
- `belongsTo` lab
- `belongsTo` labPrice

---

### `payments`

**Purpose:** Individual payment records. Source of truth for TOTAL.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `daily_work_row_id` | FK → daily_work_rows | |
| `payment_method` | string | `dhs`, `usd`, `visa` |
| `amount` | decimal(12,2) | Original amount |
| `currency` | string(3) | `AED` or `USD` |
| `exchange_rate` | decimal(12,4) | 1 for AED; 3.65 for USD |
| `amount_aed` | decimal(12,2) | Normalized AED amount |
| `paid_at` | date | Payment date |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` dailyWorkRow

---

## System Tables

### `users`

Extended with `role` column: `admin`, `accountant`, `viewer`.

### `audit_logs`

**Purpose:** Audit trail for sensitive actions.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK → users nullable | |
| `action` | string | e.g. `report_import`, `price_change` |
| `auditable_type` | string nullable | Polymorphic model class |
| `auditable_id` | bigint nullable | |
| `old_values` | json nullable | |
| `new_values` | json nullable | |
| `ip_address` | string nullable | |
| `created_at`, `updated_at` | timestamps | |

### `personal_access_tokens`

Laravel Sanctum API tokens for authentication.

---

## Entity Relationship Diagram

```
labs ──────────────┬──────────── lab_prices ──── treatments
  │                │                              │
  │                └──── doctors ─────────────────┤
  │                         │                     │
  │                         │                     │
daily_reports ── daily_work_rows ── work_items ────┘
                      │    │            │
                      │    │            └── lab_jobs ── labs
                      │    │
                      │    └── payments
                      │
                      └── doctors

doctor_fixed_fees ── doctors + treatments
```

---

## What Changed

**Updated — 2026-06-19**

- MC lab price: 105 AED
- Doctor code `RIYADH` → `RIYAD`, name Dr Riyad
- Commission rules clarified per doctor type

**Initial documentation — 2026-06-19**

Created:

- `docs/DATABASE_SCHEMA.md` — all 12 application tables plus users/audit_logs

Migrations:

- `2026_06_19_000001` through `2026_06_19_000012`
