<?php

namespace Tests\Unit;

use App\Services\Import\ExcelDailyReportParser;
use Carbon\Carbon;
use Tests\Support\StaleSectionAnchorFixtureBuilder;
use Tests\TestCase;

class ExcelDailyReportParserStaleSectionTest extends TestCase
{
    private ExcelDailyReportParser $parser;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = app(ExcelDailyReportParser::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }

        parent::tearDown();
    }

    public function test_template_year_anchor_on_matching_sheet_day_is_imported(): void
    {
        $result = $this->parseWithDiagnostics(
            StaleSectionAnchorFixtureBuilder::tag22JackTemplateYearWorkbook(),
        );

        $jackRows = $this->rowsForDoctor($result['rows'], 'jack');
        $this->assertCount(1, $jackRows);

        $jack = $jackRows[0];
        $this->assertSame(1500.0, (float) $jack['dhs_amount']);
        $this->assertSame(9450.0, (float) $jack['visa_amount']);
        $this->assertSame(10950.0, $this->dailyTotal($jack));
        $this->assertCount(0, $this->staleEvents($result['events']));
    }

    public function test_current_year_anchor_remains_imported(): void
    {
        $result = $this->parseWithDiagnostics(
            StaleSectionAnchorFixtureBuilder::currentYearAnchorWorkbook(),
        );

        $rows = $this->rowsForDoctor($result['rows'], 'beta');
        $this->assertCount(1, $rows);
        $this->assertSame(250.0, (float) $rows[0]['dhs_amount']);
        $this->assertCount(0, $this->staleEvents($result['events']));
    }

    public function test_mismatched_anchor_month_day_remains_stale_section(): void
    {
        $result = $this->parseWithDiagnostics(
            StaleSectionAnchorFixtureBuilder::mismatchedAnchorMonthDayWorkbook(),
        );

        $this->assertCount(0, $this->rowsForDoctor($result['rows'], 'alpha'));
        $this->assertCount(1, $this->staleEvents($result['events']));
    }

    public function test_without_report_month_existing_behavior_is_unchanged(): void
    {
        $filePath = StaleSectionAnchorFixtureBuilder::templateYearWithoutReportMonthWorkbook();
        $this->tempFiles[] = $filePath;

        $rows = $this->parser->parse($filePath, null);

        $gammaRows = $this->rowsForDoctor($rows, 'gamma');
        $this->assertCount(1, $gammaRows);
        $this->assertSame(300.0, (float) $gammaRows[0]['dhs_amount']);
    }

    public function test_unparseable_anchor_date_does_not_throw(): void
    {
        $result = $this->parseWithDiagnostics(
            StaleSectionAnchorFixtureBuilder::unparseableAnchorDateWorkbook(),
        );

        $rows = $this->rowsForDoctor($result['rows'], 'delta');
        $this->assertCount(1, $rows);
        $this->assertSame(180.0, (float) $rows[0]['dhs_amount']);
        $this->assertCount(0, $this->staleEvents($result['events']));
    }

    public function test_tag_22_jack_fixture_is_imported_without_stale_section(): void
    {
        $result = $this->parseWithDiagnostics(
            StaleSectionAnchorFixtureBuilder::tag22JackTemplateYearWorkbook(),
        );

        $jackRows = $this->rowsForDoctor($result['rows'], 'jack');
        $this->assertCount(1, $jackRows);
        $this->assertSame(1500.0, (float) $jackRows[0]['dhs_amount']);
        $this->assertSame(9450.0, (float) $jackRows[0]['visa_amount']);
        $this->assertCount(0, $this->staleEvents($result['events']));
    }

    public function test_tag_22_pouria_fixture_is_imported_with_cf_treatment(): void
    {
        $result = $this->parseWithDiagnostics(
            StaleSectionAnchorFixtureBuilder::tag22PouriaTemplateYearWorkbook(),
        );

        $pouriaRows = $this->rowsForDoctor($result['rows'], 'pouria');
        $this->assertCount(1, $pouriaRows);
        $this->assertSame(350.0, (float) $pouriaRows[0]['dhs_amount']);
        $this->assertStringContainsString('CF x 1', (string) $pouriaRows[0]['treatment_text']);
        $this->assertCount(0, $this->staleEvents($result['events']));
    }

    public function test_tag_22_riyadh_control_remains_imported(): void
    {
        $rows = $this->parseFixture(
            StaleSectionAnchorFixtureBuilder::tag22RiyadhControlWorkbook(),
        );

        $riyadhRows = $this->rowsForDoctor($rows, 'riyadh');
        $this->assertCount(1, $riyadhRows);

        $riyadh = $riyadhRows[0];
        $this->assertSame(11250.0, (float) $riyadh['dhs_amount']);
        $this->assertSame(350.0, (float) $riyadh['visa_amount']);
        $this->assertSame(11600.0, $this->dailyTotal($riyadh));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function dailyTotal(array $row): float
    {
        return (float) ($row['dhs_amount'] ?? 0)
            + (float) ($row['visa_amount'] ?? 0)
            + (float) ($row['usd_amount'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseFixture(string $filePath): array
    {
        $this->tempFiles[] = $filePath;

        return $this->parser->parse($filePath, Carbon::parse('2027-06-01'));
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>}
     */
    private function parseWithDiagnostics(string $filePath): array
    {
        $this->tempFiles[] = $filePath;

        return $this->parser->parseWithDiagnostics($filePath, Carbon::parse('2027-06-01'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rowsForDoctor(array $rows, string $doctorNeedle): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => str_contains(
                strtolower((string) ($row['doctor'] ?? '')),
                strtolower($doctorNeedle),
            ),
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    private function staleEvents(array $events): array
    {
        return array_values(array_filter(
            $events,
            fn (array $event): bool => ($event['reason'] ?? '') === 'stale_section',
        ));
    }
}
