<?php

namespace Tests\Unit;

use App\Services\Import\ExcelDailyReportParser;
use Carbon\Carbon;
use Tests\Support\HybridDoctorSubtotalFixtureBuilder;
use Tests\TestCase;

class ExcelDailyReportParserHybridDoctorSubtotalTest extends TestCase
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

    public function test_hybrid_row_emits_riyadh_subtotal_before_wael_switch(): void
    {
        $rows = $this->parseFixture(
            HybridDoctorSubtotalFixtureBuilder::riyadhHybridSubtotalWithWaelMarkerWorkbook(),
        );

        $riyadhRows = $this->rowsForDoctor($rows, 'riyadh');
        $waelRows = $this->rowsForDoctor($rows, 'wael');

        $this->assertCount(1, $riyadhRows, 'Riyadh block must be emitted exactly once.');
        $this->assertCount(0, $waelRows, 'Wael must not receive a subtotal row from the hybrid line.');

        $riyadh = $riyadhRows[0];
        $this->assertSame(1400.0, (float) $riyadh['visa_amount']);
        $this->assertStringContainsString('X-ray', (string) $riyadh['treatment_text']);
        $this->assertStringContainsString('ZIR x 1', (string) $riyadh['treatment_text']);
    }

    public function test_normal_doctor_marker_without_payments_switches_without_extra_emit(): void
    {
        $rows = $this->parseFixture(
            HybridDoctorSubtotalFixtureBuilder::normalDoctorMarkerSwitchWorkbook(),
        );

        $jackRows = $this->rowsForDoctor($rows, 'jack');
        $pouriaRows = $this->rowsForDoctor($rows, 'pouria');

        $this->assertCount(1, $jackRows);
        $this->assertSame(100.0, (float) $jackRows[0]['dhs_amount']);
        $this->assertStringContainsString('CF x 1', (string) $jackRows[0]['treatment_text']);

        $this->assertCount(1, $pouriaRows);
        $this->assertSame(250.0, (float) $pouriaRows[0]['visa_amount']);
    }

    public function test_normal_subtotal_without_doctor_marker_emits_once(): void
    {
        $rows = $this->parseFixture(
            HybridDoctorSubtotalFixtureBuilder::normalSubtotalWithoutDoctorMarkerWorkbook(),
        );

        $jackRows = $this->rowsForDoctor($rows, 'jack');

        $this->assertCount(1, $jackRows);
        $this->assertSame(350.0, (float) $jackRows[0]['dhs_amount']);
        $this->assertSame(700.0, (float) $jackRows[0]['visa_amount']);
        $this->assertStringContainsString('EXO x 1', (string) $jackRows[0]['treatment_text']);
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
}
