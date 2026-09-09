# Workflows

Step-by-step descriptions of every major process in the system.

---

## 1. Daily Excel Import

### Overview

An accountant uploads a daily Excel file. The system extracts rows, applies privacy rules, validates treatments, calculates lab jobs, and sets report status — inside a single database transaction. The uploaded Excel file is deleted after success (configurable).

### Web UI (drag & drop — recommended)

1. Start server: `./bin/serve`
2. Login at `/login` with `accountant@clinic.test` / `password`
3. Drag Excel file onto the import page (or click to browse)
4. Report month is resolved from the filename (e.g. `daily report january 2026.xlsm`)
5. After import, view results at `/imports/{id}` and logs at `/logs`
6. If parser warnings exist, status is `needs_review` — check `GET /api/daily-reports/{id}/validation-summary`

### Treatment text rules

Staff who fill the daily Excel must use **`CODE x QUANTITY`** in **UPPERCASE** (e.g. `ZIR x 2 + POST x 1`).  
See **`docs/TREATMENT_RULES.md`** and web page **`/docs/treatment-rules`**.

Invalid lines produce warnings (not silent skips): unknown code, missing quantity, invalid format, missing lab price.

### Steps (API / curl)

1. **User authenticates** — Sanctum token via `POST /api/login`.
2. **User uploads Excel** — `POST /api/daily-reports/import` with `multipart/form-data`.
3. **Validation** — file type (`.xlsx`, `.xlsm`), size limit.
4. **Approved report guard** — rejected if an approved report exists for the same month anchor.
5. **File storage** — saved to private disk (`storage/app/daily-reports/`).
6. **`DailyReportImportService::import()` begins DB transaction.**
7. **Create `daily_report`** — status = `uploaded`.
8. **`ExcelDailyReportParser`** — reads day sheets 1–31, maps columns, extracts rows. Patient name/MRN/file read **in memory only**.
9. **For each parsed row:**
   - Resolve doctor by code or name.
   - Compute `paid_total_aed` via `PaymentCalculationService`.
   - Hash patient identifiers → `patient_reference_hash` (HMAC-SHA256, dedicated env key).
   - Sanitize PII from `raw_data_json`.
   - Store `excel_row_number` for traceability.
   - Create `daily_work_row` + `payments`.
10. **Update status** → `parsed`.
11. **`TreatmentImportValidationService::validateAndPersist()`** — per row:
    - Split `treatment_text` on `+` (and ` | ` patient segments).
    - Emit warnings for invalid format / unknown code / missing quantity.
    - Create **`work_items` for all valid known treatments** (CF, REMOV, ZIR, …).
12. **`LabJobCalculationService::calculateForReport()`** — for work items where `has_lab_cost = true`, resolve price → create `lab_jobs`. Non-lab treatments (CF, RCT, …) skip lab job.
13. **Collect lab-price warnings** — work items with lab cost but no matching `lab_price`.
14. **Persist warnings** → `daily_report_import_warnings`.
15. **Reconciliation** — `IncomeReconciliationService::validateReport()`.
16. **Final status** → `calculated` if no warnings, else `needs_review`.
17. **Extraction log** — structured import log written.
18. **Transaction commits** — return report JSON (no patient names).
19. **Delete uploaded Excel** — when `ACCOUNTING_DELETE_UPLOAD_AFTER_IMPORT=true` (default).
20. **On failure** — rollback, status = `failed`.

### Sequence Diagram

```mermaid
sequenceDiagram
    actor User
    participant API as DailyReportController
    participant Import as DailyReportImportService
    participant Excel as ExcelDailyReportParser
    participant Privacy as PatientReferenceHasher
    participant Pay as PaymentCalculationService
    participant Valid as TreatmentImportValidationService
    participant Lab as LabJobCalculationService
    participant DB as Database

    User->>API: POST /api/daily-reports/import
    API->>Import: import(file)
    Import->>DB: BEGIN TRANSACTION
    Import->>DB: CREATE daily_report (uploaded)
    Import->>Excel: parseWithDiagnostics(path)
    Excel-->>Import: parsed rows[]

    loop Each row
        Import->>Privacy: hash(name, mrn, file) in memory
        Import->>DB: CREATE daily_work_row (no PII)
        Import->>Pay: calculateTotalCollectedAed()
        Import->>Pay: createPaymentsForWorkRow()
    end

    Import->>DB: UPDATE status = parsed
    loop Each row
        Import->>Valid: validateAndPersist(row)
        Valid->>DB: CREATE work_items (all valid codes)
    end
    Import->>Lab: calculateForReport()
    Lab->>DB: CREATE lab_jobs (has_lab_cost only)
    Import->>DB: CREATE import_warnings
    Import->>DB: UPDATE status = calculated | needs_review
    Import->>DB: COMMIT
    Import->>Import: delete uploaded file
    Import-->>API: DailyReport
    API-->>User: 201 JSON
```

### Data Flow Diagram

```
Excel file (deleted after import)
    ↓
ExcelDailyReportParser     ← extract only (doctor, amounts, treatment_text, row#)
    ↓                        patient fields: memory only → HMAC hash
daily_reports              (1 per import)
    ↓
daily_work_rows            (patient_reference_hash, excel_row_number, sanitized raw_data_json)
    ↓                    ↓
payments               treatment_text
(collected $)              ↓
              TreatmentImportValidationService
              (warnings → daily_report_import_warnings)
                            ↓
                       work_items          ← ALL valid treatments
                       (CF, REMOV, ZIR, …)
                            ↓
                       lab_jobs            ← ONLY has_lab_cost = true
                       (MC, ZIR, REMOV, …)
                            ↓
                    monthly_income / Income Excel export
```

---

## 2. Daily Report Review

### Steps

1. `GET /api/daily-reports/{id}` — full report with work items and lab jobs (no patient names).
2. `GET /api/daily-reports/{id}/validation-summary` — parser warning counts and messages.
3. Web UI: `/imports/{id}` for staff review.

Loaded relations:

- `dailyWorkRows.doctor`
- `dailyWorkRows.payments`
- `dailyWorkRows.workItems.treatment`
- `dailyWorkRows.workItems.labJob.lab`

---

## 3. Treatment Parsing vs Import Validation

Two layers — do not confuse them.

### A. Extractor (`ExcelDailyReportParser`)

Reads Excel cells. Does **not** parse treatment codes. Output includes `treatment_text` as raw string plus payment columns and `raw_row_number`.

### B. Parser (`TreatmentParserService::parse()`)

Pure function: `treatment_text` → `ParsedTreatmentItemDto[]`.

- Splits on ` | ` (patient segments) and `+` (multiple procedures).
- Longest code match first (`IMPL-ZIR` before `IMPL`).
- Supports tooth notation (`CF 876`, `MC CR 8765|5678`).
- Used by validation and unit tests; does not write to DB alone during import.

### C. Import validation (`TreatmentImportValidationService`)

Runs during import after rows are saved.

| Check | Warning code |
|---|---|
| Unknown treatment code | `unknown_treatment_code` |
| Missing quantity | `missing_quantity` |
| Invalid format (e.g. `zircon 2`) | `invalid_format` |
| Lab cost but no lab price | `lab_price_not_found` |

**Persistence rule:**

```
valid known treatment  →  work_item  (always)
has_lab_cost = true    →  lab_job    (via LabJobCalculationService)
has_lab_cost = false   →  no lab_job (CF, AF, RCT, RE-RCT, REPAIR, …)
```

**Example:**

```
Input:  "ZIR x 2 + CF x 3 + zircon 2"
Output: work_items: ZIR×2, CF×3
        warning: invalid_format on "zircon 2"
        lab_jobs: ZIR×2 only (CF has no lab cost)
        status: needs_review
```

---

## 4. Lab Job Calculation

Runs automatically during import after work items are created.

### Steps

1. Load all work items for the report.
2. For each work item:
   - Skip if `treatment.has_lab_cost = false`.
   - Resolve lab via doctor's `default_lab_id`.
   - Resolve unit price via `LabPriceResolver`.
   - `total_cost_aed = quantity × unit_cost`.
   - Create `lab_job` with status `calculated`.

### Example

```
REMOV x 2  →  lab_job: 2 × 100 = 200 AED JOB
CF x 3     →  work_item only, no lab_job
ZIR x 4 Dr Riyad  →  lab_job: 4 × 400 = 1600 AED
```

---

## 5. Monthly Income Calculation

On-demand via `GET /api/monthly-income?month=YYYY-MM`.

Same as before — aggregates `payments`, `lab_jobs`, and `work_items` per doctor for the calendar month.

---

## 6. Authentication

Sanctum bearer tokens for API; session login for web.

**Self-service password reset (ADR-040, web only):**

```mermaid
sequenceDiagram
    actor Guest
    participant Login as /login
    participant Forgot as /forgot-password
    participant Mail as Resend
    participant Reset as /reset-password
    participant Audit as audit_logs

    Guest->>Login: Forgot password?
    Guest->>Forgot: Submit email
    Forgot->>Mail: Reset link if active user
    Forgot->>Audit: password_reset_requested
    Forgot-->>Guest: Generic success message
    Guest->>Reset: New password via token
    Reset->>Audit: password_reset
    Reset-->>Guest: Redirect to login
```

Admin password reset via `/admin/users` is unchanged.

---

## 7. Role-Based Access

| Action | admin | accountant | viewer |
|---|---|---|---|
| Login | ✓ | ✓ | ✓ |
| List doctors/treatments/labs | ✓ | ✓ | ✓ |
| Import daily report | ✓ | ✓ | ✗ |
| View daily report | ✓ | ✓ | ✓ |
| View validation summary | ✓ | ✓ | ✓ |
| View monthly income | ✓ | ✓ | ✓ |
| Manage users (`/admin/users`) | ✓ | ✗ | ✗ |
| Manage laboratories (`/labs`) | ✓ | ✗ | ✗ |
| Manage treatments (`/treatments`) | ✓ | ✗ | ✗ |
| Manage lab prices (`/lab-prices`) | ✓ | ✗ | ✗ |
| Manage doctor fixed fees (`/doctor-fixed-fees`) | ✓ | ✗ | ✗ |
| Configuration dashboard (`/configuration`) | ✓ | ✗ | ✗ |
| Approve / unlock reports | ✓ | ✗ | ✗ |
| Manage doctors | ✓ | ✗ | ✗ |

---

## 8. Laboratory Administration (admin only)

```mermaid
sequenceDiagram
    actor Admin
    participant UI as /labs
    participant Svc as LabManagementService
    participant DB as labs + audit_logs

    Admin->>UI: Create lab
    UI->>Svc: create()
    Svc->>DB: INSERT labs
    Svc->>DB: audit lab_created

    Admin->>UI: Deactivate lab
    UI->>Svc: deactivate()
    Svc->>DB: is_active = false
    Svc->>DB: audit lab_deactivated

    Note over DB: Historical lab_jobs unchanged
```

**Rules:**

- Laboratories are never physically deleted
- `GET /api/labs` (reference) still returns active labs only for accountants/viewers
- Deactivated labs are excluded from new JOB calculations only

---

## 9. Treatment Administration (admin only)

```mermaid
sequenceDiagram
    actor Admin
    participant UI as /treatments
    participant Svc as TreatmentManagementService
    participant DB as treatments + audit_logs

    Admin->>UI: Create treatment
    UI->>Svc: create()
    Svc->>DB: INSERT treatments
    Svc->>DB: audit treatment_created

    Admin->>UI: Deactivate treatment
    UI->>Svc: deactivate()
    Svc->>DB: is_active = false
    Svc->>DB: audit treatment_deactivated

    Note over DB: Historical work_items unchanged
```

**Rules:**

- Treatments never physically deleted
- `LabCostTreatmentCatalog` reads `has_lab_cost` from database (no hardcoded code list)
- Parser and editor use active treatments only for new entries

---

## 10. Lab Price Administration (admin only)

```mermaid
sequenceDiagram
    actor Admin
    participant UI as /lab-prices
    participant Svc as LabPriceManagementService
    participant DB as lab_prices + audit_logs

    Admin->>UI: Create price (lab + treatment + optional doctor)
    UI->>Svc: create()
    Svc->>DB: overlap check
    Svc->>DB: INSERT lab_prices
    Svc->>DB: audit lab_price_created

    Admin->>UI: Duplicate price
    UI->>Svc: duplicate()
    Svc->>DB: INSERT inactive copy

    Admin->>UI: Deactivate price
    UI->>Svc: deactivate()
    Svc->>DB: is_active = false
    Svc->>DB: audit lab_price_deactivated

    Note over DB: Historical lab_jobs keep lab_price_id
```

**Rules:**

- Lab prices never physically deleted
- General price: `doctor_id IS NULL`; doctor override: `doctor_id` set
- Only one active price per lab + treatment + doctor + overlapping validity period
- `LabPriceResolver` unchanged — reads active rows with date validity
- Seed data in `LabPriceSeeder` is initial data only, not runtime logic

---

## 11. Doctor Fixed Fee Administration (admin only)

```mermaid
sequenceDiagram
    actor Admin
    participant UI as /doctor-fixed-fees
    participant Svc as DoctorFixedFeeManagementService
    participant DB as doctor_fixed_fees + audit_logs

    Admin->>UI: Create fee (fixed doctor + treatment)
    UI->>Svc: create()
    Svc->>DB: overlap check
    Svc->>DB: INSERT doctor_fixed_fees
    Svc->>DB: audit doctor_fixed_fee_created

    Admin->>UI: Duplicate fee
    UI->>Svc: duplicate()
    Svc->>DB: INSERT inactive copy

    Admin->>UI: Deactivate fee
    UI->>Svc: deactivate()
    Svc->>DB: is_active = false
    Svc->>DB: audit doctor_fixed_fee_deactivated

    Note over DB: Accounting engine unchanged; resolver reads active rows
```

**Rules:**

- Fixed fees never physically deleted
- Only doctors with `commission_type = fixed` may have rows
- Only one active fee per doctor + treatment + overlapping validity period
- `DoctorFixedFeeResolver` used by editor catalog; `WaelFixedFeeCalculator` and monthly income logic unchanged
- Seed data in `DoctorFixedFeeSeeder` is initial data only, not runtime logic

---

## 12. User Administration (admin only)

```mermaid
sequenceDiagram
    actor Admin
    participant UI as /admin/users
    participant Svc as UserManagementService
    participant DB as users + audit_logs

    Admin->>UI: Create user (password or temp)
    UI->>Svc: create()
    Svc->>DB: INSERT user
    Svc->>DB: audit user_created

    Admin->>UI: Change role
    UI->>Svc: update()
    Svc->>DB: UPDATE role
    Svc->>DB: audit user_role_changed

    Admin->>UI: Deactivate user
    UI->>Svc: deactivate()
    Svc->>DB: is_active = false
    Svc->>DB: revoke Sanctum tokens
    Svc->>DB: audit user_deactivated

    Admin->>UI: Reset password
    UI->>Svc: resetPassword()
    Svc->>DB: new password hash
    Svc->>DB: audit password_reset
```

**Rules:**

- Users are never physically deleted — `is_active = false`
- Deactivated users cannot log in (web session or API token)
- Admins cannot deactivate themselves or change their own role
- Temporary passwords are shown once in the success flash / API response

---

## 13. Configuration Dashboard (admin only)

```mermaid
sequenceDiagram
    actor Admin
    participant UI as /configuration
    participant Svc as ConfigurationDashboardService
    participant DB as config tables + audit_logs

    Admin->>UI: Open dashboard
    UI->>Svc: buildDashboard()
    Svc->>DB: COUNT modules (total/active/inactive)
    Svc->>DB: SELECT recent configuration audit_logs
    Svc->>DB: Run health checks (warnings only)
    Svc-->>UI: cards + activity + warnings
    Admin->>UI: Quick link to module admin page
```

**Rules:**

- Single entry point for all configuration modules (ADR-025)
- Health warnings are informational only — never auto-modify data
- Recent activity shows configuration-related audit actions only
- Accounting engine is not invoked from the dashboard
- Designed for future `clinic_id` filtering without schema changes in Milestone 05

---

## 14. Clinic Administration (admin only)

```mermaid
sequenceDiagram
    actor Admin
    participant UI as /clinics
    participant Svc as ClinicManagementService
    participant DB as clinics + audit_logs

    Admin->>UI: Create clinic
    UI->>Svc: create()
    Svc->>DB: INSERT clinics
    Svc->>DB: audit clinic_created

    Admin->>UI: Deactivate clinic
    UI->>Svc: deactivate()
    Svc->>DB: is_active = false
    Svc->>DB: audit clinic_deactivated

    Note over DB: Accounting and imports unchanged (ADR-026)
```

**Rules:**

- Clinics are never physically deleted
- `ClinicSeeder` creates exactly one default tenant: `CLINIC_111`
- No `clinic_id` on users, doctors, labs, or accounting tables yet
- Accounting engine, imports, and login do not use clinic context in Milestone 06

---

## 15. Configuration Clinic Ownership (Milestone 07)

All configuration models now store `clinic_id` pointing to `clinics.id`.

```mermaid
sequenceDiagram
    participant DB as configuration tables
    participant Clinic as CLINIC_111

    Note over DB: Migration backfills existing rows
    DB->>Clinic: users.clinic_id
    DB->>Clinic: doctors.clinic_id
    DB->>Clinic: labs.clinic_id
    DB->>Clinic: treatments.clinic_id
    DB->>Clinic: lab_prices.clinic_id
    DB->>Clinic: doctor_fixed_fees.clinic_id

    Note over DB: No query isolation yet
```

**Rules:**

- Every seeded configuration record belongs to `CLINIC_111`
- Seeders resolve clinic by code — never hardcode clinic IDs
- New configuration rows receive `clinic_id` from `CurrentClinicResolver` (Milestone 08, ADR-027)
- Admin CRUD, APIs, imports, and accounting behaviour unchanged until Milestone 09

---

## 16. Current Clinic Resolver (Milestone 08)

```mermaid
sequenceDiagram
    actor User
    participant Auth
    participant Svc as ConfigurationManagementService
    participant Resolver as CurrentClinicResolver
    participant DB as configuration tables

    User->>Auth: Authenticated request
    Svc->>Resolver: resolveId()
    Resolver->>Auth: user.clinic_id
    Resolver-->>Svc: clinic id
    Svc->>DB: INSERT with clinic_id
    Note over DB: Reads also filter by clinic_id (Milestone 09)
```

**Rules:**

- Single source of truth per request (ADR-027)
- No `CLINIC_111` fallback
- Controllers remain thin — services own clinic assignment and query scoping
- Accounting engine unchanged

---

## 17. Explicit Query Isolation (Milestone 09, ADR-028)

```mermaid
sequenceDiagram
    actor User
    participant Ctrl as AdminController
    participant Svc as ConfigurationService
    participant Resolver as CurrentClinicResolver
    participant DB as configuration tables

    User->>Ctrl: Authenticated list/show request
    Ctrl->>Svc: listQuery() / listForAdministration()
    Svc->>Resolver: resolveId()
    Resolver->>Resolver: auth user clinic_id
    Svc->>DB: SELECT ... WHERE clinic_id = ?
    DB-->>Ctrl: current clinic rows only
```

**Rules:**

- Every configuration read uses explicit `where('clinic_id', …)` in services — no global scopes
- Controllers never call `auth()->user()->clinic_id` or build tenant queries
- Cross-clinic route-model binding returns **404** on update/deactivate/activate
- Reference APIs (`/api/doctors`, `/api/treatments`, `/api/labs`) scoped via `ReferenceDataService`
- Configuration dashboard counts, health warnings, and recent activity are clinic-specific
- Accounting tables (`daily_reports`, `payments`, `lab_jobs`, …) are clinic-scoped via explicit service filtering (Milestone 10)

---

## 19. Accounting Ownership (Milestone 10, ADR-029)

```mermaid
sequenceDiagram
    actor User
    participant Ctrl as ImportController
    participant Import as DailyReportImportService
    participant Resolver as CurrentClinicResolver
    participant DB as accounting tables

    User->>Ctrl: Upload Excel / open report
    Ctrl->>Import: import() / processParsedReport()
    Import->>Resolver: resolveId()
    Import->>DB: INSERT daily_reports (clinic_id)
    Import->>DB: INSERT daily_work_rows, payments, work_items, lab_jobs (same clinic_id)
    Note over DB: All reads filter WHERE clinic_id = current clinic
```

**Rules:**

- Every accounting create assigns `clinic_id` from `CurrentClinicResolver` only
- Import pipeline: Report → Work Rows → Payments → Work Items → Lab Jobs (same tenant)
- Monthly income, exports, and editor mutations never mix clinics
- Cross-clinic report access returns **404** via `DailyReportQueryService::assertAccessible()`
- API response shapes unchanged — only filtering behaviour differs

---

## 20. Clinic Onboarding (Milestone 11, ADR-030)

```mermaid
sequenceDiagram
    actor Guest
    participant Web as ClinicOnboardingController
    participant Req as RegisterClinicRequest
    participant Svc as ClinicOnboardingService
    participant DB as Database

    Guest->>Web: POST /register-clinic
    Web->>Req: validate clinic + owner fields
    Web->>Svc: register(validated)
    Svc->>DB: BEGIN TRANSACTION
    Svc->>DB: INSERT clinic (is_active=true)
    Svc->>DB: INSERT owner user (role=admin, clinic_id)
    Svc->>DB: INSERT default lab ({CODE}_MAIN_LAB)
    Svc->>DB: COMMIT
    Web->>Web: Auth::login(owner)
    Web->>Guest: Redirect /configuration
```

**Rules:**

- Public onboarding — no authentication required (guest-only POST)
- Transaction is atomic — clinic without owner is forbidden
- No doctors, treatments, lab prices, fixed fees, or accounting records during onboarding
- Owner role, `clinic_id`, and `is_active` are never accepted from the client
- After onboarding, owner sees only their clinic via existing query isolation (ADR-028)
- API equivalent: `POST /api/register-clinic` returns Sanctum token (201)
- Rate limited: 3 POST attempts per minute per IP (ADR-032)
- Duplicate clinic code or owner email returns a **generic** validation message (no enumeration)
- Owner password must meet `Password::defaults()` (min 12, mixed case, number, symbol)
- Security audit: `clinic_registered` on success

---

## 22. Business Configuration Wizard (Milestone 12, ADR-031)

```mermaid
flowchart TD
    A[Clinic Registration] --> B[Configuration Dashboard]
    B --> C{Doctors configured?}
    C -->|No| D[Manage Doctors]
    C -->|Yes| E{Labs active?}
    E -->|No| F[Manage Laboratories]
    E -->|Yes| G{Treatments configured?}
    G -->|No| H[Manage Treatments]
    G -->|Yes| I{Lab prices configured?}
    I -->|No| J[Manage Lab Prices]
    I -->|Yes| K{Fixed-fee doctors?}
    K -->|Yes, missing fees| L[Manage Doctor Fixed Fees]
    K -->|No or complete| M[Import First Report]
    D --> B
    F --> B
    H --> B
    J --> B
    L --> B
```

**Rules:**

- Onboarding creates only clinic, owner, and default lab — no doctors, treatments, or prices
- Progress is calculated dynamically per clinic (`ConfigurationProgressService`)
- Import UI and API reject uploads until required configuration exists
- Doctor fixed fees are optional unless the clinic has active no-commission doctors
- Clinic 111 remains fully operational with seeded configuration

---

## 23. Authentication Security (ADR-032, ADR-033)

```mermaid
sequenceDiagram
    actor User
    participant Web as AuthController
    participant Auth as AuthenticationService
    participant RL as LoginThrottleService
    participant Audit as AuditLogService

    User->>Web: POST /login
    Web->>Auth: authenticate(credentials)
    Auth->>RL: tooManyAttempts(email+ip+ua)?
    alt locked out
        Auth->>Audit: login_lockout (platform if unknown email)
        Auth-->>Web: 429 / generic lockout message
    else credentials invalid
        Auth->>RL: hit(email+ip+ua)
        Auth->>Audit: login_failed (platform if unknown email)
        Auth-->>Web: generic invalid credentials
    else success
        Auth->>RL: clear(email+ip+ua)
        Auth->>Audit: login_succeeded
        Web->>Web: session regenerate + CSRF token
        Web->>User: redirect / token
```

**Rules:**

- Same generic message for wrong email, wrong password, and deactivated account
- Successful login clears failed-attempt counter
- Logout invalidates session, regenerates CSRF token, and writes `logout` audit entry
- Unknown-email failures use platform audit context (`clinic_id = null`) — never `CLINIC_111`
- New clinic owners must verify email before accessing protected routes (`verified` middleware)

---

## 24. Platform Security (Milestone 13A, ADR-033)

```mermaid
sequenceDiagram
    actor Owner
    participant Web as ClinicOnboardingController
    participant Svc as ClinicOnboardingService
    participant Captcha as CaptchaVerificationService
    participant Audit as AuditLogService
    participant Mail as VerifyEmail notification

    Owner->>Web: POST /register-clinic
    Web->>Captcha: verify(token) when enabled
    alt CAPTCHA invalid
        Web-->>Owner: validation error
    else valid
        Web->>Svc: register(data)
        Svc->>Audit: clinic_created, user_created, clinic_registered
        Svc->>Mail: sendEmailVerificationNotification
        Svc->>Audit: email_verification_sent
        Web->>Owner: redirect /email/verify
    end
```

**Registration abuse (429):**

- Rate limiter on `register-clinic` writes `registration_abuse` with platform audit context

**Background jobs:** Future queued tenant work must receive explicit `clinic_id`; must not call `CurrentClinicResolver` without authenticated context.

---

## 25. Tenant Authorization (Milestone 13B, ADR-033)

Cross-clinic resource access returns **404** via `TenantResourceGuard` and service-layer `assertSameClinic()`. User admin routes resolve `{managedUser}` by integer ID through the guard (not implicit `{user}` binding).

---

## 26. SQLite → PostgreSQL production migration (ADR-035)

**Trigger:** Deploying to production with PostgreSQL while preserving existing SQLite customer data.

**Steps:**

1. Backup: `cp database/database.sqlite database/database.sqlite.backup-$(date +%Y%m%d-%H%M%S)`
2. Configure production `.env` with `DB_CONNECTION=pgsql`
3. `php artisan migrate --force` on empty PostgreSQL
4. `php artisan app:migrate-sqlite-to-pgsql --dry-run` — compare row counts
5. `php artisan app:migrate-sqlite-to-pgsql` — import with validation
6. Optional PostgreSQL backup: `pg_dump clinic_accounting > backup.sql`

**Rules:** Source SQLite is read-only. Command never prints passwords or patient data.

---

## 21. Environment Variables (import / privacy)

| Variable | Purpose |
|---|---|
| `ACCOUNTING_PATIENT_REFERENCE_HMAC_KEY` | Dedicated secret for patient reference hashing (**required**, not `APP_KEY`) |
| `ACCOUNTING_DELETE_UPLOAD_AFTER_IMPORT` | Delete Excel after successful import (default `true`) |
| `ACCOUNTING_USD_EXCHANGE_RATE` | USD → AED (default `3.65`) |

---

## 13. V2 Manual Entry (Planned)

Same pipeline after row creation: `TreatmentImportValidationService` → `LabJobCalculationService`. No Excel parser.

---

## 26. Multi-Currency Foundation (Milestone 14, ADR-034)

```mermaid
sequenceDiagram
    participant UI as Configuration UI
    participant Cat as CurrencyCatalog
    participant Clinic as clinics.currency
    participant Fmt as CurrencyFormatter
    participant Eng as Accounting Engine

    UI->>Cat: Validate supported ISO code
    Cat->>Clinic: Persist base currency
    Eng->>Clinic: Calculate in base currency only
    UI->>Fmt: Format amounts for display
```

**Rules:**

1. Clinic registration and admin store one validated base currency.
2. Catalog defines code, symbol, precision — not duplicated in controllers.
3. Accounting engine never converts currencies internally (legacy Clinic 111 import layout excepted).
4. Conversion contracts exist for future milestones — not bound or called.

---

## What Changed

**Updated — 2026-06-29**

- SQLite → PostgreSQL production migration workflow (ADR-035)

**Updated — 2026-06-28**

- Multi-currency foundation workflow — catalog, Money value object, centralized formatting (Milestone 14, ADR-034)

**Updated — 2026-06-27**

- Platform security workflows — email verification, CAPTCHA registration, platform audit context, security headers (Milestone 13A, ADR-033)
- Tenant authorization workflow — cross-clinic 404, TenantResourceGuard, clinic-scoped FK validation (Milestone 13B, ADR-033)
- Business configuration wizard and import readiness guard (Milestone 12, ADR-031)
- Clinic onboarding workflow (Milestone 11, ADR-030)
- Accounting ownership and isolation (Milestone 10, ADR-029)

**Updated — 2026-06-26**

- Clinic onboarding workflow (Milestone 11, ADR-030)
- Explicit query isolation workflow (Milestone 09, ADR-028)
- Configuration clinic ownership workflow (Milestone 07, ADR-026)
- Current clinic resolver workflow (Milestone 08, ADR-027)

**Updated — 2026-06-26**

- Clinic administration workflow (Milestone 06, ADR-026)

**Updated — 2026-06-25**

- Laboratory administration workflow (Milestone 01)

**Updated — 2026-06-26**

- Lab price administration workflow (Milestone 03)
- Treatment administration workflow (Milestone 02)
- Database-driven `LabCostTreatmentCatalog`

**Updated — 2026-06-25**

- Laboratory administration workflow (`/admin/users`, admin-only)
- Extended RBAC table with user management and master data

**Updated — 2026-06-19**

- Privacy-safe import: HMAC patient reference, no plain-text PII in DB or API
- `TreatmentImportValidationService` with warnings and `needs_review` status
- `GET /api/daily-reports/{id}/validation-summary`
- All valid treatments → `work_items`; lab jobs only when `has_lab_cost`
- Upload file deleted after successful import
- Extractor vs parser vs validation documented separately

**Initial documentation — 2026-06-19**

Created import, parse, calculate, monthly report, and auth workflows with Mermaid diagrams.
