<?php

namespace App\DTOs\Analytics;

/**
 * Aggregated clinic financial overview for one selected month (ADR-036).
 */
readonly class ClinicFinancialOverviewData
{
    /**
     * @param  list<MonthlyRevenueData>  $revenueTrend
     * @param  list<TreatmentRevenueData>  $topTreatments
     */
    public function __construct(
        public string $selectedMonth,
        public string $currency,
        public FinancialKpiData $revenue,
        public FinancialKpiData $labCost,
        public FinancialKpiData $calculatedResult,
        public array $revenueTrend,
        public array $topTreatments,
        public bool $hasData,
        public int $reportCount,
        public ?string $latestImportFileName,
        public ?string $dataStandLabel,
    ) {}
}
