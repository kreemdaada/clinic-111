# Database Seeders

Reference data for the accounting engine. Run via:

```bash
php artisan migrate --seed
```

Order is defined in `DatabaseSeeder` — labs and doctors must exist before prices and export profiles.

## Seeder order

| Seeder | Purpose |
|---|---|
| `ClinicSeeder` | `CLINIC_111` — default tenant (ADR-026) |
| `LabSeeder` | `MAIN_LAB`, `LAB_NAME2` |
| `DoctorSeeder` | NAME1, NAME2, NAME3, NAME4 with commission settings |
| `TreatmentSeeder` | Treatment catalog; `has_lab_cost` flag drives lab_jobs |
| `LabPriceSeeder` | Default lab unit costs + Dr. Name2 overrides |
| `DoctorLabBillingSeeder` | Per-doctor JOB rules (Name3: MC/ZIR/POST/REMOV; Name4: none) |
| `DoctorFixedFeeSeeder` | Dr. Name4: IMPL 500 AED, BG 200 USD, SINUS 300 USD |
| `DoctorIncomeExportProfileSeeder` | Original Income Excel sheet/column layout per doctor |
| `UserSeeder` | admin, accountant, viewer test users |

## Treatment categories (seeded)

**With lab cost (JOB):** MC, ZIR, IMPL-CR, IMPL-ZIR, VENEER, POST, ABT, IMPL, **REMOV (100 AED)**

**Without lab cost (work_item only):** CF, RCF, SXP, AF, RCT, RE-RCT, REPAIR, EXO, APICO, BG, SINUS, …

All valid parsed treatments create `work_items`. `lab_jobs` require `has_lab_cost` **and** a `doctor_lab_billings` row with `bill_lab_job = true`.

## Lab price highlights

| Code | Default | Dr. Name2 |
|---|---|---|
| MC | 105 AED | — |
| ZIR | 360 AED | 400 AED |
| IMPL-ZIR | 460 AED | 500 AED |
| REMOV | 100 AED | — |

Source of truth: `LabPriceSeeder.php` and `TreatmentSeeder.php`.
