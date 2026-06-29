# Clinic Financial Overview

Practice-wide financial dashboard for the authenticated clinic (ADR-036).

## Purpose

Display **real** KPIs from imported accounting data:

- Total revenue (collected payments)
- Lab costs (JOB)
- Calculated result (revenue − lab costs)
- Month-over-month comparison
- Six-month revenue trend
- Top 5 treatments by allocated revenue

This is operational reporting — not tax advice or full P&L.

## Route

| URL | Name | Access |
|---|---|---|
| `/practice-overview` | `clinic.financial-overview` | `auth`, `verified`, roles: admin, accountant, viewer |

Optional query: `?month=YYYY-MM`

## Data sources

| KPI | Source | Rule |
|---|---|---|
| Revenue | `payments.amount_aed` | Join `daily_work_rows` where `work_date` in month; exclude `failed` reports (ADR-001) |
| Lab cost | `lab_jobs.total_cost_aed` | Status `calculated` or `adjusted`; linked work row in month (ADR-002) |
| Calculated result | Service | Revenue − lab cost (BCMath) |
| Top treatments | `work_items` + row `paid_total_aed` | Quantity-weighted allocation per row |
| Data stand | `daily_reports` | Count + latest `source_file_name` for report month |

## Period & timezone

- Default month = current calendar month in `clinics.timezone`
- Boundaries: start/end of month in clinic timezone

## Currency

Clinic base currency via `ClinicCurrencySupport` / `CurrencyFormatter` (ADR-034).

## Empty state

No revenue, lab cost, or reports → link to `imports.index`.

## Limits

- No doctor commissions, overhead, or taxes
- Treatment revenue is **allocated**, not invoice-level
- Does not claim “month complete”

## Related

- ADR-036 in `docs/DECISIONS.md`
- `App\Services\Analytics\ClinicFinancialOverviewService`
