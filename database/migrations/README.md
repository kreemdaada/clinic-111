# Database Migrations

Laravel migrations for the clinic accounting schema. Application tables use prefix `2026_06_19_0000xx`.

Fresh install:

```bash
php artisan migrate --seed
```

## Application tables (in order)

| Migration | Table | Purpose |
|---|---|---|
| `000001` | `labs` | Dental laboratories |
| `000002` | `doctors` | Doctors, commission type, default lab |
| `000003` | `treatments` | Procedure codes; `has_lab_cost` flag |
| `000004` | `lab_prices` | Unit cost per lab/treatment/doctor |
| `000005` | `doctor_fixed_fees` | Fixed fees for Dr Wa |
| `000006` | `daily_reports` | One import or manual report per month anchor |
| `000007` | `daily_work_rows` | One Excel row (payments + treatment_text) |
| `000008` | `work_items` | Parsed treatment lines (all valid codes) |
| `000009` | `lab_jobs` | Calculated JOB (lab cost) per lab-cost work_item |
| `000010` | `payments` | DHS / USD / VISA payment components |
| `000011` | `users.role` | admin, accountant, viewer |
| `000012` | `audit_logs` | Import and sensitive action audit trail |
| `000013` | `doctor_income_export_profiles` | Server Income Excel layout per doctor |
| `000014` | privacy + warnings | Removes plain-text PII; adds `patient_reference_hash`, `daily_report_import_warnings` |
| `000015` | `doctor_lab_billings` | Per-doctor JOB rules (Puriya subset; Wa none) |

## Privacy migration (`000014`)

- **Drops:** `patient_name`, `mrn`, `file_number` from `daily_work_rows`
- **Adds:** `patient_reference_hash` (HMAC-SHA256), `excel_row_number`
- **Adds:** `daily_report_import_warnings` for parser/lab-pricing review

Patient identifiers are read in memory during Excel import only. Never stored as plain text.

## Status values (`daily_reports.status`)

`uploaded` → `parsed` → `calculated` or `needs_review` → `approved` | `failed`
