# API Reference

Base URL: `/api`

Authentication: Laravel Sanctum bearer token (except login).

```
Authorization: Bearer {token}
Content-Type: application/json
```

All JSON error responses follow Laravel's standard format.

---

### Web UI (recommended for Excel upload)

For drag-and-drop Excel import without curl, use the web interface:

1. Start server: `./bin/serve` (20 MB upload limit)
2. Open `http://127.0.0.1:8000/login`
3. Sign in as `accountant@clinic.test` / `password`
4. Go to **Import** — drag & drop `.xlsx` or `.xlsm`
5. Optional: set **Report date** to first day of month (e.g. `2026-01-01`)
6. View **Logs** for audit trail + `storage/logs/import-*.log`

| Route | Method | Role |
|---|---|---|
| `/login` | GET/POST | guest |
| `/imports` | GET/POST | admin, accountant |
| `/imports/{id}` | GET | admin, accountant, viewer |
| `/logs` | GET | admin, accountant |

---

## Authentication

### POST /api/login

**Purpose:** Obtain API token.

**Role:** Public (no auth required)

**Rate limit:** 10 requests/minute

**Request:**

```json
{
  "email": "accountant@clinic.test",
  "password": "password"
}
```

**Validation (`LoginRequest`):**

| Field | Rules |
|---|---|
| `email` | required, email |
| `password` | required, string |

**Response `200`:**

```json
{
  "token": "1|abc123...",
  "user": {
    "id": 2,
    "name": "Accountant User",
    "email": "accountant@clinic.test",
    "role": "accountant"
  }
}
```

**Error `422`:** Invalid credentials.

---

### POST /api/logout

**Purpose:** Revoke current API token.

**Role:** Any authenticated user

**Request:** No body.

**Response `200`:**

```json
{
  "message": "Logged out successfully."
}
```

---

## Daily Reports

### POST /api/daily-reports/import

**Purpose:** Upload Excel daily report, import and calculate.

**Role:** `admin`, `accountant`

**Rate limit:** 20 requests/minute

**Request:** `multipart/form-data`

| Field | Type | Required | Rules |
|---|---|---|---|
| `file` | file | yes | xlsx or xlsm, max 10 MB |
| `report_date` | date | no | YYYY-MM-DD; defaults to today |

**Example (curl):**

```bash
curl -X POST /api/daily-reports/import \
  -H "Authorization: Bearer {token}" \
  -F "file=@daily-report.xlsx" \
  -F "report_date=2026-01-15"
```

**Response `201` (no warnings):**

```json
{
  "message": "Daily report imported and calculated successfully.",
  "data": {
    "id": 1,
    "report_date": "2026-01-01",
    "source_type": "excel_upload",
    "source_file_name": "daily report january 2026.xlsm",
    "status": "calculated",
    "daily_work_rows": [
      {
        "id": 1,
        "doctor": { "id": 2, "code": "RIYAD", "name": "Dr Riyad" },
        "work_date": "2026-01-15",
        "excel_row_number": 25,
        "treatment_text": "ZIR x 4 + POST x 2",
        "paid_total_aed": "3025.00",
        "payments": [
          {
            "payment_method": "dhs",
            "amount": "1000.00",
            "currency": "AED",
            "amount_aed": "1000.00"
          }
        ],
        "work_items": [
          {
            "treatment_code": "ZIR",
            "quantity": 4,
            "lab_job": {
              "total_cost_aed": "1600.00",
              "status": "calculated"
            }
          }
        ]
      }
    ]
  }
}
```

**Response `201` (with parser warnings):** `"status": "needs_review"` and message *"Daily report imported with parser warnings requiring review."*

**Privacy:** Responses never include `patient_name`, `mrn`, or `file_number`.

**Errors:**

| Code | Condition |
|---|---|
| `401` | Not authenticated |
| `403` | Insufficient role |
| `422` | Validation failed (wrong file type, too large) |
| `500` | Import failed (unknown doctor, parse error) — transaction rolled back |

---

### GET /api/daily-reports/{id}

**Purpose:** Retrieve a daily report with all calculated data.

**Role:** `admin`, `accountant`, `viewer`

**Request:** No body. `{id}` is the daily report ID.

**Response `200`:** Same structure as import response `data` object (no patient identifiers).

**Errors:**

| Code | Condition |
|---|---|
| `401` | Not authenticated |
| `403` | Insufficient role |
| `404` | Report not found |

---

### GET /api/daily-reports/{id}/validation-summary

**Purpose:** Parser and lab-pricing warnings for an imported report.

**Role:** `admin`, `accountant`, `viewer`

**Response `200`:**

```json
{
  "data": {
    "total_rows": 120,
    "parsed_items": 96,
    "warnings_count": 4,
    "warnings": [
      {
        "excel_row": 25,
        "doctor": "Dr Riyad",
        "treatment_text": "zircon 2",
        "message": "Invalid format. Use ZIR x 2"
      }
    ]
  }
}
```

**Warning types (stored in `daily_report_import_warnings.warning_code`):**

| Code | Meaning |
|---|---|
| `invalid_format` | Text does not match `CODE x QUANTITY` |
| `missing_quantity` | Code without quantity |
| `unknown_treatment_code` | Code not in `treatments` table |
| `lab_price_not_found` | Lab-cost treatment with no matching price |

**Errors:**

| Code | Condition |
|---|---|
| `401` | Not authenticated |
| `403` | Insufficient role |
| `404` | Report not found |

---

## Monthly Income

### GET /api/monthly-income

**Purpose:** Monthly income summary for all active doctors.

**Role:** `admin`, `accountant`, `viewer`

**Query parameters:**

| Param | Required | Format | Example |
|---|---|---|---|
| `month` | yes | `YYYY-MM` | `2026-01` |

**Example:**

```
GET /api/monthly-income?month=2026-01
```

**Validation (`MonthlyIncomeRequest`):**

| Field | Rules |
|---|---|
| `month` | required, date_format:Y-m |

**Response `200`:**

```json
{
  "month": "2026-01",
  "data": [
    {
      "doctor_id": 1,
      "doctor_name": "Dr Jack",
      "month": "2026-01",
      "total_dhs": "25000.00",
      "total_usd_to_aed": "5000.00",
      "total_visa": "3000.00",
      "total_collected_aed": "33000.00",
      "lab_cost_aed": "8000.00",
      "net_total_aed": "25000.00",
      "doctor_income_aed": "8750.00",
      "clinic_income_aed": "16250.00",
      "treatment_counts": {
        "MC": 5,
        "ZIR": 12,
        "POST": 3
      }
    }
  ]
}
```

---

## Reference Data

### GET /api/doctors

**Purpose:** List all active doctors with commission settings.

**Role:** Any authenticated user

**Response `200`:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Dr Jack",
      "code": "JACK",
      "commission_type": "percentage",
      "commission_percentage": "35.00",
      "default_lab": {
        "id": 1,
        "code": "MAIN_LAB",
        "name": "Main Lab"
      }
    }
  ]
}
```

---

### GET /api/treatments

**Purpose:** List all active treatments.

**Role:** Any authenticated user

**Response `200`:**

```json
{
  "data": [
    {
      "id": 1,
      "code": "ZIR",
      "name": "Zircon Crown",
      "has_lab_cost": true
    }
  ]
}
```

---

### GET /api/labs

**Purpose:** List all active labs.

**Role:** Any authenticated user

**Response `200`:**

```json
{
  "data": [
    {
      "id": 1,
      "code": "MAIN_LAB",
      "name": "Main Lab"
    }
  ]
}
```

---

---

## Laboratory Administration (admin only)

Reference endpoint `GET /api/labs` returns **active labs only** (unchanged).

Admin management uses `/api/admin/labs` and web `/labs`.

### GET /api/admin/labs

**Purpose:** List all laboratories with optional search and status filter.

**Role:** admin

**Query parameters (`ListLabsRequest`):**

| Param | Rules |
|---|---|
| `search` | optional, max 120 — matches name or code |
| `status` | optional: `all`, `active`, `inactive` (default `all`) |

**Response `200`:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Main Lab",
      "code": "MAIN_LAB",
      "is_active": true,
      "lab_jobs_count": 42,
      "lab_prices_count": 18
    }
  ]
}
```

---

### POST /api/admin/labs

**Purpose:** Create a laboratory.

**Role:** admin

**Request:**

```json
{
  "name": "Secondary Lab",
  "code": "SEC_LAB"
}
```

**Validation (`StoreLabRequest`):** `name` required; `code` required, unique, alphanumeric/underscore/dash.

**Response `201`:** Created lab + audit `lab_created`.

---

### PUT /api/admin/labs/{id}

**Purpose:** Update name, code, or active status.

**Role:** admin

**Validation (`UpdateLabRequest`):** same as create; code unique except current lab.

**Audit:** `lab_updated`, `lab_deactivated`, or `lab_activated` depending on changes.

---

### DELETE /api/admin/labs/{id}

**Purpose:** Soft-deactivate (`is_active = false`). Never deletes the row.

**Role:** admin

---

### POST /api/admin/labs/{id}/activate

**Purpose:** Reactivate a deactivated laboratory.

**Role:** admin

---

### Web UI: `/labs`

| Route | Method | Action |
|---|---|---|
| `/labs` | GET | List + search + status filter |
| `/labs` | POST | Create |
| `/labs/{id}` | PUT | Update |
| `/labs/{id}` | DELETE | Deactivate |
| `/labs/{id}/activate` | POST | Activate |

---

## Treatment Administration (admin only)

Reference endpoint `GET /api/treatments` returns **active treatments only** (unchanged).

Admin management uses `/api/admin/treatments` and web `/treatments`.

### GET /api/admin/treatments

**Purpose:** Paginated list with search and status filter.

**Role:** admin

**Query parameters (`ListTreatmentsRequest`):**

| Param | Rules |
|---|---|
| `search` | optional — matches code, name, or description |
| `status` | optional: `all`, `active`, `inactive` |
| `page` | optional pagination |

**Response `200`:** `{ data: [...], meta: { current_page, last_page, per_page, total } }`

---

### POST /api/admin/treatments

**Purpose:** Create a treatment.

**Role:** admin

**Request:**

```json
{
  "code": "NEW-TX",
  "name": "New Treatment",
  "description": "Optional",
  "has_lab_cost": true
}
```

**Validation (`StoreTreatmentRequest`):** unique `code`, required `name`, optional `description`, optional `has_lab_cost`.

**Response `201`:** Created treatment + audit `treatment_created`.

---

### PUT /api/admin/treatments/{id}

**Purpose:** Update treatment fields.

**Role:** admin

**Audit:** `treatment_updated`, `treatment_deactivated`, or `treatment_activated`.

---

### DELETE /api/admin/treatments/{id}

**Purpose:** Soft-deactivate. Never deletes the row.

**Role:** admin

---

### POST /api/admin/treatments/{id}/activate

**Purpose:** Reactivate a deactivated treatment.

**Role:** admin

---

### Web UI: `/treatments`

| Route | Method | Action |
|---|---|---|
| `/treatments` | GET | Paginated list + search/filter |
| `/treatments` | POST | Create (modal) |
| `/treatments/{id}` | PUT | Update (modal) |
| `/treatments/{id}` | DELETE | Deactivate |
| `/treatments/{id}/activate` | POST | Activate |

---

## Lab Price Administration (admin only)

Admin management uses `/api/admin/lab-prices` and web `/lab-prices`.

Accounting resolution (`LabPriceResolver`) is unchanged — reads active rows from DB.

### GET /api/admin/lab-prices

**Purpose:** List lab prices with search and filters.

**Role:** admin

**Query parameters (`ListLabPricesRequest`):**

| Param | Rules |
|---|---|
| `search` | optional — lab, treatment, or doctor code/name |
| `lab_id` | optional |
| `treatment_id` | optional |
| `doctor_id` | optional: `all`, `general`, or doctor id |
| `status` | optional: `all`, `active`, `inactive` |
| `currency` | optional, 3-letter code |

**Response `200`:** `{ data: [...] }`

---

### POST /api/admin/lab-prices

**Purpose:** Create a lab price row.

**Role:** admin

**Request:**

```json
{
  "lab_id": 1,
  "treatment_id": 5,
  "doctor_id": null,
  "unit_cost": "360.00",
  "currency": "AED",
  "valid_from": null,
  "valid_to": null
}
```

**Validation (`StoreLabPriceRequest`):** lab and treatment must exist; `unit_cost` > 0; `currency` required; no overlapping active price for same scope and period.

**Response `201`:** Created price + audit `lab_price_created`.

---

### PUT /api/admin/lab-prices/{id}

**Purpose:** Update price fields (lab, treatment, doctor override, cost, currency, validity, status).

**Role:** admin

**Audit:** `price_change`, `lab_price_deactivated`, or `lab_price_activated`.

---

### DELETE /api/admin/lab-prices/{id}

**Purpose:** Soft-deactivate. Never deletes the row.

**Role:** admin

---

### POST /api/admin/lab-prices/{id}/activate

**Purpose:** Reactivate a deactivated price (overlap validated).

**Role:** admin

---

### POST /api/admin/lab-prices/{id}/duplicate

**Purpose:** Copy row as inactive — adjust validity before activating.

**Role:** admin

**Response `201`:** New inactive price row.

---

### Web UI: `/lab-prices`

| Route | Method | Action |
|---|---|---|
| `/lab-prices` | GET | Paginated list + search/filters |
| `/lab-prices` | POST | Create (modal) |
| `/lab-prices/{id}` | PUT | Update (modal) |
| `/lab-prices/{id}` | DELETE | Deactivate |
| `/lab-prices/{id}/activate` | POST | Activate |
| `/lab-prices/{id}/duplicate` | POST | Duplicate (inactive copy) |

---

## Doctor Fixed Fee Administration (admin only)

Admin management uses `/api/admin/doctor-fixed-fees` and web `/doctor-fixed-fees`.

`DoctorFixedFeeResolver` reads active rows with date validity. Accounting calculation services (`WaelFixedFeeCalculator`, `MonthlyIncomeCalculationService`) are unchanged.

### GET /api/admin/doctor-fixed-fees

**Purpose:** List doctor fixed fees with search and filters.

**Role:** admin

**Query parameters (`ListDoctorFixedFeesRequest`):**

| Param | Rules |
|---|---|
| `search` | optional — doctor or treatment code/name |
| `doctor_id` | optional |
| `treatment_id` | optional |
| `status` | optional: `all`, `active`, `inactive` |
| `currency` | optional, 3-letter code |

**Response `200`:** `{ data: [...] }`

---

### POST /api/admin/doctor-fixed-fees

**Purpose:** Create a fixed fee row.

**Role:** admin

**Request:**

```json
{
  "doctor_id": 4,
  "treatment_id": 12,
  "fee_amount": "500.00",
  "currency": "AED",
  "valid_from": null,
  "valid_to": null
}
```

**Response `201`:** `{ message, data }`

**Validation:** Doctor must have `commission_type = fixed`; `fee_amount` > 0; currency `AED` or `USD`; no overlapping active validity.

---

### PUT /api/admin/doctor-fixed-fees/{id}

**Purpose:** Update fee amount, currency, validity, or active status.

**Role:** admin

**Response `200`:** `{ message, data }`

---

### DELETE /api/admin/doctor-fixed-fees/{id}

**Purpose:** Soft deactivate (`is_active = false`).

**Role:** admin

---

### POST /api/admin/doctor-fixed-fees/{id}/activate

**Purpose:** Reactivate row after overlap check.

**Role:** admin

---

### POST /api/admin/doctor-fixed-fees/{id}/duplicate

**Purpose:** Copy row as inactive — adjust validity before activating.

**Role:** admin

**Response `201`:** New inactive fee row.

---

### Web UI: `/doctor-fixed-fees`

| Route | Method | Action |
|---|---|---|
| `/doctor-fixed-fees` | GET | Paginated list + search/filters |
| `/doctor-fixed-fees` | POST | Create (modal) |
| `/doctor-fixed-fees/{id}` | PUT | Update (modal) |
| `/doctor-fixed-fees/{id}` | DELETE | Deactivate |
| `/doctor-fixed-fees/{id}/activate` | POST | Activate |
| `/doctor-fixed-fees/{id}/duplicate` | POST | Duplicate (inactive copy) |

---

## User Management (admin only)

### GET /api/users

**Purpose:** List all users (active and inactive).

**Role:** admin

**Response `200`:**

```json
{
  "data": [
    {
      "id": 2,
      "name": "Accountant User",
      "email": "accountant@clinic.test",
      "role": "accountant",
      "is_active": true
    }
  ]
}
```

---

### POST /api/users

**Purpose:** Create a user account.

**Role:** admin

**Request:**

```json
{
  "name": "New Viewer",
  "email": "viewer2@clinic.test",
  "role": "viewer",
  "password": "secure-password"
}
```

Or generate a temporary password:

```json
{
  "name": "New Viewer",
  "email": "viewer2@clinic.test",
  "role": "viewer",
  "generate_temp_password": true
}
```

**Validation (`StoreUserRequest`):**

| Field | Rules |
|---|---|
| `name` | required, string, max 120 |
| `email` | required, email, unique |
| `role` | required, `admin` \| `accountant` \| `viewer` |
| `password` | required_without:generate_temp_password |
| `generate_temp_password` | optional boolean |
| `is_active` | optional boolean |

**Response `201`:**

```json
{
  "message": "User created.",
  "data": {
    "id": 5,
    "name": "New Viewer",
    "email": "viewer2@clinic.test",
    "role": "viewer",
    "is_active": true
  },
  "temporary_password": "xK9mP2nQ4rTv"
}
```

`temporary_password` is `null` when a manual password was supplied.

---

### PUT /api/users/{id}

**Purpose:** Update name, email, role, or active status.

**Role:** admin

**Request:**

```json
{
  "name": "Updated Name",
  "email": "updated@clinic.test",
  "role": "accountant",
  "is_active": true
}
```

**Validation (`UpdateUserRequest`):** same fields as create (except password). Email unique except current user.

**Response `200`:** Updated user object.

**Audit:** `user_role_changed` when role changes; `user_deactivated` when `is_active` becomes false.

---

### DELETE /api/users/{id}

**Purpose:** Soft-deactivate a user (`is_active = false`). Never physically deletes the row.

**Role:** admin

**Response `200`:**

```json
{
  "message": "User deactivated.",
  "data": { "id": 5, "is_active": false }
}
```

Revokes all Sanctum tokens for the user.

---

### POST /api/users/{id}/reset-password

**Purpose:** Set a new password (manual or generated temporary).

**Role:** admin

**Request:**

```json
{
  "password": "new-secure-password"
}
```

Or:

```json
{
  "generate_temp_password": true
}
```

**Validation (`ResetUserPasswordRequest`):** `password` required_without `generate_temp_password`.

**Response `200`:** Includes `temporary_password` when generated.

**Audit:** `password_reset` (password hash is never stored in audit JSON).

---

### Web UI: `/admin/users`

Same capabilities as the API. Admin-only. Nav link visible when logged in as admin.

| Route | Method | Action |
|---|---|---|
| `/admin/users` | GET | List users + create form |
| `/admin/users` | POST | Create user |
| `/admin/users/{id}` | PUT | Update user |
| `/admin/users/{id}` | DELETE | Deactivate user |
| `/admin/users/{id}/reset-password` | POST | Reset password |

---

## Default Test Users

| Email | Password | Role |
|---|---|---|
| admin@clinic.test | password | admin |
| accountant@clinic.test | password | accountant |
| viewer@clinic.test | password | viewer |

---

## Error Response Format

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "file": ["The file field must be a file of type: xlsx, xlsm."]
  }
}
```

---

## Configuration Dashboard (admin only)

Web-only — no API endpoint in Milestone 05. Entry point for the Configuration Layer (ADR-025).

### Web UI: `/configuration`

| Route | Method | Action |
|---|---|---|
| `/configuration` | GET | Dashboard: module statistics, recent configuration audit activity, health warnings |

**Role:** admin

**Dashboard sections:**

- **Module cards** — Doctors, Laboratories, Treatments, Lab Prices, No-commission fees, Users (total / active / inactive + quick links)
- **Recent activity** — Latest configuration-related `audit_logs` (date, user, action, target)
- **Configuration health** — Read-only warnings (e.g. no active labs, missing fee rules); never auto-modifies data

---

## What Changed

**Updated — 2026-06-26**

- Configuration dashboard web UI `/configuration` (Milestone 05)
- Lab price administration API (`/api/admin/lab-prices`) and web `/lab-prices` (Milestone 03)
- Doctor fixed fee administration API (`/api/admin/doctor-fixed-fees`) and web `/doctor-fixed-fees` (Milestone 04)
- Treatment administration API (`/api/admin/treatments`) and web `/treatments`

**Updated — 2026-06-25**

- Laboratory administration API (`/api/admin/labs`) and web `/labs`

**Updated — 2026-06-25**

- User management API (`GET/POST/PUT/DELETE /api/users`, `POST /api/users/{id}/reset-password`)
- Web admin page `/admin/users`
- Documented `StoreUserRequest`, `UpdateUserRequest`, `ResetUserPasswordRequest`

**Updated — 2026-06-19**

- Added `GET /api/daily-reports/{id}/validation-summary`
- Removed patient PII from report JSON; added `excel_row_number`
- Documented `needs_review` import status

**Initial documentation — 2026-06-19**

Created:

- `docs/API.md` — all 8 API endpoints documented

Routes defined in:

- `routes/api.php`

Form requests:

- `LoginRequest`, `ImportDailyReportRequest`, `MonthlyIncomeRequest`
