<?php

namespace App\Support\Analytics;

use App\Support\MoneyCalculator;

/**
 * Month-over-month percentage change with safe handling of edge cases (ADR-036).
 */
final class MonthOverMonthComparison
{
    public const UNAVAILABLE = 'No comparison available';

    /**
     * @return array{percent: string, direction: string}|null
     */
    public static function calculate(string $current, string $previous): ?array
    {
        if (bccomp($previous, '0', 2) === 0) {
            return null;
        }

        $diff = MoneyCalculator::subtract($current, $previous);
        $ratio = bcdiv($diff, $previous, 8);
        $percent = bcmul($ratio, '100', 1);

        $direction = match (bccomp($percent, '0', 1)) {
            1 => 'up',
            -1 => 'down',
            default => 'flat',
        };

        $sign = bccomp($percent, '0', 1) > 0 ? '+' : '';

        return [
            'percent' => $sign.$percent.'%',
            'direction' => $direction,
        ];
    }
}
