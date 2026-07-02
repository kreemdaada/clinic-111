<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\DailyReport;
use App\Models\DailyReportImportWarning;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\WorkItem;
use App\Services\Import\DailyReportImportService;
use App\Services\Import\ImportExtractionLogService;
use App\Support\AccountingScopedQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\ImportExcelFixtureBuilder;
use Tests\TestCase;

/**
 * Additional end-to-end characterization tests for {@see DailyReportImportService::import()}.
 */
class ImportPipelineCharacterizationExtendedTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    private DailyReportImportService $importService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->authenticateAdmin();
        Storage::fake('local');
        config(['accounting.upload.delete_after_import' => false]);

        $this->importService = app(DailyReportImportService::class);
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

    public function test_parser_failure_inside_import_transaction_rolls_back_daily_report(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'empty-import-').'.xlsx';
        $this->tempFiles[] = $path;

        try {
            $this->importFixture($path);
            $this->fail('Expected import to throw for empty workbook.');
        } catch (\Throwable) {
            $this->assertSame(0, DailyReport::query()->count());
            $this->assertSame(0, DailyWorkRow::query()->count());
        }
    }

    public function test_non_workbook_bytes_may_still_create_empty_daily_report(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'import-bad-').'.xlsx';
        file_put_contents($path, 'not-a-valid-workbook');
        $this->tempFiles[] = $path;

        $report = $this->importFixture($path);

        $this->assertSame(1, DailyReport::query()->count());
        $this->assertSame(0, DailyWorkRow::query()->where('daily_report_id', $report->id)->count());
    }

    public function test_import_without_month_in_filename_throws_before_persistence(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook();
        $this->tempFiles[] = $fixture;

        try {
            $this->importFixture($fixture, 'daily-report-no-month.xlsx');
            $this->fail('Expected month resolution failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Could not detect the report month', $exception->getMessage());
            $this->assertSame(0, DailyReport::query()->count());
        }
    }

    public function test_import_merges_duplicate_treatment_codes_in_one_row(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            treatmentText: 'ZIR x 1 | ZIR x 1',
            payments: ['dhs' => 200.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $workRow = AccountingScopedQuery::workRows((int) $report->clinic_id, $report->id)->firstOrFail();
        $workItems = WorkItem::query()->where('daily_work_row_id', $workRow->id)->get();

        $this->assertCount(1, $workItems);
        $this->assertSame(2, $workItems->first()->quantity);
        $this->assertSame('ZIR', $workItems->first()->treatment->code);
    }

    public function test_inactive_treatment_code_emits_unknown_treatment_warning(): void
    {
        $clinicId = $this->clinic111()->id;
        $treatment = Treatment::query()->create([
            'clinic_id' => $clinicId,
            'code' => 'INACTV',
            'name' => 'Inactive Treatment',
            'has_lab_cost' => false,
        ]);
        $treatment->is_active = false;
        $treatment->save();

        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            treatmentText: 'INACTV x 1',
            payments: ['dhs' => 90.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $workRow = AccountingScopedQuery::workRows((int) $report->clinic_id, $report->id)->firstOrFail();

        $this->assertSame(0, WorkItem::query()->where('daily_work_row_id', $workRow->id)->count());

        $warning = DailyReportImportWarning::query()
            ->where('daily_report_id', $report->id)
            ->where('warning_code', 'unknown_treatment_code')
            ->first();

        $this->assertNotNull($warning);
        $this->assertSame(ReportStatus::NeedsReview, $report->fresh()->status);
    }

    public function test_doctor_specific_lab_price_overrides_default_for_riyad(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            doctorLabel: 'DR Riyad',
            treatmentText: 'ZIR x 1',
            payments: ['dhs' => 150.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $workItem = WorkItem::query()
            ->whereHas('dailyWorkRow', fn ($query) => $query->where('daily_report_id', $report->id))
            ->firstOrFail();

        $labJob = $workItem->labJob;
        $this->assertNotNull($labJob);
        $this->assertSame('400.00', (string) $labJob->unit_cost);
        $this->assertSame('400.00', (string) $labJob->total_cost_aed);
    }

    public function test_default_lab_price_is_used_for_jack(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            doctorLabel: 'DR Jack',
            treatmentText: 'ZIR x 1',
            payments: ['dhs' => 150.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $workItem = WorkItem::query()
            ->whereHas('dailyWorkRow', fn ($query) => $query->where('daily_report_id', $report->id))
            ->firstOrFail();

        $labJob = $workItem->labJob;
        $this->assertNotNull($labJob);
        $this->assertSame('360.00', (string) $labJob->unit_cost);
        $this->assertSame('360.00', (string) $labJob->total_cost_aed);
    }

    public function test_missing_lab_price_emits_warning_without_lab_job(): void
    {
        LabPrice::query()
            ->whereHas('treatment', fn ($query) => $query->where('code', 'ZIR'))
            ->update(['is_active' => false]);

        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            treatmentText: 'ZIR x 1',
            payments: ['dhs' => 120.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $workItem = WorkItem::query()
            ->whereHas('dailyWorkRow', fn ($query) => $query->where('daily_report_id', $report->id))
            ->firstOrFail();

        $this->assertNull($workItem->labJob);

        $warning = DailyReportImportWarning::query()
            ->where('daily_report_id', $report->id)
            ->where('warning_code', 'lab_price_not_found')
            ->first();

        $this->assertNotNull($warning);
        $this->assertSame(ReportStatus::NeedsReview, $report->fresh()->status);
    }

    public function test_import_does_not_attach_foreign_clinic_treatment_id(): void
    {
        $tenant222 = $this->seedClinic222Tenant();

        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            treatmentText: $tenant222['treatment']->code.' x 1',
            payments: ['dhs' => 80.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $workRow = AccountingScopedQuery::workRows((int) $report->clinic_id, $report->id)->firstOrFail();

        $this->assertSame(0, WorkItem::query()->where('daily_work_row_id', $workRow->id)->count());
        $this->assertDatabaseMissing('work_items', [
            'clinic_id' => $tenant222['clinic']->id,
            'daily_work_row_id' => $workRow->id,
        ]);
    }

    public function test_identical_doctor_codes_resolve_to_current_clinic_only(): void
    {
        $tenant222 = $this->seedClinic222Tenant();
        $clinic111Doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();

        Doctor::query()->create([
            'clinic_id' => $tenant222['clinic']->id,
            'name' => 'Dr Jack Other Clinic',
            'code' => 'JACK',
            'commission_type' => 'percentage',
            'commission_percentage' => 20,
            'is_active' => true,
        ]);

        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            doctorLabel: 'DR Jack',
            treatmentText: 'CF x 1',
            payments: ['dhs' => 50.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $workRow = AccountingScopedQuery::workRows((int) $report->clinic_id, $report->id)->firstOrFail();

        $this->assertSame($clinic111Doctor->id, $workRow->doctor_id);
        $this->assertSame($this->clinic111()->id, $workRow->clinic_id);
    }

    public function test_extraction_log_preserves_unknown_keys_across_updates(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            treatmentText: 'CF x 1',
            payments: ['dhs' => 75.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);

        $logService = app(ImportExtractionLogService::class);
        $path = $logService->getLogPath($report);
        $this->assertNotNull($path);
        $document = json_decode((string) file_get_contents($path), true);
        $document['custom_characterization_marker'] = 'preserve-me';
        file_put_contents($path, json_encode($document, JSON_THROW_ON_ERROR));

        $logService->recordReconciliationIssues([]);
        $updated = json_decode((string) file_get_contents($path), true);

        $this->assertSame('preserve-me', $updated['custom_characterization_marker'] ?? null);
        $this->assertIsArray($updated['imported_rows'] ?? null);
    }

    public function test_extraction_log_appends_multiple_imported_rows_without_overwrite(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111MultiDayWorkbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $log = app(ImportExtractionLogService::class)->loadForReport($report);

        $this->assertNotNull($log);
        $this->assertGreaterThanOrEqual(2, count($log['imported_rows']));
        $workRowIds = array_column($log['imported_rows'], 'work_row_id');
        $this->assertSame(count($workRowIds), count(array_unique($workRowIds)));
    }

    private function importFixture(string $absolutePath, string $filename = 'daily report June 2026.xlsm'): DailyReport
    {
        $uploadedFile = new UploadedFile(
            $absolutePath,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        return $this->importService->import($uploadedFile);
    }
}
