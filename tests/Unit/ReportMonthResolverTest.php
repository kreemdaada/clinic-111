<?php

namespace Tests\Unit;

use App\Support\ReportMonthResolver;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ReportMonthResolverTest extends TestCase
{
    #[Test]
    public function it_parses_month_and_year_from_filename(): void
    {
        $month = ReportMonthResolver::parseFromFilename('daily report january 2026.xlsm');

        $this->assertNotNull($month);
        $this->assertSame('2026-01-01', $month->toDateString());
    }

    #[Test]
    public function it_parses_april_from_filename(): void
    {
        $month = ReportMonthResolver::parseFromFilename('daily report April 2026.xlsm');

        $this->assertNotNull($month);
        $this->assertSame('2026-04-01', $month->toDateString());
    }

    #[Test]
    public function it_parses_numeric_year_month_from_filename(): void
    {
        $month = ReportMonthResolver::parseFromFilename('daily-report-2026-04.xlsm');

        $this->assertNotNull($month);
        $this->assertSame('2026-04-01', $month->toDateString());
    }

    #[Test]
    public function it_requires_month_in_filename(): void
    {
        $this->expectException(RuntimeException::class);

        ReportMonthResolver::requireFromFilename('report.xlsm');
    }

    #[Test]
    public function it_uses_sheet_day_for_work_date(): void
    {
        $monthStart = Carbon::createFromDate(2026, 4, 1);

        $resolved = ReportMonthResolver::resolveWorkDateForRow(
            $monthStart,
            '2025-04-07',
            6,
        );

        $this->assertSame('2026-04-06', $resolved);
    }
}
