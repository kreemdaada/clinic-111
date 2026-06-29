<?php

namespace App\DTOs\Analytics;

/**
 * Revenue total for one calendar month in the trend chart.
 */
readonly class MonthlyRevenueData
{
    public function __construct(
        public string $month,
        public string $label,
        public string $revenue,
        public int $barPercent,
    ) {}
}
