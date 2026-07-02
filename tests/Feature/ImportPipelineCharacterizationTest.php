<?php

namespace Tests\Feature;

use App\Enums\CommissionType;
use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\Clinic;
use App\Models\DailyReport;
use App\Models\DailyReportImportWarning;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\LabJob;
use App\Models\Payment;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\Import\DailyReportImportService;
use App\Services\Import\ImportExtractionLogService;
use App\Support\AccountingScopedQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImportExcelFixtureBuilder;
use Tests\TestCase;

/**
 * End-to-end characterization tests for {@see DailyReportImportService::import()}.
 */
class ImportPipelineCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private const USD_TO_AED_RATE = '3.65';

    private const RUB_TO_AED_RATE = '0.0481';

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

    public function test_full_legacy_clinic_111_import_creates_complete_accounting_chain(): void
    {
        $doctor = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            doctorLabel: 'DR Riyad',
            treatmentText: 'ZIR x 2',
            payments: [
                'dhs' => 100.0,
                'cheque' => 25.0,
                'tabby' => 10.0,
                'usd' => 10.0,
                'visa' => 50.0,
                'rub' => 1000.0,
            ],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);

        $clinicId = $this->clinic111()->id;
        $usdToAed = bcmul('10.00', self::USD_TO_AED_RATE, 2);
        $rubToAed = bcmul('1000.00', self::RUB_TO_AED_RATE, 2);
        $expectedPaidTotal = bcadd(
            bcadd(bcadd(bcadd(bcadd('100.00', '25.00', 2), '10.00', 2), $usdToAed, 2), '50.00', 2),
            $rubToAed,
            2,
        );

        $this->assertSame('269.60', $expectedPaidTotal);
        $this->assertSame($clinicId, $report->clinic_id);
        $this->assertSame(ReportSourceType::ExcelUpload, $report->source_type);
        $this->assertSame('2026-06-01', $report->report_date->toDateString());
        $this->assertContains($report->status, [ReportStatus::Calculated, ReportStatus::NeedsReview]);

        $workRows = AccountingScopedQuery::workRows($clinicId, $report->id)->get();
        $this->assertCount(1, $workRows);

        $workRow = $workRows->first();
        $this->assertSame($doctor->id, $workRow->doctor_id);
        $this->assertSame('ZIR x 2', $workRow->treatment_text);
        $this->assertSame($expectedPaidTotal, (string) $workRow->paid_total_aed);
        $this->assertSame(8, $workRow->raw_data_json['sheet_day'] ?? null);

        $payments = Payment::query()->where('daily_work_row_id', $workRow->id)->orderBy('payment_method')->get();
        $this->assertCount(5, $payments);
        $this->assertSame($clinicId, $payments->first()->clinic_id);

        $workItems = WorkItem::query()->where('daily_work_row_id', $workRow->id)->get();
        $this->assertCount(1, $workItems);
        $this->assertSame('ZIR', $workItems->first()->treatment->code);
        $this->assertSame(2, $workItems->first()->quantity);
        $this->assertSame($clinicId, $workItems->first()->clinic_id);

        $labJob = LabJob::query()->where('work_item_id', $workItems->first()->id)->first();
        $this->assertNotNull($labJob);
        $this->assertSame('800.00', (string) $labJob->total_cost_aed);
        $this->assertSame('400.00', (string) $labJob->unit_cost);
        $this->assertSame($clinicId, $labJob->clinic_id);

        $log = $this->loadExtractionLog($report);
        $this->assertIsArray($log['imported_rows']);
        $this->assertIsArray($log['skipped_rows']);
        $this->assertIsArray($log['unresolved_rows']);
        $this->assertIsArray($log['reconciliation_issues']);
        $this->assertArrayHasKey('issue_summary', $log);
        $this->assertArrayHasKey('doctor_totals', $log);
    }

    public function test_legacy_import_creates_payment_rows_with_legacy_currency_layout(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            payments: ['dhs' => 100.0, 'cheque' => 25.0, 'tabby' => 10.0, 'usd' => 10.0, 'visa' => 50.0, 'rub' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $workRow = AccountingScopedQuery::workRows((int) $report->clinic_id, $report->id)->firstOrFail();

        $payments = Payment::query()
            ->where('daily_work_row_id', $workRow->id)
            ->get()
            ->keyBy(fn (Payment $payment) => $payment->payment_method->value);

        $this->assertPaymentRow($payments['dhs'], $workRow, '100.00', 'AED', '1.0000', '100.00');
        $this->assertPaymentRow($payments['cheque'], $workRow, '25.00', 'AED', '1.0000', '25.00');
        $this->assertPaymentRow($payments['tabby'], $workRow, '10.00', 'AED', '1.0000', '10.00');
        $this->assertPaymentRow($payments['usd'], $workRow, '10.00', 'USD', '3.6500', '36.50');
        $this->assertPaymentRow($payments['visa'], $workRow, '50.00', 'AED', '1.0000', '50.00');
    }

    public function test_non_legacy_aed_clinic_import_assigns_clinic_currency_to_payments(): void
    {
        $tenant = $this->seedGenericAedClinicTenant();
        $this->actingAs($tenant['admin']);

        $fixture = ImportExcelFixtureBuilder::genericClinicWorkbook(
            doctorCode: $tenant['doctor']->code,
            treatmentText: 'CF x 1',
            workDate: '2026-07-08',
            payments: ['dhs' => 100.0, 'usd' => 10.0, 'visa' => 0.0, 'cheque' => 0.0, 'tabby' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture, 'daily report July 2026.xlsx');
        $workRow = AccountingScopedQuery::workRows((int) $report->clinic_id, $report->id)->firstOrFail();

        $foreignToAed = bcmul('10.00', self::USD_TO_AED_RATE, 2);
        $expectedPaidTotalAed = bcadd('100.00', $foreignToAed, 2);

        $this->assertSame($tenant['clinic']->id, $report->clinic_id);
        $this->assertSame($expectedPaidTotalAed, (string) $workRow->paid_total_aed);

        $payments = Payment::query()->where('daily_work_row_id', $workRow->id)->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertSame('AED', $payments[0]->currency);
        $this->assertSame('USD', $payments[1]->currency);
        $this->assertSame('136.50', $expectedPaidTotalAed);
    }

    public function test_import_succeeds_when_no_existing_report_for_month(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);

        $this->assertDatabaseHas('daily_reports', ['id' => $report->id]);
        $this->assertSame(1, DailyWorkRow::query()->where('daily_report_id', $report->id)->count());
    }

    public function test_import_creates_additional_report_when_editable_report_already_exists(): void
    {
        $existing = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'status' => ReportStatus::Calculated,
        ]);

        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);

        $this->assertNotSame($existing->id, $report->id);
        $this->assertSame(2, $this->countReportsForMonth('2026-06-01'));
    }

    public function test_import_duplicate_month_guard_query_does_not_match_sqlite_datetime_storage(): void
    {
        $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'status' => ReportStatus::Approved,
        ]);

        $this->assertTrue(
            DailyReport::query()
                ->whereDate('report_date', '2026-06-01')
                ->where('status', ReportStatus::Approved)
                ->exists(),
        );
        $this->assertFalse(
            DailyReport::query()
                ->where('report_date', '2026-06-01')
                ->whereIn('status', [ReportStatus::Approved, ReportStatus::Locked])
                ->exists(),
        );
    }

    public function test_import_proceeds_when_approved_report_exists_on_sqlite_test_database(): void
    {
        $existing = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'status' => ReportStatus::Approved,
        ]);

        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);

        $this->assertNotSame($existing->id, $report->id);
        $existing->refresh();
        $this->assertSame(ReportStatus::Approved, $existing->status);
        $this->assertSame(2, $this->countReportsForMonth('2026-06-01'));
    }

    public function test_import_proceeds_when_locked_report_exists_on_sqlite_test_database(): void
    {
        $existing = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'status' => ReportStatus::Locked,
        ]);

        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);

        $this->assertNotSame($existing->id, $report->id);
        $this->assertSame(ReportStatus::Locked, $existing->fresh()->status);
        $this->assertSame(2, $this->countReportsForMonth('2026-06-01'));
    }

    public function test_unknown_doctor_row_is_logged_unresolved_and_not_persisted(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111UnknownDoctorWorkbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);

        $this->assertSame(0, DailyWorkRow::query()->where('daily_report_id', $report->id)->count());
        $this->assertSame(0, Payment::query()->where('clinic_id', $report->clinic_id)->count());

        $log = $this->loadExtractionLog($report);
        $this->assertCount(1, $log['unresolved_rows']);
        $this->assertSame('unresolved_doctor', $log['unresolved_rows'][0]['status'] ?? $log['unresolved_rows'][0]['reason'] ?? null);
    }

    public function test_cash_boundary_row_is_skipped_and_not_imported(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111CashSkipWorkbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);

        $this->assertSame(1, DailyWorkRow::query()->where('daily_report_id', $report->id)->count());

        $log = $this->loadExtractionLog($report);
        $skippedReasons = array_column($log['skipped_rows'], 'reason');
        $this->assertContains('cash_row', $skippedReasons);
    }

    public function test_unknown_treatment_emits_import_warning_without_work_items(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111UnknownTreatmentWorkbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);

        $workRow = AccountingScopedQuery::workRows((int) $report->clinic_id, $report->id)->firstOrFail();
        $this->assertSame(0, WorkItem::query()->where('daily_work_row_id', $workRow->id)->count());
        $this->assertSame(ReportStatus::NeedsReview, $report->fresh()->status);

        $warning = DailyReportImportWarning::query()->where('daily_report_id', $report->id)->first();
        $this->assertNotNull($warning);
        $this->assertSame('unknown_treatment_code', $warning->warning_code);
    }

    public function test_no_lab_job_for_treatment_without_lab_cost(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            treatmentText: 'CF x 1',
            payments: ['dhs' => 120.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $workRow = AccountingScopedQuery::workRows((int) $report->clinic_id, $report->id)->firstOrFail();

        $workItem = WorkItem::query()->where('daily_work_row_id', $workRow->id)->first();
        $this->assertNotNull($workItem);
        $this->assertNull($workItem->labJob);
    }

    public function test_no_lab_job_when_doctor_lab_billing_is_disabled(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            doctorLabel: 'DR Wa',
            treatmentText: 'ZIR x 1',
            payments: ['dhs' => 300.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $workRow = AccountingScopedQuery::workRows((int) $report->clinic_id, $report->id)->firstOrFail();
        $workItem = WorkItem::query()->where('daily_work_row_id', $workRow->id)->first();

        $this->assertNotNull($workItem);
        $this->assertNull($workItem->labJob);
    }

    public function test_zero_payment_subtotal_rows_are_not_imported_by_parser(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111ZeroPaymentWorkbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);

        $this->assertSame(0, DailyWorkRow::query()->where('daily_report_id', $report->id)->count());
        $log = $this->loadExtractionLog($report);
        $this->assertSame([], $log['reconciliation_issues']);
    }

    public function test_reconciliation_does_not_block_successful_import(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            doctorLabel: 'DR Riyad',
            treatmentText: 'ZIR x 1',
            payments: ['dhs' => 100.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 10.0, 'visa' => 50.0, 'rub' => 0.0],
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);

        $this->assertContains($report->status, [ReportStatus::Calculated, ReportStatus::NeedsReview]);
        $this->assertSame(1, DailyWorkRow::query()->where('daily_report_id', $report->id)->count());

        $log = $this->loadExtractionLog($report);
        $this->assertIsArray($log['reconciliation_issues']);
        $this->assertArrayHasKey('issue_summary', $log);
    }

    public function test_import_assigns_same_clinic_id_to_all_accounting_entities(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook(
            doctorLabel: 'DR Riyad',
            treatmentText: 'ZIR x 1',
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $clinicId = $report->clinic_id;

        foreach (DailyWorkRow::query()->where('daily_report_id', $report->id)->get() as $workRow) {
            $this->assertSame($clinicId, $workRow->clinic_id);

            foreach (Payment::query()->where('daily_work_row_id', $workRow->id)->get() as $payment) {
                $this->assertSame($clinicId, $payment->clinic_id);
            }

            foreach (WorkItem::query()->where('daily_work_row_id', $workRow->id)->get() as $workItem) {
                $this->assertSame($clinicId, $workItem->clinic_id);
                $this->assertNotNull($workItem->labJob);
                $this->assertSame($clinicId, $workItem->labJob->clinic_id);
            }
        }
    }

    public function test_import_raw_data_json_removes_top_level_patient_identifiers(): void
    {
        $fixture = ImportExcelFixtureBuilder::legacyClinic111Workbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);
        $workRow = AccountingScopedQuery::workRows((int) $report->clinic_id, $report->id)->firstOrFail();
        $raw = $workRow->raw_data_json;

        $this->assertIsArray($raw);
        $this->assertArrayNotHasKey('patient_name', $raw);
        $this->assertArrayNotHasKey('mrn', $raw);
        $this->assertArrayNotHasKey('file_number', $raw);
        $this->assertSame('ZIR x 2', $raw['treatment_text'] ?? null);
    }

    public function test_generic_import_does_not_resolve_doctor_from_other_clinic(): void
    {
        $tenant222 = $this->seedClinic222Tenant();

        $fixture = ImportExcelFixtureBuilder::genericClinicWorkbook(
            doctorCode: $tenant222['doctor']->code,
            workDate: '2026-06-08',
        );
        $this->tempFiles[] = $fixture;

        $report = $this->importFixture($fixture);

        $this->assertSame(0, DailyWorkRow::query()->where('daily_report_id', $report->id)->count());
        $log = $this->loadExtractionLog($report);
        $this->assertGreaterThanOrEqual(1, count($log['unresolved_rows']));
    }

    private function countReportsForMonth(string $monthStart): int
    {
        return DailyReport::query()->whereDate('report_date', $monthStart)->count();
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

    /**
     * @return array{clinic: Clinic, admin: User, doctor: Doctor, treatment: Treatment}
     */
    private function seedGenericAedClinicTenant(): array
    {
        $clinic = Clinic::query()->create([
            'name' => 'Generic AED Import Clinic',
            'code' => 'GEN_AED_IMP',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'UAE',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $admin = new User;
        $admin->fill([
            'name' => 'Generic AED Admin',
            'email' => 'admin-gen-aed-import@test.local',
            'role' => 'admin',
            'clinic_id' => $clinic->id,
        ]);
        $admin->password = 'password';
        $admin->is_active = true;
        $admin->email_verified_at = now();
        $admin->save();

        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'CF',
            'name' => 'Composite Filling',
            'has_lab_cost' => false,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr Generic AED',
            'code' => 'GEN_AED_DOC',
            'commission_type' => CommissionType::Percentage,
            'commission_percentage' => 30,
            'is_active' => true,
        ]);

        return compact('clinic', 'admin', 'doctor', 'treatment');
    }

    /**
     * @return array<string, mixed>
     */
    private function loadExtractionLog(DailyReport $report): array
    {
        $log = app(ImportExtractionLogService::class)->loadForReport($report);

        $this->assertNotNull($log);

        return $log;
    }

    private function assertPaymentRow(
        Payment $payment,
        DailyWorkRow $workRow,
        string $amount,
        string $currency,
        string $exchangeRate,
        string $amountAed,
    ): void {
        $this->assertSame($workRow->clinic_id, $payment->clinic_id);
        $this->assertSame($workRow->id, $payment->daily_work_row_id);
        $this->assertSame($amount, (string) $payment->amount);
        $this->assertSame($currency, $payment->currency);
        $this->assertSame($exchangeRate, (string) $payment->exchange_rate);
        $this->assertSame($amountAed, (string) $payment->amount_aed);
    }
}
