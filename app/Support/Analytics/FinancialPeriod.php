<?php

namespace App\Support\Analytics;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
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

    /**
     * First instant of the month following this period (exclusive upper bound).
     */
    public function exclusiveEnd(): CarbonInterface
    {
        return $this->start->copy()->addMonth()->startOfDay();
    }

    /**
     * Apply half-open month constraint: >= period start and < next period start.
     *
     * @param  EloquentBuilder<mixed>|QueryBuilder  $query
     */
    public function applyHalfOpenDateConstraint(EloquentBuilder|QueryBuilder $query, string $column): void
    {
        $query
            ->where($column, '>=', $this->start)
            ->where($column, '<', $this->exclusiveEnd());
    }

    /**
     * Apply half-open month constraint from any month anchor.
     *
     * @param  EloquentBuilder<mixed>|QueryBuilder  $query
     */
    public static function applyHalfOpenMonthConstraint(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        CarbonInterface $monthStart,
    ): void {
        $start = $monthStart->copy()->startOfMonth()->startOfDay();
        $exclusiveEnd = $start->copy()->addMonth()->startOfDay();

        $query
            ->where($column, '>=', $start)
            ->where($column, '<', $exclusiveEnd);
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
