<?php

namespace App\DTOs\Analytics;

/**
 * Single KPI with optional month-over-month comparison (ADR-036).
 */
readonly class FinancialKpiData
{
    public function __construct(
        public string $amount,
        public ?string $comparisonLabel,
        public ?string $comparisonDirection,
    ) {}
}
