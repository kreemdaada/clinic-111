<?php

namespace Tests\Unit;

use App\Support\Analytics\FinancialPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinancialPeriodBoundsTest extends TestCase
{
    #[DataProvider('juneBoundaryProvider')]
    public function test_june_half_open_month_boundaries(string $workDate, bool $expectedIncluded): void
    {
        $this->assertSame(
            $expectedIncluded,
            $this->isIncludedInMonth('2026-06', $workDate),
            "Boundary mismatch for {$workDate}",
        );
    }

    public static function juneBoundaryProvider(): array
    {
        return [
            'june 30 midnight' => ['2026-06-30 00:00:00', true],
            'june 30 noon' => ['2026-06-30 12:30:00', true],
            'june 30 end of day' => ['2026-06-30 23:59:59', true],
            'july 1 start' => ['2026-07-01 00:00:00', false],
            'july 31' => ['2026-07-31 00:00:00', false],
        ];
    }

    public function test_february_and_leap_year_boundaries(): void
    {
        $this->assertTrue($this->isIncludedInMonth('2026-02', '2026-02-28 00:00:00'));
        $this->assertFalse($this->isIncludedInMonth('2026-02', '2026-03-01 00:00:00'));
        $this->assertTrue($this->isIncludedInMonth('2028-02', '2028-02-29 00:00:00'));
        $this->assertFalse($this->isIncludedInMonth('2028-02', '2028-03-01 00:00:00'));
    }

    public function test_financial_period_exclusive_end_is_first_day_of_next_month(): void
    {
        $period = FinancialPeriod::fromMonth('2026-06', 'UTC');

        $this->assertSame('2026-07-01 00:00:00', $period->exclusiveEnd()->format('Y-m-d H:i:s'));
    }

    private function isIncludedInMonth(string $month, string $workDate): bool
    {
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        DB::statement('CREATE TEMP TABLE IF NOT EXISTS boundary_probe (work_date TEXT)');
        DB::table('boundary_probe')->delete();
        DB::table('boundary_probe')->insert(['work_date' => $workDate]);

        return DB::table('boundary_probe')
            ->where('work_date', '>=', $monthStart->copy()->startOfDay())
            ->where('work_date', '<', $monthStart->copy()->addMonth()->startOfDay())
            ->exists();
    }
}
