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

**Response `201`:**

```json
{
  "message": "Daily report imported and calculated successfully.",
  "data": {
    "id": 1,
    "report_date": "2026-01-15",
    "source_type": "excel_upload",
    "source_file_name": "daily-report.xlsx",
    "status": "calculated",
    "daily_work_rows": [
      {
        "id": 1,
        "doctor": { "id": 2, "code": "RIYAD", "name": "Dr Riyad" },
        "work_date": "2026-01-15",
        "patient_name": "John Doe",
        "treatment_text": "ZIR 4 + POST 2",
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

**Response `200`:** Same structure as import response `data` object.

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

## What Changed

**Initial documentation — 2026-06-19**

Created:

- `docs/API.md` — all 8 API endpoints documented

Routes defined in:

- `routes/api.php`

Form requests:

- `LoginRequest`, `ImportDailyReportRequest`, `MonthlyIncomeRequest`
