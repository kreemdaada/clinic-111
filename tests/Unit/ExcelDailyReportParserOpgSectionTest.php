<?php

namespace Tests\Unit;

use App\Services\Import\ExcelDailyReportParser;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\OpgSectionImportFixtureBuilder;
use Tests\TestCase;

class ExcelDailyReportParserOpgSectionTest extends TestCase
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

    public function test_recognizes_plain_opg_as_opg_normal(): void
    {
        $rows = $this->parseFixture(OpgSectionImportFixtureBuilder::tag3OpgDhsWorkbook());

        $opgRows = $this->opgRows($rows);
        $this->assertCount(1, $opgRows);
        $this->assertStringContainsString('OPG_NORMAL', (string) $opgRows[0]['treatment_text']);
    }

    public function test_recognizes_opg_with_suffix_as_opg_normal(): void
    {
        $rows = $this->parseFixture(OpgSectionImportFixtureBuilder::tag3OpgDhsWorkbook());
        $opg = $this->opgRows($rows)[0];

        $this->assertSame(200.0, (float) $opg['dhs_amount']);
        $this->assertStringContainsString('x 1', (string) $opg['treatment_text']);
    }

    public function test_recognizes_opg_3d_before_generic_opg(): void
    {
        $rows = $this->parseFixture(OpgSectionImportFixtureBuilder::opg3dBeforeNormalWorkbook());
        $opg = $this->opgRows($rows)[0];

        $this->assertStringContainsString('OPG_3D', (string) $opg['treatment_text']);
        $this->assertSame(360.0, (float) $opg['dhs_amount']);
    }

    public function test_uses_header_based_visa_column(): void
    {
        $rows = $this->parseFixture(OpgSectionImportFixtureBuilder::tag19OpgVisaInColumnKWorkbook());
        $opg = $this->opgRows($rows)[0];

        $this->assertSame(200.0, (float) $opg['visa_amount']);
        $this->assertSame(0.0, (float) ($opg['dhs_amount'] ?? 0));
    }

    public function test_does_not_assign_opg_to_previous_doctor(): void
    {
        $rows = $this->parseFixture(OpgSectionImportFixtureBuilder::doctorThenOpgIsolationWorkbook());

        $jackRows = $this->rowsForDoctor($rows, 'jack');
        $this->assertCount(1, $jackRows);
        $this->assertStringContainsString('CF x 1', (string) $jackRows[0]['treatment_text']);
        $this->assertSame(100.0, (float) $jackRows[0]['dhs_amount']);

        $opgRows = $this->opgRows($rows);
        $this->assertCount(1, $opgRows);
        $this->assertSame(200.0, (float) $opgRows[0]['visa_amount']);
    }

    public function test_does_not_silently_drop_opg_payment(): void
    {
        $result = $this->parseWithDiagnostics(OpgSectionImportFixtureBuilder::tag30OpgNurseInTreatmentTextWorkbook());

        $this->assertCount(1, $this->opgRows($result['rows']));
        $this->assertSame(0, count(array_filter(
            $result['events'],
            fn (array $event): bool => ($event['reason'] ?? '') === 'special_section',
        )));
    }

    public function test_extracts_optional_nurse_alias(): void
    {
        $rows = $this->parseFixture(OpgSectionImportFixtureBuilder::tag30OpgNurseInTreatmentTextWorkbook());
        $opg = $this->opgRows($rows)[0];

        $this->assertSame('Jiji', $opg['opg_treatment_nurse_alias'] ?? null);
    }

    public function test_defaults_quantity_to_one(): void
    {
        $rows = $this->parseFixture(OpgSectionImportFixtureBuilder::tag19OpgVisaInColumnKWorkbook());
        $opg = $this->opgRows($rows)[0];

        $this->assertStringEndsWith('x 1', (string) $opg['treatment_text']);
    }

    public function test_reads_explicit_quantity(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('5');
        $sheet->setCellValue('G2', 'DR Jack');
        $sheet->fromArray([['Date', 'Name', 'MRN', 'File', 'Total Cost', 'Disc.', 'Treatment', 'Dhs', '$/Euro', 'Visa', 'crown']], null, 'A3');
        $sheet->setCellValue('G8', 'OPG');
        $sheet->fromArray([['Date', 'Name', 'MRN', 'File', 'Total Cost', 'Disc.', 'Treatment', 'Dhs', '$/Euro', 'Visa', 'crown']], null, 'A9');
        $sheet->setCellValue('G10', 'OPG x 2');
        $sheet->setCellValue('H10', 400);
        $file = tempnam(sys_get_temp_dir(), 'opg-qty-').'.xlsx';
        (new Xlsx($spreadsheet))->save($file);
        $this->tempFiles[] = $file;

        $opg = $this->opgRows($this->parser->parse($file, Carbon::parse('2026-06-01')))[0];

        $this->assertStringContainsString('x 2', (string) $opg['treatment_text']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function opgRows(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => (bool) ($row['is_opg_section'] ?? false),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseFixture(string $filePath): array
    {
        $this->tempFiles[] = $filePath;

        return $this->parser->parse($filePath, Carbon::parse('2026-06-01'));
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>}
     */
    private function parseWithDiagnostics(string $filePath): array
    {
        $this->tempFiles[] = $filePath;

        return $this->parser->parseWithDiagnostics($filePath, Carbon::parse('2026-06-01'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rowsForDoctor(array $rows, string $needle): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => ! ($row['is_opg_section'] ?? false)
                && str_contains(strtolower((string) ($row['doctor'] ?? '')), strtolower($needle)),
        ));
    }
}
