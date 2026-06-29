<?php

namespace App\Services\Analytics;

use App\DTOs\Analytics\ClinicFinancialOverviewData;
use App\DTOs\Analytics\FinancialKpiData;
use App\DTOs\Analytics\MonthlyRevenueData;
use App\DTOs\Analytics\TreatmentRevenueData;
use App\Enums\LabJobStatus;
use App\Enums\ReportStatus;
use App\Models\DailyReport;
use App\Models\LabJob;
use App\Models\Payment;
use App\Services\Accounting\Concerns\ScopesAccountingQueries;
use App\Services\Configuration\CurrentClinicResolver;
use App\Support\Analytics\FinancialPeriod;
use App\Support\Analytics\MonthOverMonthComparison;
use App\Support\ClinicCurrencySupport;
use App\Support\MoneyCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Server-side clinic financial overview (ADR-036).
 *
 * Revenue = SUM(payments.amount_aed) for work rows in period (ADR-001).
 * Lab cost = SUM(lab_jobs.total_cost_aed) for calculated/adjusted jobs (ADR-002).
 */
class ClinicFinancialOverviewService
{
    use ScopesAccountingQueries;

    public function __construct(
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    public function build(?string $month = null): ClinicFinancialOverviewData
    {
        $clinic = $this->currentClinicResolver->resolve();
        $timezone = $clinic->timezone ?: config('app.timezone', 'UTC');
        $period = $month !== null
            ? FinancialPeriod::fromMonth($month, $timezone)
            : FinancialPeriod::currentMonth($timezone);

        $previous = $period->previous();
        $currency = ClinicCurrencySupport::baseCurrency($clinic);

        $revenueCurrent = $this->sumRevenue($period);
        $revenuePrevious = $this->sumRevenue($previous);
        $labCurrent = $this->sumLabCost($period);
        $labPrevious = $this->sumLabCost($previous);
        $resultCurrent = MoneyCalculator::subtract($revenueCurrent, $labCurrent);
        $resultPrevious = MoneyCalculator::subtract($revenuePrevious, $labPrevious);

        $revenueTrend = $this->buildRevenueTrend($period, $currency);
        $topTreatments = $this->buildTopTreatments($period);
        $reportMeta = $this->reportMetadata($period);

        $hasData = bccomp($revenueCurrent, '0', 2) !== 0
            || bccomp($labCurrent, '0', 2) !== 0
            || $reportMeta['count'] > 0;

        return new ClinicFinancialOverviewData(
            selectedMonth: $period->label,
            currency: $currency,
            revenue: $this->kpi($revenueCurrent, $revenuePrevious),
            labCost: $this->kpi($labCurrent, $labPrevious),
            calculatedResult: $this->kpi($resultCurrent, $resultPrevious),
            revenueTrend: $revenueTrend,
            topTreatments: $topTreatments,
            hasData: $hasData,
            reportCount: $reportMeta['count'],
            latestImportFileName: $reportMeta['latest_file'],
            dataStandLabel: $this->dataStandLabel($reportMeta),
        );
    }

    private function kpi(string $current, string $previous): FinancialKpiData
    {
        $comparison = MonthOverMonthComparison::calculate($current, $previous);

        return new FinancialKpiData(
            amount: $current,
            comparisonLabel: $comparison['percent'] ?? MonthOverMonthComparison::UNAVAILABLE,
            comparisonDirection: $comparison['direction'] ?? null,
        );
    }

    private function sumRevenue(FinancialPeriod $period): string
    {
        $total = $this->paymentsInPeriodQuery($period)
            ->sum('payments.amount_aed');

        return $this->decimal($total);
    }

    private function sumLabCost(FinancialPeriod $period): string
    {
        $total = $this->labJobsInPeriodQuery($period)
            ->sum('lab_jobs.total_cost_aed');

        return $this->decimal($total);
    }

    /**
     * @return list<MonthlyRevenueData>
     */
    private function buildRevenueTrend(FinancialPeriod $selected, string $currency): array
    {
        $periods = $selected->trailingMonths(6);
        $amounts = [];

        foreach ($periods as $period) {
            $amounts[$period->label] = $this->sumRevenue($period);
        }

        $max = '0.00';
        foreach ($amounts as $amount) {
            if (bccomp($amount, $max, 2) > 0) {
                $max = $amount;
            }
        }

        $trend = [];
        foreach ($periods as $period) {
            $revenue = $amounts[$period->label];
            $barPercent = bccomp($max, '0', 2) === 0
                ? 0
                : (int) min(100, (int) round((float) bcmul(bcdiv($revenue, $max, 6), '100', 2)));

            $trend[] = new MonthlyRevenueData(
                month: $period->label,
                label: $period->start->format('M Y'),
                revenue: $revenue,
                barPercent: $barPercent,
            );
        }

        return $trend;
    }

    /**
     * @return list<TreatmentRevenueData>
     */
    private function buildTopTreatments(FinancialPeriod $period): array
    {
        $rows = DB::table('daily_work_rows as dwr')
            ->join('daily_reports as dr', 'dwr.daily_report_id', '=', 'dr.id')
            ->where('dwr.clinic_id', $this->currentClinicId())
            ->where('dr.status', '!=', ReportStatus::Failed->value)
            ->whereBetween('dwr.work_date', [
                $period->start->toDateString(),
                $period->end->toDateString(),
            ])
            ->where('dwr.paid_total_aed', '>', 0)
            ->select(['dwr.id', 'dwr.paid_total_aed'])
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $rowIds = $rows->pluck('id')->all();
        $paidByRow = $rows->pluck('paid_total_aed', 'id');

        $items = DB::table('work_items as wi')
            ->join('treatments as t', 'wi.treatment_id', '=', 't.id')
            ->where('wi.clinic_id', $this->currentClinicId())
            ->whereIn('wi.daily_work_row_id', $rowIds)
            ->select([
                'wi.daily_work_row_id',
                'wi.treatment_id',
                'wi.quantity',
                't.code',
                't.name',
            ])
            ->get();

        $quantitiesByRow = [];
        foreach ($items as $item) {
            $quantitiesByRow[$item->daily_work_row_id] = ($quantitiesByRow[$item->daily_work_row_id] ?? 0) + (int) $item->quantity;
        }

        /** @var array<int, array{code: string, name: string, revenue: string}> $byTreatment */
        $byTreatment = [];

        foreach ($items as $item) {
            $rowTotalQty = $quantitiesByRow[$item->daily_work_row_id] ?? 0;
            if ($rowTotalQty <= 0) {
                continue;
            }

            $rowPaid = $this->decimal($paidByRow[$item->daily_work_row_id] ?? '0');
            if (bccomp($rowPaid, '0', 2) === 0) {
                continue;
            }

            $share = bcdiv((string) $item->quantity, (string) $rowTotalQty, 8);
            $allocated = bcmul($rowPaid, $share, 2);
            $treatmentId = (int) $item->treatment_id;

            if (! isset($byTreatment[$treatmentId])) {
                $byTreatment[$treatmentId] = [
                    'code' => (string) $item->code,
                    'name' => (string) $item->name,
                    'revenue' => '0.00',
                ];
            }

            $byTreatment[$treatmentId]['revenue'] = MoneyCalculator::add(
                $byTreatment[$treatmentId]['revenue'],
                $allocated,
            );
        }

        uasort($byTreatment, function (array $a, array $b): int {
            $cmp = bccomp($b['revenue'], $a['revenue'], 2);

            return $cmp !== 0 ? $cmp : strcmp($a['name'], $b['name']);
        });

        $top = [];
        foreach (array_slice($byTreatment, 0, 5, true) as $treatmentId => $row) {
            $top[] = new TreatmentRevenueData(
                treatmentId: $treatmentId,
                code: $row['code'],
                name: $row['name'],
                revenue: $row['revenue'],
            );
        }

        return $top;
    }

    /**
     * @return array{count: int, latest_file: ?string}
     */
    private function reportMetadata(FinancialPeriod $period): array
    {
        $monthAnchor = $period->start->copy()->startOfMonth()->toDateString();

        $reports = $this->forCurrentClinic(DailyReport::class)
            ->where('report_date', $monthAnchor)
            ->where('status', '!=', ReportStatus::Failed)
            ->orderByDesc('updated_at')
            ->get(['source_file_name', 'updated_at']);

        return [
            'count' => $reports->count(),
            'latest_file' => $reports->first()?->source_file_name,
        ];
    }

    /**
     * @param  array{count: int, latest_file: ?string}  $meta
     */
    private function dataStandLabel(array $meta): ?string
    {
        if ($meta['count'] === 0) {
            return null;
        }

        if ($meta['latest_file'] !== null) {
            return sprintf(
                'Based on %d imported report(s); latest: %s',
                $meta['count'],
                $meta['latest_file'],
            );
        }

        return sprintf('Based on %d imported report(s)', $meta['count']);
    }

    private function paymentsInPeriodQuery(FinancialPeriod $period)
    {
        return Payment::query()
            ->where('payments.clinic_id', $this->currentClinicId())
            ->join('daily_work_rows as dwr', 'payments.daily_work_row_id', '=', 'dwr.id')
            ->join('daily_reports as dr', 'dwr.daily_report_id', '=', 'dr.id')
            ->where('dr.status', '!=', ReportStatus::Failed->value)
            ->whereBetween('dwr.work_date', [
                $period->start->toDateString(),
                $period->end->toDateString(),
            ]);
    }

    private function labJobsInPeriodQuery(FinancialPeriod $period)
    {
        return LabJob::query()
            ->where('lab_jobs.clinic_id', $this->currentClinicId())
            ->join('work_items as wi', 'lab_jobs.work_item_id', '=', 'wi.id')
            ->join('daily_work_rows as dwr', 'wi.daily_work_row_id', '=', 'dwr.id')
            ->join('daily_reports as dr', 'dwr.daily_report_id', '=', 'dr.id')
            ->where('dr.status', '!=', ReportStatus::Failed->value)
            ->whereIn('lab_jobs.status', [
                LabJobStatus::Calculated->value,
                LabJobStatus::Adjusted->value,
            ])
            ->whereBetween('dwr.work_date', [
                $period->start->toDateString(),
                $period->end->toDateString(),
            ]);
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
