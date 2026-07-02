<?php

namespace Tests\Unit;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Services\Import\ImportExtractionLogService;
use ReflectionClass;
use Tests\TestCase;

class ImportExtractionLogServiceTest extends TestCase
{
    private ImportExtractionLogService $logService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->logService = app(ImportExtractionLogService::class);
    }

    public function test_record_reconciliation_issues_initializes_imported_rows_as_array(): void
    {
        $this->logService->recordReconciliationIssues([
            ['severity' => 'error', 'type' => 'lab_without_payments', 'message' => 'test'],
        ]);

        $document = $this->document();

        $this->assertIsArray($document['imported_rows']);
        $this->assertSame([], $document['imported_rows']);
        $this->assertCount(1, $document['reconciliation_issues']);
    }

    public function test_record_calculated_row_after_reconciliation_without_imported_rows_does_not_throw(): void
    {
        $workRow = $this->makeWorkRow('CF x 1');

        $this->logService->recordReconciliationIssues([]);

        $this->logService->recordCalculatedRow($workRow);

        $document = $this->document();

        $this->assertIsArray($document['imported_rows']);
        $this->assertCount(1, $document['imported_rows']);
        $this->assertSame($workRow->id, $document['imported_rows'][0]['work_row_id']);
    }

    public function test_record_calculated_row_replaces_null_imported_rows_with_empty_array(): void
    {
        $workRow = $this->makeWorkRow('CF x 1');

        $this->logService->recordReconciliationIssues([]);
        $this->setDocumentKey('imported_rows', null);

        $this->logService->recordCalculatedRow($workRow);

        $this->assertIsArray($this->document()['imported_rows']);
        $this->assertCount(1, $this->document()['imported_rows']);
    }

    public function test_record_calculated_row_preserves_existing_imported_rows(): void
    {
        $existing = [
            'work_row_id' => 999,
            'doctor_code' => 'JACK',
            'sheet_day' => 1,
            'excel_row' => 42,
            'paid_total_aed' => '10.00',
            'treatment_text' => 'LEGACY x 1',
        ];

        $this->logService->recordReconciliationIssues([]);
        $this->setDocumentKey('imported_rows', [$existing]);

        $workRow = $this->makeWorkRow('CF x 1');
        $this->logService->recordCalculatedRow($workRow);

        $importedRows = $this->document()['imported_rows'];

        $this->assertCount(2, $importedRows);
        $this->assertSame(999, $importedRows[0]['work_row_id']);
        $this->assertSame($workRow->id, $importedRows[1]['work_row_id']);
    }

    public function test_normalize_preserves_unknown_document_keys(): void
    {
        $this->logService->recordReconciliationIssues([
            ['severity' => 'warning', 'type' => 'payment_mismatch', 'message' => 'keep me'],
        ]);
        $this->setDocumentKey('legacy_custom_key', 'preserved-value');

        $this->logService->recordCalculatedRow($this->makeWorkRow('CF x 1'));

        $document = $this->document();

        $this->assertSame('preserved-value', $document['legacy_custom_key']);
        $this->assertCount(1, $document['reconciliation_issues']);
    }

    public function test_multiple_record_calculated_row_calls_append_distinct_manual_rows(): void
    {
        $this->logService->recordReconciliationIssues([]);

        $first = $this->makeWorkRow('CF x 1', day: 5);
        $second = $this->makeWorkRow('CF x 1', day: 6);

        $this->logService->recordCalculatedRow($first);
        $this->logService->recordCalculatedRow($second);

        $importedRows = $this->document()['imported_rows'];

        $this->assertCount(2, $importedRows);
        $this->assertSame($first->id, $importedRows[0]['work_row_id']);
        $this->assertSame($second->id, $importedRows[1]['work_row_id']);
    }

    public function test_excel_import_flow_keeps_parser_rows_when_calculating(): void
    {
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'source_file_name' => 'daily report June 2026.xlsm',
            'status' => ReportStatus::Uploaded,
        ]);

        $this->logService->startReport($report, '/tmp/daily-report-june-2026.xlsm');
        $this->logService->recordParserEvents([
            [
                'status' => 'extracted',
                'doctor_label' => 'DR Jack',
                'sheet_day' => 3,
                'excel_row' => 15,
                'dhs_aed' => '100.00',
                'usd' => '0.00',
                'visa_aed' => '0.00',
                'treatment_text' => 'CF x 1',
            ],
        ]);

        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'CF')->firstOrFail();
        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-03',
            'treatment_text' => 'CF x 1',
            'dhs_amount' => '100.00',
            'paid_total_aed' => '100.00',
            'excel_row_number' => 15,
            'raw_data_json' => ['sheet_day' => 3, 'doctor' => 'JACK', 'excel_row' => 15],
        ]);
        $this->createWorkItem($workRow, [
            'treatment_id' => $treatment->id,
            'quantity' => 1,
        ]);

        $this->logService->recordCalculatedRow($workRow->fresh(['doctor', 'workItems.treatment', 'workItems.labJob']));
        $this->logService->recordReconciliationIssues([]);

        $importedRows = $this->document()['imported_rows'];
        $matchedRow = collect($importedRows)->first(fn (array $row) => ($row['work_row_id'] ?? null) === $workRow->id);

        $this->assertNotNull($matchedRow);
        $this->assertSame(15, $matchedRow['excel_row']);
        $this->assertNotEmpty($matchedRow['treatments_parsed'] ?? []);
    }

    private function makeWorkRow(string $treatmentText, int $day = 5): DailyWorkRow
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);

        return $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => sprintf('2026-06-%02d', $day),
            'treatment_text' => $treatmentText,
            'dhs_amount' => '100.00',
            'paid_total_aed' => '100.00',
            'excel_row_number' => 1,
            'raw_data_json' => [
                'sheet_day' => $day,
                'doctor' => 'JACK',
                'source' => 'manual_v2',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function document(): array
    {
        $property = (new ReflectionClass($this->logService))->getProperty('document');

        return $property->getValue($this->logService);
    }

    private function setDocumentKey(string $key, mixed $value): void
    {
        $reflection = new ReflectionClass($this->logService);
        $property = $reflection->getProperty('document');
        $document = $property->getValue($this->logService);
        $document[$key] = $value;
        $property->setValue($this->logService, $document);
    }
}
