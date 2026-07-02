# Database Schema

All money columns use `decimal(12, 2)`. Foreign keys use cascade or null-on-delete as noted. Enum values are stored as strings and cast to PHP enums in models.

---

## Supported database engines (ADR-035)

| Environment | Engine | File / connection |
|---|---|---|
| Local development | SQLite | `database/database.sqlite` |
| Automated tests | SQLite | `database/testing.sqlite` (isolated — see `phpunit.xml`) |
| Production | PostgreSQL | `DB_CONNECTION=pgsql` in `.env` |

Business data is identical across engines — one shared schema via Laravel migrations. Production deployment copies existing SQLite customer data with `php artisan app:migrate-sqlite-to-pgsql` (never deletes the source file).

**Before any migration:** backup SQLite and PostgreSQL:

```bash
cp database/database.sqlite database/database.sqlite.backup-$(date +%Y%m%d-%H%M%S)
pg_dump clinic_accounting > backup.sql
```

---

## Reference Tables

### `clinics`

**Purpose:** Root tenant entity for independent clinic accounting (ADR-026).

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | Display name |
| `code` | string unique | e.g. `CLINIC_111` |
| `currency` | string(3) | ISO base currency code (ADR-034). Supported: AED, EUR, USD, SAR, GBP. Symbol and precision resolved from `config/currencies.php` at runtime — not stored on the row. |
| `timezone` | string(64) | IANA timezone, default `Asia/Dubai` |
| `country` | string | Country name |
| `is_active` | boolean | Inactive clinics reserved for future tenant scoping |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `hasMany` users, doctors, labs, treatments, lab_prices, doctor_fixed_fees, nurses, nurse_commission_rates, nurse_commissions

**Example data (seeded):**

| id | name | code | currency | timezone | country | is_active |
|---|---|---|---|---|---|---|
| 1 | Clinic 111 | CLINIC_111 | AED | Asia/Dubai | United Arab Emirates | true |

**Admin rules (Milestone 06):**

- Managed at web `/clinics` and API `/api/admin/clinics` (admin only)
- Never physically deleted — use `is_active = false`
- `is_active` is not mass-assignable on the model; set via `ClinicManagementService`
- Configuration models belong to a clinic via `clinic_id` (Milestone 07)
- Accounting and transactional tables (`daily_reports`, `daily_work_rows`, `payments`, `work_items`, `lab_jobs`, `nurse_commissions`, `audit_logs`) own `clinic_id` (Milestone 10, ADR-029)

**Onboarding rules (Milestone 11, ADR-030):**

- New clinics are created via `ClinicOnboardingService` (public `/register-clinic` or `POST /api/register-clinic`)
- Onboarding creates clinic + owner/admin user + one default lab (`{CLINIC_CODE}_MAIN_LAB`) inside a transaction
- No doctors, treatments, lab prices, fixed fees, or accounting records during onboarding

---

### `labs`

**Purpose:** Dental laboratories that produce crowns, implants, and other lab work.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `clinic_id` | FK → clinics | Required; scoped to owning clinic (Milestone 07) |
| `name` | string | Display name |
| `code` | string unique | e.g. `MAIN_LAB`, `RIYADH_LAB` |
| `is_active` | boolean | Inactive labs are skipped |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` clinic
- `hasMany` lab_prices
- `hasMany` lab_jobs

**Example data:**

| id | name | code | is_active |
|---|---|---|---|
| 1 | Main Lab | MAIN_LAB | true |
| 2 | Riyadh Lab | RIYADH_LAB | true |

**Admin rules (Milestone 01):**

- Managed at web `/labs` and API `/api/admin/labs` (admin only)
- Never physically deleted — use `is_active = false`
- Inactive labs are excluded from new calculations but remain on historical `lab_jobs`
- `is_active` is not mass-assignable on the model; set via `LabManagementService`

---

### `doctors`

**Purpose:** Stores doctor commission settings and default lab assignment.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `clinic_id` | FK → clinics | Required; scoped to owning clinic (Milestone 07) |
| `name` | string | Display name |
| `code` | string unique | e.g. `JACK`, `RIYAD`, `PURIYA`, `WA` |
| `commission_type` | string | `percentage` or `fixed` |
| `commission_percentage` | decimal(5,2) nullable | Used only for percentage doctors |
| `default_lab_id` | FK → labs nullable | Doctor's preferred lab |
| `is_active` | boolean | |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` clinic
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
| `clinic_id` | FK → clinics | Required; scoped to owning clinic (Milestone 07) |
| `code` | string unique | e.g. `ZIR`, `IMPL-CR`, `BG` |
| `name` | string | Full name |
| `description` | text nullable | Optional admin notes |
| `has_lab_cost` | boolean | If false, no lab_job is created |
| `requires_nurse_commission` | boolean | Default `false`; if true, work item may receive nurse commission snapshot |
| `treatment_price` | decimal(12,2) nullable | Patient/list price for the treatment (ADR-039); not lab cost |
| `treatment_price_currency` | char(3) nullable | ISO 4217 currency of `treatment_price`; no automatic default |
| `is_active` | boolean | |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` clinic
- `hasMany` work_items
- `hasMany` lab_prices
- `hasMany` doctor_fixed_fees
- `hasMany` nurse_commission_rates
- `hasMany` nurse_commissions

**Price semantics (ADR-039):**

- **Treatment price** (`treatment_price` + `treatment_price_currency`) — patient/list price configured on the treatment; basis for nurse commission when `requires_nurse_commission = true`
- **Lab price** (`lab_prices.unit_cost`) — external laboratory unit cost; used only when `has_lab_cost = true`; must not be used as patient price or nurse-commission basis

Nullable treatment price fields allow existing treatments without immediate pricing. When `requires_nurse_commission = true`, editor validation requires treatment price, active nurse, and active commission rate before save.

**Admin rules (Milestone 02):**

- Managed at web `/treatments` and API `/api/admin/treatments` (admin only)
- Never physically deleted — use `is_active = false`
- Inactive treatments excluded from parser known codes and editor catalog; historical `work_items` unchanged
- `has_lab_cost` and `is_active` set via `TreatmentManagementService`, not mass-assignable

**Example data:**

| code | name | has_lab_cost |
|---|---|---|
| ZIR | Zircon Crown | true |
| MC | Metal Ceramic Crown | true |
| CF | Composite Filling | false |
| BG | Bone Graft | false |

---

### `nurses`

**Purpose:** X-ray nurse master data for commission accounting (ADR-039). No application login in v1.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `clinic_id` | FK → clinics | Required; scoped to owning clinic |
| `code` | string | Unique per clinic |
| `name` | string | Display name |
| `is_active` | boolean | Default `true`; soft deactivate only |
| `created_at`, `updated_at` | timestamps | |

**Indexes:** unique `(clinic_id, code)`; index `(clinic_id, is_active)`

**Relationships:**

- `belongsTo` clinic
- `hasMany` nurse_commission_rates
- `hasMany` nurse_commissions

**Business rules:**

- Never physically deleted — use `is_active = false`
- Same `code` may exist in different clinics

---

### `nurse_commission_rates`

**Purpose:** Commission percentage for a nurse + treatment pair.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `clinic_id` | FK → clinics | Required |
| `nurse_id` | FK → nurses | Required |
| `treatment_id` | FK → treatments | Required |
| `commission_percentage` | decimal(5,2) | Validated 0.01–100.00 in management layer (future) |
| `is_active` | boolean | Default `true`; soft deactivate only |
| `created_at`, `updated_at` | timestamps | |

**Indexes:** `(clinic_id, nurse_id, treatment_id, is_active)` — at most one active row per clinic + nurse + treatment enforced in application layer

**Relationships:**

- `belongsTo` clinic
- `belongsTo` nurse
- `belongsTo` treatment

**Business rules:** mirrors `doctor_fixed_fees` lifecycle — multiple inactive rows allowed; no physical delete.

---

### `nurse_commissions`

**Purpose:** Historical 1:1 nurse commission snapshot per work item. Source of truth for nurse commission accounting in AED.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `clinic_id` | FK → clinics | Required; immutable after create (ADR-029) |
| `work_item_id` | FK → work_items unique | 1:1 with eligible work item; cascade delete with work item |
| `nurse_id` | FK → nurses | Required; restrict on delete (preserves history) |
| `nurse_name_snapshot` | string | Copied at snapshot time |
| `treatment_id` | FK → treatments | Required; restrict on delete |
| `treatment_code_snapshot` | string | Copied at snapshot time |
| `treatment_name_snapshot` | string | Copied at snapshot time |
| `treatment_price_original` | decimal(12,2) | Price in original currency |
| `treatment_price_currency` | char(3) | Original currency code |
| `exchange_rate_to_aed` | decimal(12,4) | FX rate used at snapshot |
| `treatment_price_aed` | decimal(12,2) | Normalized unit price in AED |
| `commission_percentage` | decimal(5,2) | Rate at snapshot time |
| `unit_commission_aed` | decimal(12,2) | Per-unit commission in AED |
| `quantity` | unsigned integer | From work item |
| `total_commission_aed` | decimal(12,2) | unit_commission_aed × quantity |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` clinic
- `belongsTo` workItem
- `belongsTo` nurse
- `belongsTo` treatment

**Delete rules:**

- Deleting an editable work item removes its snapshot (`work_item_id` cascade)
- Hard delete of nurse or treatment blocked when snapshots reference them

---

### `lab_prices`

**Purpose:** Unit cost per treatment per lab, with optional doctor-specific overrides.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `clinic_id` | FK → clinics | Required; scoped to owning clinic (Milestone 07) |
| `lab_id` | FK → labs | Required |
| `treatment_id` | FK → treatments | Required |
| `doctor_id` | FK → doctors nullable | `null` = default price for all doctors |
| `unit_cost` | decimal(12,2) | |
| `currency` | string(3) | Default `AED` |
| `valid_from` | date nullable | Price effective start |
| `valid_to` | date nullable | Price effective end |
| `is_active` | boolean | Default `true`; soft deactivate instead of delete |
| `created_at`, `updated_at` | timestamps | |

**Admin rules (Milestone 03):**

- Never physically delete price rows
- Only one **active** price per lab + treatment + doctor scope + overlapping validity period
- Inactive prices remain in DB for historical `lab_jobs.lab_price_id` references
- General price: `doctor_id IS NULL`; doctor override: `doctor_id` set

**Relationships:**

- `belongsTo` clinic
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
| `clinic_id` | FK → clinics | Required; scoped to owning clinic (Milestone 07) |
| `doctor_id` | FK → doctors | |
| `treatment_id` | FK → treatments | |
| `fee_amount` | decimal(12,2) | Must be > 0 |
| `currency` | string(3) | Default `AED`; may be `USD` |
| `valid_from` | date nullable | |
| `valid_to` | date nullable | |
| `is_active` | boolean | Default `true`; soft deactivate only |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` clinic
- `belongsTo` doctor
- `belongsTo` treatment

**Business rules:**

- Only doctors with `commission_type = fixed` use these rows
- Only one **active** fee per doctor + treatment + overlapping validity period
- Multiple inactive or non-overlapping rows allowed (duplicate workflow)
- No physical delete — historical accounting references remain valid

**Indexes:** `doctor_id`, `treatment_id` (FK indexes)

**Example data (Dr Wa):**

| treatment | fee_amount | currency |
|---|---|---|
| IMPL | 500.00 | AED |
| BG | 200.00 | USD |
| SINUS | 300.00 | USD |

---

### `doctor_lab_billings`

**Purpose:** Per-doctor rule whether a lab-cost treatment generates JOB (lab deduction).

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `doctor_id` | FK → doctors | |
| `treatment_id` | FK → treatments | |
| `bill_lab_job` | boolean | `true` = create `lab_job` and deduct from NET |
| `created_at`, `updated_at` | timestamps | |

**Unique constraint:** `(doctor_id, treatment_id)`

**Seeded rules:**

| doctor | bill_lab_job |
|---|---|
| JACK, RIYAD | All `has_lab_cost` treatments |
| PURIYA | MC, ZIR, POST, REMOV only |
| WA | No rows — no lab jobs |

---

## Transaction Tables

### `daily_reports`

**Purpose:** One imported or manually entered daily accounting report.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `clinic_id` | FK → clinics | Required; scoped to owning clinic (Milestone 10) |
| `report_date` | date | Business date of the report |
| `source_type` | string | `excel_upload` or `manual_entry` |
| `source_file_name` | string nullable | Original Excel filename |
| `status` | string | See status enum below |
| `created_at`, `updated_at` | timestamps | |

**Status values:** `uploaded`, `parsed`, `calculated`, `needs_review`, `approved`, `failed`

**Relationships:**

- `hasMany` daily_work_rows
- `hasMany` import_warnings (`daily_report_import_warnings`)

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
| `clinic_id` | FK → clinics | Required; inherits from parent report (Milestone 10) |
| `daily_report_id` | FK → daily_reports | |
| `doctor_id` | FK → doctors | |
| `work_date` | date | Date work was performed |
| `patient_reference_hash` | string(64) nullable | HMAC-SHA256 of name\|mrn\|file — no plain-text PII |
| `excel_row_number` | unsigned int nullable | Source Excel row for traceability |
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
| `raw_data_json` | json nullable | Sanitized parsed row (PII keys redacted) |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` dailyReport
- `belongsTo` doctor
- `hasMany` work_items
- `hasMany` payments

---

### `work_items`

**Purpose:** Parsed treatment items extracted from `treatment_text`. Created for **every valid known treatment code**, not only lab-cost codes.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `clinic_id` | FK → clinics | Required; inherits from parent work row (Milestone 10) |
| `daily_work_row_id` | FK → daily_work_rows | |
| `treatment_id` | FK → treatments | |
| `nurse_id` | FK → nurses nullable | Selected nurse for commission-eligible treatments (editor/import) |
| `quantity` | integer | Default 1 |
| `confidence` | integer | 100 = certain; lower = inferred from tooth notation |
| `warning_message` | text nullable | Parser note (e.g. quantity inferred) |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` dailyWorkRow
- `belongsTo` treatment
- `belongsTo` nurse (nullable)
- `hasOne` labJob (only when treatment `has_lab_cost = true` and price resolved)
- `hasOne` nurseCommission (only when treatment `requires_nurse_commission = true` and snapshot created)

**Example data:**

| treatment | quantity | confidence |
|---|---|---|
| ZIR | 4 | 100 |
| POST | 2 | 100 |

---

### `daily_report_import_warnings`

**Purpose:** Parser and lab-pricing warnings from import. Drives `needs_review` status.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `daily_report_id` | FK → daily_reports | |
| `daily_work_row_id` | FK nullable → daily_work_rows | |
| `excel_row_number` | unsigned int nullable | Source row |
| `doctor_code` | string nullable | Doctor display name/code for review UI |
| `treatment_text` | text nullable | Full row treatment text |
| `warning_code` | string | `invalid_format`, `missing_quantity`, `unknown_treatment_code`, `lab_price_not_found` |
| `message` | text | Human-readable message |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` dailyReport
- `belongsTo` dailyWorkRow (nullable)

---

### `lab_jobs`

**Purpose:** Calculated lab cost for one work item. Source of truth for LAB COST.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `clinic_id` | FK → clinics | Required; inherits from parent work item (Milestone 10) |
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
| `clinic_id` | FK → clinics | Required; inherits from parent work row (Milestone 10) |
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

**Purpose:** Application login accounts for web session and Sanctum API tokens.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `clinic_id` | FK → clinics | Required; belongs to clinic (Milestone 07); not yet used at login |
| `name` | string | Display name |
| `email` | string unique | Login identifier |
| `email_verified_at` | timestamp nullable | |
| `password` | string | Bcrypt hash — never mass-assignable |
| `role` | string | `admin`, `accountant`, `viewer` |
| `is_active` | boolean | `false` = deactivated (never physically deleted) |
| `remember_token` | string nullable | Web sessions |
| `created_at`, `updated_at` | timestamps | |

**Relationships:**

- `belongsTo` clinic
- `hasMany` audit_logs (as actor via `user_id`)
- Sanctum `personal_access_tokens`

**Business rules:**

- Deactivated users cannot log in (web or API)
- Admin manages users at `/admin/users` — soft deactivate only
- Password and `is_active` are set only via `UserManagementService`, not `$fillable`

**Default seed users:**

| email | role | password |
|---|---|---|
| admin@clinic.test | admin | password |
| accountant@clinic.test | accountant | password |
| viewer@clinic.test | viewer | password |

### `audit_logs`

**Purpose:** Audit trail for sensitive actions.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `clinic_id` | FK → clinics | Required; from auditable model or current clinic (Milestone 10) |
| `user_id` | FK → users nullable | |
| `action` | string | e.g. `report_import`, `price_change` |
| `auditable_type` | string nullable | Polymorphic model class |
| `auditable_id` | bigint nullable | |
| `old_values` | json nullable | |
| `new_values` | json nullable | |
| `ip_address` | string nullable | |
| `user_agent` | text nullable | |
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
      │                │    │            ├── lab_jobs ── labs
      │                │    │            └── nurse_commissions ── nurses
      │                │    │
      │                │    └── payments
      │                │
      │                └── doctors
      │
      └── daily_report_import_warnings

doctor_fixed_fees ── doctors + treatments
nurses ── nurse_commission_rates ── treatments
```

---

## What Changed

**Updated — 2026-07-02**

- Treatment price moved to `treatments.treatment_price` and `treatments.treatment_price_currency` (nullable; ADR-039 correction)
- Obsolete `treatment_prices` table removed when empty (migration `2026_07_02_000001_move_treatment_price_to_treatments_table`)

**Updated — 2026-06-28**

- Nurse commission schema (ADR-039): `nurses`, `nurse_commission_rates`, `nurse_commissions`
- `treatments.requires_nurse_commission` boolean (default `false`)

**Updated — 2026-06-27**

- `clinic_id` on accounting tables: `daily_reports`, `daily_work_rows`, `payments`, `work_items`, `lab_jobs`, `nurse_commissions`, `audit_logs` (Milestone 10, ADR-029)

**Updated — 2026-06-26**

- `clinic_id` on configuration tables: users, doctors, labs, treatments, lab_prices, doctor_fixed_fees (Milestone 07, ADR-026)

**Updated — 2026-06-25**

- `labs` admin rules documented (soft deactivate, Milestone 01)

**Updated — 2026-06-26**

- `lab_prices.is_active` admin rules and overlap validation (Milestone 03 — Lab Price Administration)
- `treatments.description` column and admin rules (Milestone 02)

**Updated — 2026-06-25**

- `users.is_active` for soft deactivation
- `audit_logs.user_agent` column
- User admin documented with role management rules

**Updated — 2026-06-21 (privacy + validation)**

- `daily_work_rows`: `patient_reference_hash`, `excel_row_number`; PII columns removed
- `daily_report_import_warnings` table
- `needs_review` report status
- `work_items` documented for all valid treatments

**Updated — 2026-06-19**

- MC lab price: 105 AED
- Doctor code `RIYADH` → `RIYAD`, name Dr Riyad
- Commission rules clarified per doctor type

**Initial documentation — 2026-06-19**

Created:

- `docs/DATABASE_SCHEMA.md` — all 12 application tables plus users/audit_logs

Migrations:

- `2026_06_21_000001` through `2026_06_21_000014`
