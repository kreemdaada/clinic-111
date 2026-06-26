# Services Reference

All business logic lives in service classes. Controllers only validate and delegate.

Location: `app/Services/`

---

## Accounting Services

### `PaymentCalculationService`

**Path:** `app/Services/Accounting/PaymentCalculationService.php`

**Purpose:** Calculate TOTAL (collected payments) and create payment records.

**Input:**

```php
calculateTotalCollectedAed(
    string $dhsAmount,      // e.g. '1000.00'
    string $usdAmount,      // e.g. '500.00'
    string $visaAmount,     // e.g. '200.00'
    ?string $usdExchangeRate // default '3.65'
)
```

**Output:**

```php
[
    'usd_to_aed_amount' => '1825.00',
    'paid_total_aed'    => '3025.00',
]
```

**Business rules:**

- TOTAL = DHS + (USD × exchange_rate) + VISA
- Zero-amount payment components are not persisted
- Each component stored as separate `payments` row with `amount_aed`

**Dependencies:** `MoneyCalculator`, `Payment` model

---

### `TreatmentParserService`

**Path:** `app/Services/Accounting/TreatmentParserService.php`

**Purpose:** Parse free-text treatment descriptions into structured work items.

**Input:**

```
"ZIR 4 + POST 2"
```

**Output:**

```
ParsedTreatmentItemDto: ZIR, quantity 4, confidence 100
ParsedTreatmentItemDto: POST, quantity 2, confidence 100
```

**Warnings (import validation only):**

- Unknown codes, invalid format, and missing quantity → `daily_report_import_warnings` (not silent skip).
- `TreatmentParserService::parse()` alone does not emit DB warnings; use `TreatmentImportValidationService` during import.

**Supported patterns:**

- `ZIR x 4`, `ZIR X 4`, `ZIR × 4`
- `ZIR 4` (space-separated quantity)
- `ZIR` alone (quantity defaults to 1)

**Dependencies:** `Treatment` model, `ParsedTreatmentItemDto`

**Methods:**

| Method | Description |
|---|---|
| `parse(string $text)` | Returns array of DTOs (no DB write) |
| `parseAndPersist(DailyWorkRow $row)` | Parses and creates work_items for all known codes |

---

### `TreatmentImportValidationService`

**Path:** `app/Services/Import/TreatmentImportValidationService.php`

**Purpose:** Validate `treatment_text` during import, emit warnings, persist valid work items.

**Methods:**

| Method | Description |
|---|---|
| `validateAndPersist(DailyWorkRow $row)` | Returns `TreatmentImportResultDto` (count + warnings) |
| `collectLabPriceWarnings(DailyWorkRow $row)` | Warnings when lab-cost item has no `lab_job` |

**Persistence:** All valid known treatments → `work_items`. Lab jobs created separately.

---

### `PatientReferenceHasher` / `ImportRowPrivacySanitizer`

**Path:** `app/Support/PatientReferenceHasher.php`, `app/Support/ImportRowPrivacySanitizer.php`

**Purpose:** Privacy during import — HMAC patient reference, strip PII from `raw_data_json`.

**Requires:** `ACCOUNTING_PATIENT_REFERENCE_HMAC_KEY` in `.env` (dedicated secret, not `APP_KEY`).

---

### `DailyReportValidationSummaryService`

**Path:** `app/Services/Import/DailyReportValidationSummaryService.php`

**Purpose:** Build validation summary payload for `GET /api/daily-reports/{id}/validation-summary`.

---

### `LabPriceResolver`

**Path:** `app/Services/Accounting/LabPriceResolver.php`

**Purpose:** Find the correct lab unit price for a doctor + treatment + lab combination.

**Input:**

```php
resolve(Doctor $doctor, Treatment $treatment, Lab $lab, ?CarbonInterface $date)
```

**Output:** `LabPrice` model or `null`

**Business rules:**

1. Try doctor-specific price (`doctor_id` set).
2. Fall back to default price (`doctor_id IS NULL`).
3. Respect `valid_from` / `valid_to` date ranges.

**Lab selection (`resolveLabForDoctor`):**

1. Doctor's `default_lab_id` if active.
2. First active lab in system.

**Dependencies:** `LabPrice`, `Lab`, `Doctor` models

---

### `LabJobCalculationService`

**Path:** `app/Services/Accounting/LabJobCalculationService.php`

**Purpose:** Calculate and persist lab costs (JOB) for all work items in a report.

**Input:** `DailyReport` or `DailyWorkRow`

**Output:** Creates `lab_jobs` records in database (no return value)

**Business rules:**

- Skip treatments where `has_lab_cost = false`
- `total_cost_aed = quantity × unit_cost` (both in AED)
- Existing lab job for work item is deleted before recalculation
- Status set to `calculated`

**Dependencies:** `LabPriceResolver`, `MoneyCalculator`, `LabJob` model

**Example:**

```
Input:  ZIR × 4 for Dr Riyad
Output: lab_job.total_cost_aed = 1600.00 (4 × 400)
```

---

### `MonthlyIncomeCalculationService`

**Path:** `app/Services/Accounting/MonthlyIncomeCalculationService.php`

**Purpose:** Calculate monthly income summary per doctor.

**Input:** `string $month` (format `YYYY-MM`)

**Output:** `Collection<MonthlyIncomeSummaryDto>`

**Business rules:**

- TOTAL = SUM(`payments.amount_aed`) for month
- LAB COST = SUM(`lab_jobs.total_cost_aed`) for month
- NET TOTAL = TOTAL - LAB COST
- Percentage doctors: DOCTOR INCOME = NET TOTAL × percentage / 100
- Fixed doctors: DOCTOR INCOME = SUM(fixed_fee_aed × quantity)
- CLINIC INCOME = NET TOTAL - DOCTOR INCOME
- Treatment counts grouped by treatment code

**Public helper methods (used in tests):**

| Method | Formula |
|---|---|
| `calculateNetTotal($total, $labCost)` | TOTAL - LAB COST |
| `calculatePercentageDoctorIncome($net, $pct)` | NET × pct / 100 |

**Dependencies:** `MoneyCalculator`, `MonthlyIncomeSummaryDto`, Payment/LabJob/WorkItem models

---

## Import Services

### `DailyReportImportService`

**Path:** `app/Services/Import/DailyReportImportService.php`

**Purpose:** Orchestrate the full Excel import pipeline.

**Input:**

```php
import(UploadedFile $file, ?string $reportDate = null): DailyReport
```

**Output:** Fully calculated `DailyReport` with all relations loaded.

**Pipeline:**

1. Store file privately
2. Begin transaction
3. Create daily_report
4. Parse Excel → hash patient ref → sanitize raw JSON → create daily_work_rows + payments
5. Validate treatments → work_items + import_warnings
6. Calculate lab jobs (has_lab_cost only)
7. Set status `calculated` or `needs_review`
8. Extraction log + audit
9. Commit; delete uploaded file (default)

**Business rules:**

- Approved reports for same month cannot be overwritten
- Patient name/MRN/file never persisted or returned in API
- Invalid treatments produce warnings, not silent drops

**Dependencies:** `ExcelDailyReportParser`, `PaymentCalculationService`, `TreatmentImportValidationService`, `LabJobCalculationService`, `PatientReferenceHasher`, `ImportRowPrivacySanitizer`, `ImportExtractionLogService`

---

### `ExcelDailyReportParser`

**Path:** `app/Services/Import/ExcelDailyReportParser.php`

**Purpose:** Read Excel files and extract structured row data. **Does not parse treatment codes** — only reads the `treatment_text` cell as a string.

**Input:** File path (string)

**Output (in memory, before privacy sanitization):**

```php
[
    [
        'doctor' => 'DR Jack',
        'sheet_day' => 15,
        'raw_row_number' => 25,
        'patient_name' => '...',  // memory only — never persisted
        'mrn' => '...',
        'file_number' => '...',
        'treatment_text' => 'ZIR x 4 + POST x 2',
        'dhs_amount' => 1000,
        'usd_amount' => 0,
        'visa_amount' => 200,
        'raw_cells' => [...],
    ],
]
```

**Business rules:**

- Clinic 111 layout: sheets `1`–`31`, header row 3, doctor row 2
- Reads calculated cell values only (not formulas)
- Skips empty rows; emits diagnostic events for unresolved doctors

**Dependencies:** PhpSpreadsheet

---

## Support Classes

### `MoneyCalculator`

**Path:** `app/Support/MoneyCalculator.php`

**Purpose:** All bcmath money operations. No floats.

| Method | Description |
|---|---|
| `add(...$amounts)` | Sum decimal strings |
| `subtract($a, $b)` | Subtract |
| `multiply($amount, $qty)` | Multiply amount by integer quantity |
| `percentage($amount, $pct)` | Calculate percentage with half-up rounding |
| `convertToAed($amount, $currency, $rate)` | Convert USD to AED |
| `roundToTwoDecimals($amount)` | Standard rounding to 2 dp |

---

### `LabManagementService`

**Path:** `app/Services/Accounting/LabManagementService.php`

**Purpose:** Admin CRUD for laboratory master data (`labs` table).

**Input:**

```php
create(['name' => 'Main Lab', 'code' => 'MAIN_LAB'])
update($lab, ['name' => '...', 'code' => '...', 'is_active' => true|false])
deactivate($lab)
activate($lab)
```

**Output:** `Lab` model

**Business rules:**

- Codes are stored uppercase
- Never physically deletes rows — `deactivate()` sets `is_active = false`
- Inactive labs are excluded from active-lab queries used by the accounting engine
- Historical `lab_jobs` keep their `lab_id` reference unchanged
- Every create/update/activate/deactivate writes an audit log

**Dependencies:** `AuditLogService`, `Lab` model

---

### `TreatmentManagementService`

**Path:** `app/Services/Accounting/TreatmentManagementService.php`

**Purpose:** Admin CRUD for treatment master data (`treatments` table).

**Input:**

```php
create(['code' => 'MC', 'name' => 'Metal Ceramic Crown', 'has_lab_cost' => true, 'description' => null])
update($treatment, ['code' => '...', 'name' => '...', 'has_lab_cost' => bool, 'is_active' => bool])
deactivate($treatment)
activate($treatment)
```

**Output:** `Treatment` model

**Business rules:**

- Codes are stored uppercase and must be unique
- Never physically deletes rows
- `has_lab_cost` drives `LabJobCalculationService` and `LabCostTreatmentCatalog`
- Inactive treatments are excluded from `TreatmentParserService` known codes and editor catalog
- Historical `work_items` retain `treatment_id` references

**Dependencies:** `AuditLogService`, `Treatment` model

---

### `LabPriceManagementService`

**Path:** `app/Services/Accounting/LabPriceManagementService.php`

**Purpose:** Admin CRUD for lab unit prices (`lab_prices` table).

**Input:**

```php
create(['lab_id' => 1, 'treatment_id' => 2, 'doctor_id' => null, 'unit_cost' => '360.00', 'currency' => 'AED', 'valid_from' => null, 'valid_to' => null])
update($labPrice, ['unit_cost' => '...', 'is_active' => bool, ...])
deactivate($labPrice)
activate($labPrice)
duplicate($labPrice)  // creates inactive copy for new validity period
```

**Output:** `LabPrice` model (with `lab`, `treatment`, `doctor` loaded)

**Business rules:**

- Never physically deletes rows
- Only one active price per lab + treatment + doctor scope + overlapping validity period (`LabPriceOverlapValidator`)
- General price: `doctor_id IS NULL`; doctor override takes precedence in `LabPriceResolver`
- Inactive prices excluded from resolution; historical `lab_jobs.lab_price_id` unchanged
- Duplicate creates an **inactive** copy — adjust dates before activating

**Dependencies:** `AuditLogService`, `LabPriceOverlapValidator`, `LabPrice` model

---

### `LabPriceOverlapValidator`

**Path:** `app/Support/LabPriceOverlapValidator.php`

**Purpose:** Detect overlapping active price rows for the same lab/treatment/doctor scope.

---

### `DoctorFixedFeeManagementService`

**Path:** `app/Services/Accounting/DoctorFixedFeeManagementService.php`

**Purpose:** Admin CRUD for doctor fixed procedure fees (`doctor_fixed_fees` table).

**Input:**

```php
create(['doctor_id' => 1, 'treatment_id' => 2, 'fee_amount' => '500.00', 'currency' => 'AED', 'valid_from' => null, 'valid_to' => null])
update($doctorFixedFee, ['fee_amount' => '...', 'is_active' => bool, ...])
deactivate($doctorFixedFee)
activate($doctorFixedFee)
duplicate($doctorFixedFee)  // creates inactive copy for new validity period
```

**Output:** `DoctorFixedFee` model (with `doctor`, `treatment` loaded)

**Business rules:**

- Never physically deletes rows
- Doctor must have `commission_type = fixed`
- Only one active fee per doctor + treatment + overlapping validity period (`DoctorFixedFeeOverlapValidator`)
- Inactive fees excluded from `DoctorFixedFeeResolver`; accounting engine services unchanged
- Duplicate creates an **inactive** copy — adjust dates before activating

**Dependencies:** `AuditLogService`, `DoctorFixedFeeOverlapValidator`, `DoctorFixedFee` model

---

### `DoctorFixedFeeResolver`

**Path:** `app/Services/Accounting/DoctorFixedFeeResolver.php`

**Purpose:** Resolve the effective fixed fee for a doctor/treatment on a given work date.

**Business rules:** Active rows only; respects `valid_from` / `valid_to` when set.

---

### `DoctorFixedFeeOverlapValidator`

**Path:** `app/Support/DoctorFixedFeeOverlapValidator.php`

**Purpose:** Detect overlapping active fixed fee rows for the same doctor/treatment scope.

---

## Configuration Services

### `ConfigurationDashboardService`

**Path:** `app/Services/Configuration/ConfigurationDashboardService.php`

**Purpose:** Aggregate statistics, recent configuration audit activity, and health warnings for the admin configuration dashboard.

**Input:**

```php
buildDashboard(?int $clinicId = null)
moduleStatistics(?int $clinicId = null)
recentActivity(?int $clinicId = null, int $limit = 15)
healthWarnings(?int $clinicId = null)
```

**Output:** Module card data (total/active/inactive counts), recent audit rows, warning messages (read-only — never auto-fixes data).

**Business rules:**

- Covers Doctors, Labs, Treatments, Lab Prices, Doctor Fixed Fees, Users
- Health warnings only — no automatic data changes
- Optional `$clinicId` reserved for future multi-clinic scoping (currently ignored)

**Dependencies:** Configuration models, `AuditLog`, `AuditAction`

---

## Audit Services

### `AuditLogService`

**Path:** `app/Services/Audit/AuditLogService.php`

**Purpose:** Record audit trail for sensitive actions.

**Input:**

```php
log(
    AuditAction $action,
    ?Model $auditable = null,
    ?array $oldValues = null,
    ?array $newValues = null,
): AuditLog
```

**Output:** `AuditLog` model

**Logged actions:** `report_import`, `price_change`, `commission_change`, `report_approval`, `manual_correction`, `lab_created`, `lab_updated`, `lab_deactivated`, `lab_activated`, `lab_price_created`, `lab_price_deactivated`, `lab_price_activated`, `treatment_created`, `treatment_updated`, `treatment_deactivated`, `treatment_activated`, `user_created`, `user_role_changed`, `user_deactivated`, `password_reset`

**Dependencies:** `AuditLog` model, authenticated user

---

## DTOs

### `MonthlyIncomeSummaryDto`

**Path:** `app/DTOs/MonthlyIncomeSummaryDto.php`

Readonly DTO returned by monthly income calculation. Has `toArray()` for JSON serialization.

### `ParsedTreatmentItemDto`

**Path:** `app/DTOs/ParsedTreatmentItemDto.php`

Readonly DTO for a single parsed treatment line before persistence.

### `ImportParseWarningDto` / `TreatmentImportResultDto`

**Path:** `app/DTOs/ImportParseWarningDto.php`, `app/DTOs/TreatmentImportResultDto.php`

Import validation warning and per-row persist result.

---

## What Changed

**Updated — 2026-06-26**

- Documented `ConfigurationDashboardService` (Milestone 05)
- Documented `DoctorFixedFeeManagementService`, `DoctorFixedFeeResolver`, and `DoctorFixedFeeOverlapValidator` (Milestone 04)
- Documented `LabPriceManagementService` and `LabPriceOverlapValidator` (Milestone 03)
- Documented `TreatmentManagementService` (Milestone 02)
- `LabCostTreatmentCatalog` now reads `has_lab_cost` from database

**Updated — 2026-06-25**

- Documented `LabManagementService` (Milestone 01)

**Updated — 2026-06-19**

- Documented `TreatmentImportValidationService`, privacy helpers, validation summary
- Updated import pipeline and work_item persistence rules

**Initial documentation — 2026-06-19**

Created:

- `docs/SERVICES.md` — all 8 service/support classes documented

Services documented:

- `PaymentCalculationService`
- `TreatmentParserService`
- `LabPriceResolver`
- `LabJobCalculationService`
- `MonthlyIncomeCalculationService`
- `DailyReportImportService`
- `ExcelDailyReportParser`
- `AuditLogService`
- `MoneyCalculator`
