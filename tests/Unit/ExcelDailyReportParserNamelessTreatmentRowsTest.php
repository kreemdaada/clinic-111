<?php

namespace Tests\Unit;

use App\Services\Import\ExcelDailyReportParser;
use Carbon\Carbon;
use Tests\Support\NamelessTreatmentRowsFixtureBuilder;
use Tests\TestCase;

class ExcelDailyReportParserNamelessTreatmentRowsTest extends TestCase
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

    public function test_nameless_treatment_with_payment_is_collected_as_activity(): void
    {
        $rows = $this->parseFixture(
            NamelessTreatmentRowsFixtureBuilder::singleNamelessActivityWithPaymentWorkbook(),
        );

        $jackRows = $this->rowsForDoctor($rows, 'jack');
        $this->assertCount(1, $jackRows);

        $jack = $jackRows[0];
        $this->assertSame(100.0, (float) $jack['dhs_amount']);
        $this->assertSame(500.0, (float) $jack['visa_amount']);
        $this->assertStringContainsString('CF x 1', (string) $jack['treatment_text']);
        $this->assertStringContainsString('PAID BALANCE', (string) $jack['treatment_text']);
    }

    public function test_nameless_treatment_without_payment_remains_in_section(): void
    {
        $rows = $this->parseFixture(
            NamelessTreatmentRowsFixtureBuilder::namelessActivityWithoutPaymentWorkbook(),
        );

        $jackRows = $this->rowsForDoctor($rows, 'jack');
        $this->assertCount(1, $jackRows);

        $jack = $jackRows[0];
        $this->assertStringContainsString('POST X 2', (string) $jack['treatment_text']);
        $this->assertStringContainsString('EXO x 1', (string) $jack['treatment_text']);
        $this->assertSame(350.0, (float) $jack['dhs_amount']);
    }

    public function test_empty_treatment_row_with_totals_closes_section(): void
    {
        $rows = $this->parseFixture(
            NamelessTreatmentRowsFixtureBuilder::emptyTreatmentSubtotalClosesSectionWorkbook(),
        );

        $jackRows = $this->rowsForDoctor($rows, 'jack');
        $this->assertCount(1, $jackRows);

        $jack = $jackRows[0];
        $this->assertSame(250.0, (float) $jack['visa_amount']);
        $this->assertStringContainsString('RCF x 1', (string) $jack['treatment_text']);
    }

    public function test_tag_26_emits_one_jack_subtotal(): void
    {
        $rows = $this->parseFixture(
            NamelessTreatmentRowsFixtureBuilder::tag26NamelessActivityRowsWorkbook(),
        );

        $jackRows = $this->rowsForDoctor($rows, 'jack');
        $this->assertCount(1, $jackRows);

        $jack = $jackRows[0];
        $this->assertSame(4650.0, (float) $jack['dhs_amount']);
        $this->assertSame(14150.0, (float) $jack['visa_amount']);
        $this->assertSame(18800.0, $this->dailyTotal($jack));

        foreach ([
            'PAID BALANCE',
            'REMOV x 2',
            'RCF x 1',
            'MC x 2',
            'TRANSFER FROM DR POURIA',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, (string) $jack['treatment_text']);
        }
    }

    public function test_tag_26_does_not_emit_multiple_empty_subtotals(): void
    {
        $result = $this->parseWithDiagnostics(
            NamelessTreatmentRowsFixtureBuilder::tag26NamelessActivityRowsWorkbook(),
        );

        $jackRows = $this->rowsForDoctor($result['rows'], 'jack');
        $this->assertCount(1, $jackRows);
        $this->assertNotSame('', trim((string) ($jackRows[0]['treatment_text'] ?? '')));

        $emptyJackEvents = array_values(array_filter(
            $result['events'],
            fn (array $event): bool => ($event['status'] ?? '') === 'extracted'
                && str_contains(strtolower((string) ($event['doctor_label'] ?? '')), 'jack')
                && trim((string) ($event['treatment_text'] ?? '')) === ''
                && (float) ($event['dhs_aed'] ?? 0) === 0.0
                && (float) ($event['visa_aed'] ?? 0) === 0.0,
        ));

        $this->assertCount(0, $emptyJackEvents, 'Parser must not emit multiple empty Jack subtotals.');
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
}
