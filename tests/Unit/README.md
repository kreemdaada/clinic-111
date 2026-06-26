# Unit Tests

Fast, isolated tests for accounting logic, import validation, and privacy helpers. No HTTP layer — services and support classes are resolved from the container or called directly.

Run all unit tests:

```bash
php artisan test --testsuite=Unit
```

## Required test environment

`phpunit.xml` sets `ACCOUNTING_PATIENT_REFERENCE_HMAC_KEY` for privacy tests. Do not rely on `APP_KEY` for patient hashing.

## Test map

| File | What it verifies |
|---|---|
| `PaymentCalculationServiceTest` | TOTAL = DHS + USD→AED + VISA |
| `TreatmentParserServiceTest` | Regex/tooth-notation parsing (`ZIR x 2`, `MC CR 8765\|5678`, aliases) |
| `TreatmentImportValidationServiceTest` | Import validation warnings + work_item persistence |
| `LabJobCalculationServiceTest` | JOB = quantity × lab price (e.g. ZIR × 4 for Dr Riyad = 1600 AED) |
| `LabPriceResolverTest` | Doctor-specific vs default lab prices (incl. REMOV 100 AED) |
| `LabPriceAdministrationWorkflowTest` | End-to-end lab price create, duplicate, validity, overlap, resolver |
| `LabPriceManagementServiceTest` | Lab price CRUD, overlap guard, duplicate/activate |
| `DoctorFixedFeeAdministrationWorkflowTest` | End-to-end fixed fee create, duplicate, validity, overlap, resolver |
| `DoctorFixedFeeManagementServiceTest` | Fixed fee CRUD, overlap guard, duplicate/activate |
| `DoctorFixedFeeResolverTest` | Active fee resolution by doctor/treatment/date |
| `ConfigurationDashboardServiceTest` | Dashboard stats, audit activity, health warnings |
| `ClinicManagementServiceTest` | Clinic CRUD, audit logging, soft deactivate/activate |
| `LabCostTreatmentCatalogTest` | Which codes generate JOB vs clinical-only |
| `NonLabTreatmentJobTest` | CF/SXP/RCT persist as work_items but never create lab_jobs |
| `PatientPrivacyTest` | HMAC hash + PII stripped from `raw_data_json` |
| `MonthlyIncomeCalculationServiceTest` | Monthly NET, doctor/clinic income formulas |
| `IncomeSheetColumnMapTest` | Doctor export profile column letters from DB |
| `DoctorLabelNormalizerTest` | Excel doctor label → code guess |
| `ReportMonthResolverTest` | Month anchor from filename / work dates |

## Import pipeline coverage

```
ExcelDailyReportParser          → (Feature / manual import tests)
TreatmentParserService::parse   → TreatmentParserServiceTest
TreatmentImportValidationService → TreatmentImportValidationServiceTest
LabJobCalculationService        → LabJobCalculationServiceTest + NonLabTreatmentJobTest
PatientReferenceHasher          → PatientPrivacyTest
```

## Conventions

- Call `$this->seedAccountingData()` in `setUp()` when tests need doctors, treatments, or lab prices.
- Use `bcmath`-safe string amounts (`'1600.00'`) in assertions.
- Financial accuracy tests must stay green when changing import or parser behaviour.
