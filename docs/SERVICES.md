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

**Warnings:**

- Unknown treatment codes are silently skipped (not in database).
- Known code with unclear quantity → quantity = 1, confidence = 80, warning message set.

**Supported patterns:**

- `ZIR x 4`, `ZIR X 4`, `ZIR × 4`
- `ZIR 4` (space-separated quantity)
- `ZIR` alone (quantity defaults to 1)

**Dependencies:** `Treatment` model, `ParsedTreatmentItemDto`

**Methods:**

| Method | Description |
|---|---|
| `parse(string $text)` | Returns array of DTOs (no DB write) |
| `parseAndPersist(DailyWorkRow $row)` | Parses and creates work_items |

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
4. Parse Excel → create daily_work_rows + payments
5. Parse treatments → work_items
6. Calculate lab jobs
7. Audit log
8. Commit

**Business rules:**

- Approved reports for same date cannot be overwritten
- Approved reports cannot be reprocessed
- Failed imports roll back entire transaction
- Doctor resolved by code or name from database (never hardcoded)

**Dependencies:** `ExcelDailyReportParser`, `PaymentCalculationService`, `TreatmentParserService`, `LabJobCalculationService`, `AuditLogService`

---

### `ExcelDailyReportParser`

**Path:** `app/Services/Import/ExcelDailyReportParser.php`

**Purpose:** Read Excel files and extract structured row data. Isolated for easy replacement in V2.

**Input:** File path (string)

**Output:**

```php
[
    [
        'doctor' => 'JACK',
        'work_date' => '2026-01-15',
        'patient_name' => 'John Doe',
        'treatment_text' => 'ZIR 4 + POST 2',
        'dhs_amount' => 1000,
        'usd_amount' => 0,
        'visa_amount' => 200,
        'raw_cells' => [...],  // full row for audit
    ],
    // ...
]
```

**Business rules:**

- Reads calculated cell values only (not formulas)
- Sanitizes string values (strip tags, trim)
- Detects header row by looking for DOCTOR/DR column
- Maps common column name aliases (DHS, USD, VISA, TREATMENT, etc.)
- Skips empty rows

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

**Logged actions:** `report_import`, `price_change`, `commission_change`, `report_approval`, `manual_correction`

**Dependencies:** `AuditLog` model, authenticated user

---

## DTOs

### `MonthlyIncomeSummaryDto`

**Path:** `app/DTOs/MonthlyIncomeSummaryDto.php`

Readonly DTO returned by monthly income calculation. Has `toArray()` for JSON serialization.

### `ParsedTreatmentItemDto`

**Path:** `app/DTOs/ParsedTreatmentItemDto.php`

Readonly DTO for a single parsed treatment line before persistence.

---

## What Changed

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
