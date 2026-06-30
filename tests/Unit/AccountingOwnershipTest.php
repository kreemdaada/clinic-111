<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\Doctor;
use App\Models\Payment;
use App\Models\Treatment;
use App\Services\Accounting\LabJobCalculationService;
use App\Services\Accounting\MonthlyIncomeCalculationService;
use App\Services\Accounting\PaymentCalculationService;
use App\Services\DailyReport\DailyReportEditorService;
use App\Services\Import\DailyReportImportService;
use App\Support\AccountingScopedQuery;
use RuntimeException;
use Tests\TestCase;

class AccountingOwnershipTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->authenticateAdmin();
    }

    public function test_manual_report_create_assigns_current_clinic_id(): void
    {
        $report = app(DailyReportEditorService::class)
            ->createManualReport(now()->startOfMonth(), 'Ownership test');

        $this->assertSame($this->clinic111()->id, $report->clinic_id);
    }

    public function test_payment_create_inherits_work_row_clinic_id(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-05',
            'dhs_amount' => '100.00',
            'paid_total_aed' => '100.00',
        ]);

        app(PaymentCalculationService::class)->createPaymentsForWorkRow($workRow);

        $payment = Payment::query()->where('daily_work_row_id', $workRow->id)->first();

        $this->assertNotNull($payment);
        $this->assertSame($report->clinic_id, $payment->clinic_id);
        $this->assertSame(PaymentMethod::Dhs, $payment->payment_method);
    }

    public function test_lab_job_create_inherits_work_item_clinic_id(): void
    {
        $doctor = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'ZIR')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-01-15',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Parsed,
        ]);

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-01-15',
            'treatment_text' => 'ZIR x 1',
        ]);

        $workItem = $this->createWorkItem($workRow, [
            'treatment_id' => $treatment->id,
            'quantity' => 1,
        ]);

        app(LabJobCalculationService::class)->calculateForWorkRow($workRow);
        $workItem->refresh()->load('labJob');

        $this->assertNotNull($workItem->labJob);
        $this->assertSame($report->clinic_id, $workItem->labJob->clinic_id);
    }

    public function test_monthly_income_queries_are_clinic_scoped(): void
    {
        $tenant222 = $this->seedClinic222Tenant();
        $report222 = $this->createClinic222DailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'status' => ReportStatus::Calculated,
        ]);

        $workRow222 = $this->createDailyWorkRow($report222, [
            'doctor_id' => $tenant222['doctor']->id,
            'work_date' => '2026-06-12',
            'paid_total_aed' => '999.00',
            'dhs_amount' => '999.00',
        ]);

        Payment::query()->create([
            'clinic_id' => $report222->clinic_id,
            'daily_work_row_id' => $workRow222->id,
            'payment_method' => PaymentMethod::Dhs,
            'amount' => '999.00',
            'currency' => 'AED',
            'amount_aed' => '999.00',
            'paid_at' => '2026-06-12',
        ]);

        $service = app(MonthlyIncomeCalculationService::class);
        $summary111 = $service->calculateForMonth('2026-06');
        $doctor222Row = $summary111->first(fn ($row) => $row->doctorId === $tenant222['doctor']->id);

        $this->assertNull($doctor222Row);

        $this->actingAs($tenant222['admin']);
        $summary222 = $service->calculateForMonth('2026-06');
        $doctor222Included = $summary222->first(fn ($row) => $row->doctorId === $tenant222['doctor']->id);

        $this->assertNotNull($doctor222Included);
        $this->assertSame('999.00', $doctor222Included->totalCollectedAed);
    }

    public function test_import_pipeline_assigns_same_clinic_id_to_all_entities(): void
    {
        $doctor = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'source_file_name' => 'daily report June 2026.xlsm',
            'status' => ReportStatus::Uploaded,
        ]);

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-03',
            'treatment_text' => 'ZIR x 2',
            'dhs_amount' => '800.00',
            'paid_total_aed' => '800.00',
        ]);

        app(PaymentCalculationService::class)->createPaymentsForWorkRow($workRow);
        app(DailyReportImportService::class)->processParsedReport($report->fresh());

        $clinicId = $this->clinic111()->id;
        $report->refresh();

        $this->assertSame($clinicId, $report->clinic_id);

        $workRows = AccountingScopedQuery::workRows($clinicId, $report->id)
            ->with(['payments', 'workItems.labJob'])
            ->get();

        foreach ($workRows as $row) {
            $this->assertSame($clinicId, $row->clinic_id);

            foreach ($row->payments as $payment) {
                $this->assertSame($clinicId, $payment->clinic_id);
            }

            foreach ($row->workItems as $workItem) {
                $this->assertSame($clinicId, $workItem->clinic_id);

                if ($workItem->labJob !== null) {
                    $this->assertSame($clinicId, $workItem->labJob->clinic_id);
                }
            }
        }
    }

    public function test_clinic_id_is_immutable_after_create(): void
    {
        $report = $this->createDailyReport([
            'report_date' => '2026-07-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('clinic_id is immutable');

        $report->update(['clinic_id' => $report->clinic_id + 1]);
    }

    public function test_child_work_item_clinic_matches_parent_work_row(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
            'treatment_text' => 'MC x 1',
        ]);

        app(DailyReportImportService::class)->processParsedReport($report->fresh());

        $workItem = AccountingScopedQuery::workItems((int) $workRow->clinic_id, $workRow->id)->first();

        $this->assertNotNull($workItem);
        $this->assertSame($workRow->clinic_id, $workItem->clinic_id);
        $this->assertSame($report->clinic_id, $workItem->clinic_id);
    }
}
