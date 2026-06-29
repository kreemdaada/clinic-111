<?php

namespace App\Support\Analytics;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Calendar month boundaries in a clinic timezone (ADR-036).
 */
readonly class FinancialPeriod
{
    public function __construct(
        public CarbonInterface $start,
        public CarbonInterface $end,
        public string $label,
    ) {}

    public static function fromMonth(string $month, string $timezone): self
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new InvalidArgumentException('Month must be in YYYY-MM format.');
        }

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01', $timezone)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        return new self($start, $end, $month);
    }

    public static function currentMonth(string $timezone): self
    {
        return self::fromMonth(Carbon::now($timezone)->format('Y-m'), $timezone);
    }

    public function previous(): self
    {
        $previousStart = $this->start->copy()->subMonth();

        return self::fromMonth($previousStart->format('Y-m'), $this->start->getTimezone()->getName());
    }

    /**
     * @return list<self> Six months ending with this period (inclusive), oldest first.
     */
    public function trailingMonths(int $count = 6): array
    {
        $periods = [];
        $cursor = $this->start->copy()->subMonths($count - 1);

        for ($i = 0; $i < $count; $i++) {
            $periods[] = self::fromMonth($cursor->format('Y-m'), $this->start->getTimezone()->getName());
            $cursor = $cursor->copy()->addMonth();
        }

        return $periods;
    }
}
