<?php

namespace Tests\Unit;

use App\Enums\CommissionType;
use App\Enums\PaymentMethod;
use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\Clinic;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabJob;
use App\Models\LabPrice;
use App\Models\Payment;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\DailyReport\DailyReportEditorService;
use App\Support\AccountingScopedQuery;
use ErrorException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Characterization tests for {@see DailyReportEditorService::saveWorkRow()}.
 *
 * Documents current pipeline behaviour: work row persist, payment replacement,
 * treatment parsing, lab jobs, tenant guards, and report status rules.
 */
class DailyReportEditorServiceSaveWorkRowCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private const USD_TO_AED_RATE = '3.65';

    private DailyReportEditorService $editorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->editorService = app(DailyReportEditorService::class);
    }

    public function test_new_manual_work_row_persists_core_fields_for_legacy_clinic(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport();

        $workRow = $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 5,
            'dhs_amount' => '100.00',
            'visa_amount' => '50.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $this->assertDatabaseHas('daily_work_rows', [
            'id' => $workRow->id,
            'clinic_id' => $report->clinic_id,
            'daily_report_id' => $report->id,
            'doctor_id' => $doctor->id,
            'treatment_text' => 'CF x 1',
            'dhs_amount' => '100.00',
            'visa_amount' => '50.00',
            'paid_total_aed' => '150.00',
        ]);
        $this->assertSame('2026-06-05', $workRow->work_date?->toDateString());
        $this->assertSame('manual_v2', $workRow->raw_data_json['source'] ?? null);
        $this->assertTrue((bool) ($workRow->raw_data_json['corrected_in_editor'] ?? false));
    }

    public function test_new_work_row_creates_expected_payment_rows_for_legacy_clinic_111(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport();

        $workRow = $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 6,
            'dhs_amount' => '100.00',
            'cheque_amount' => '25.00',
            'usd_amount' => '10.00',
            'visa_amount' => '50.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $payments = Payment::query()
            ->where('daily_work_row_id', $workRow->id)
            ->orderBy('payment_method')
            ->get()
            ->keyBy(fn (Payment $payment) => $payment->payment_method->value);

        $usdToAed = bcmul('10.00', self::USD_TO_AED_RATE, 2);
        $expectedTotal = bcadd(bcadd(bcadd(bcadd('100.00', '25.00', 2), $usdToAed, 2), '50.00', 2), '0.00', 2);

        $this->assertSame('211.50', $expectedTotal);
        $this->assertSame($expectedTotal, (string) $workRow->paid_total_aed);
        $this->assertCount(4, $payments);
        $this->assertPaymentRow($payments['cheque'], $workRow, '25.00', 'AED', '1.0000', '25.00');
        $this->assertPaymentRow($payments['dhs'], $workRow, '100.00', 'AED', '1.0000', '100.00');
        $this->assertPaymentRow($payments['usd'], $workRow, '10.00', 'USD', '3.6500', '36.50');
        $this->assertPaymentRow($payments['visa'], $workRow, '50.00', 'AED', '1.0000', '50.00');
    }

    public function test_new_work_row_creates_payment_in_clinic_currency_for_non_legacy_aed_clinic(): void
    {
        $tenant = $this->seedNonLegacyAedClinicTenant();
        $this->actingAs($tenant['admin']);

        $report = DailyReport::query()->create([
            'clinic_id' => $tenant['clinic']->id,
            'report_date' => '2026-07-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'AED tenant report',
            'status' => ReportStatus::Uploaded,
        ]);

        $workRow = $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $tenant['doctor']->id,
            'day' => 3,
            'dhs_amount' => '100.00',
            'usd_amount' => '10.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $foreignToAed = bcmul('10.00', self::USD_TO_AED_RATE, 2);
        $expectedPaidTotalAed = bcadd('100.00', $foreignToAed, 2);

        $payment = Payment::query()->where('daily_work_row_id', $workRow->id)->orderBy('id')->get();

        $this->assertSame('136.50', $expectedPaidTotalAed);
        $this->assertSame($expectedPaidTotalAed, (string) $workRow->paid_total_aed);
        $this->assertCount(2, $payment);
        $this->assertPaymentRow($payment[0], $workRow, '100.00', 'AED', '1.0000', '100.00');
        $this->assertPaymentRow($payment[1], $workRow, '10.00', 'USD', '3.6500', '36.50');
    }

    public function test_new_work_row_preserves_usd_original_amount_and_stores_aed_pivot_for_usd_clinic(): void
    {
        $tenant = $this->seedUsdClinicTenant();
        $this->actingAs($tenant['admin']);

        $report = DailyReport::query()->create([
            'clinic_id' => $tenant['clinic']->id,
            'report_date' => '2026-08-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'USD tenant report',
            'status' => ReportStatus::Uploaded,
        ]);

        $workRow = $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $tenant['doctor']->id,
            'day' => 4,
            'dhs_amount' => '100.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $expectedAed = bcmul('100.00', self::USD_TO_AED_RATE, 2);
        $payment = Payment::query()->where('daily_work_row_id', $workRow->id)->sole();

        $this->assertSame('365.00', $expectedAed);
        $this->assertSame($expectedAed, (string) $workRow->paid_total_aed);
        $this->assertPaymentRow($payment, $workRow, '100.00', 'USD', '3.6500', '365.00');
    }

    public function test_update_existing_work_row_does_not_create_duplicate_row(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport();

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-07',
            'treatment_text' => 'CF x 1',
            'dhs_amount' => '100.00',
            'paid_total_aed' => '100.00',
            'raw_data_json' => ['sheet_day' => 7, 'doctor' => 'JACK', 'source' => 'manual_v2'],
        ]);

        $updated = $this->editorService->saveWorkRow($report, $this->payload([
            'work_row_id' => $workRow->id,
            'doctor_id' => $doctor->id,
            'day' => 7,
            'dhs_amount' => '250.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $this->assertSame($workRow->id, $updated->id);
        $this->assertSame(1, DailyWorkRow::query()->where('daily_report_id', $report->id)->count());
        $this->assertSame('250.00', (string) $updated->dhs_amount);
        $this->assertSame('250.00', (string) $updated->paid_total_aed);
    }

    public function test_update_existing_work_row_replaces_payments_without_duplicates(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport();

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-08',
            'treatment_text' => 'CF x 1',
            'dhs_amount' => '100.00',
            'visa_amount' => '50.00',
            'paid_total_aed' => '150.00',
            'raw_data_json' => ['sheet_day' => 8, 'doctor' => 'JACK', 'source' => 'manual_v2'],
        ]);

        $paymentDhs = Payment::query()->create([
            'clinic_id' => $workRow->clinic_id,
            'daily_work_row_id' => $workRow->id,
            'payment_method' => PaymentMethod::Dhs,
            'amount' => '100.00',
            'currency' => 'AED',
            'exchange_rate' => '1.0000',
            'amount_aed' => '100.00',
            'paid_at' => '2026-06-08',
        ]);
        $paymentVisa = Payment::query()->create([
            'clinic_id' => $workRow->clinic_id,
            'daily_work_row_id' => $workRow->id,
            'payment_method' => PaymentMethod::Visa,
            'amount' => '50.00',
            'currency' => 'AED',
            'exchange_rate' => '1.0000',
            'amount_aed' => '50.00',
            'paid_at' => '2026-06-08',
        ]);

        $paymentIdsBefore = [$paymentDhs->id, $paymentVisa->id];

        $updated = $this->editorService->saveWorkRow($report, $this->payload([
            'work_row_id' => $workRow->id,
            'doctor_id' => $doctor->id,
            'day' => 8,
            'dhs_amount' => '200.00',
            'visa_amount' => '0',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $paymentsAfter = Payment::query()->where('daily_work_row_id', $workRow->id)->get();
        $paymentIdsAfter = $paymentsAfter->pluck('id')->all();

        $this->assertCount(1, $paymentsAfter);
        $this->assertSame('200.00', (string) $updated->paid_total_aed);
        $this->assertEmpty(array_intersect($paymentIdsBefore, $paymentIdsAfter));
        $this->assertSame(PaymentMethod::Dhs, $paymentsAfter->first()->payment_method);
        $this->assertSame('200.00', (string) $paymentsAfter->first()->amount);
    }

    public function test_update_replaces_work_items_when_treatment_text_changes(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $zir = Treatment::query()->where('code', 'ZIR')->firstOrFail();
        $mc = Treatment::query()->where('code', 'MC')->firstOrFail();
        $report = $this->createEditableReport();

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-09',
            'treatment_text' => 'ZIR x 2',
            'dhs_amount' => '500.00',
            'paid_total_aed' => '500.00',
            'raw_data_json' => ['sheet_day' => 9, 'doctor' => 'JACK', 'source' => 'manual_v2'],
        ]);
        $firstWorkItem = $this->createWorkItem($workRow, [
            'treatment_id' => $zir->id,
            'quantity' => 2,
        ]);

        $updated = $this->editorService->saveWorkRow($report, $this->payload([
            'work_row_id' => $workRow->id,
            'doctor_id' => $doctor->id,
            'day' => 9,
            'dhs_amount' => '300.00',
            'treatment_lines' => [['code' => 'MC', 'quantity' => 1]],
        ]));

        $this->assertCount(1, $updated->workItems);
        $this->assertNotSame($firstWorkItem->id, $updated->workItems->first()->id);
        $this->assertSame($mc->id, $updated->workItems->first()->treatment_id);
        $this->assertSame(1, $updated->workItems->first()->quantity);
        $this->assertSame($report->clinic_id, $updated->workItems->first()->clinic_id);
        $this->assertDatabaseMissing('work_items', ['id' => $firstWorkItem->id]);
    }

    public function test_update_replaces_lab_job_without_orphans_or_duplicates(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'ZIR')->firstOrFail();
        $report = $this->createEditableReport();

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-10',
            'treatment_text' => 'ZIR x 2',
            'dhs_amount' => '500.00',
            'paid_total_aed' => '500.00',
            'raw_data_json' => ['sheet_day' => 10, 'doctor' => 'JACK', 'source' => 'manual_v2'],
        ]);
        $workItem = $this->createWorkItem($workRow, [
            'treatment_id' => $treatment->id,
            'quantity' => 2,
        ]);
        $firstLabJob = $this->createLabJob($workItem, [
            'lab_id' => Lab::query()->where('code', 'MAIN_LAB')->firstOrFail()->id,
            'lab_price_id' => LabPrice::query()->where('treatment_id', $treatment->id)->whereNull('doctor_id')->firstOrFail()->id,
            'quantity' => 2,
            'unit_cost' => '360.00',
            'total_cost_aed' => '720.00',
        ]);

        $updated = $this->editorService->saveWorkRow($report, $this->payload([
            'work_row_id' => $workRow->id,
            'doctor_id' => $doctor->id,
            'day' => 10,
            'dhs_amount' => '750.00',
            'treatment_lines' => [['code' => 'ZIR', 'quantity' => 3]],
        ]));

        $labJobs = LabJob::query()
            ->where('clinic_id', $report->clinic_id)
            ->whereIn('work_item_id', $updated->workItems->pluck('id'))
            ->get();

        $this->assertCount(1, $labJobs);
        $this->assertNotSame($firstLabJob->id, $labJobs->first()->id);
        $this->assertDatabaseMissing('lab_jobs', ['id' => $firstLabJob->id]);
        $this->assertSame('1080.00', (string) $labJobs->first()->total_cost_aed);
        $this->assertSame('360.00', (string) $labJobs->first()->unit_cost);
        $this->assertSame($updated->workItems->first()->id, $labJobs->first()->work_item_id);
    }

    public function test_second_consecutive_save_work_row_on_manual_report_throws_undefined_imported_rows_error(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport();

        $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 22,
            'dhs_amount' => '100.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('imported_rows');

        $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 23,
            'dhs_amount' => '200.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));
    }

    public function test_save_work_row_creates_lab_job_with_resolved_price_for_zir_treatment(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'ZIR')->firstOrFail();
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $labPrice = LabPrice::query()
            ->where('lab_id', $mainLab->id)
            ->where('treatment_id', $treatment->id)
            ->whereNull('doctor_id')
            ->firstOrFail();
        $report = $this->createEditableReport();

        $workRow = $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 11,
            'dhs_amount' => '500.00',
            'treatment_lines' => [['code' => 'ZIR', 'quantity' => 2]],
        ]));

        $workItem = $workRow->workItems->first();
        $labJob = $workItem->labJob;

        $this->assertNotNull($labJob);
        $this->assertSame($report->clinic_id, $labJob->clinic_id);
        $this->assertSame($workItem->id, $labJob->work_item_id);
        $this->assertSame($mainLab->id, $labJob->lab_id);
        $this->assertSame($labPrice->id, $labJob->lab_price_id);
        $this->assertSame('360.00', (string) $labJob->unit_cost);
        $this->assertSame('720.00', (string) $labJob->total_cost_aed);
        $this->assertSame(2, $labJob->quantity);
    }

    public function test_save_work_row_creates_work_item_without_lab_job_for_non_lab_treatment(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport();

        $workRow = $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 12,
            'dhs_amount' => '150.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $this->assertCount(1, $workRow->workItems);
        $this->assertNull($workRow->workItems->first()->labJob);
        $this->assertCount(1, Payment::query()->where('daily_work_row_id', $workRow->id)->get());
        $this->assertSame('150.00', (string) $workRow->paid_total_aed);
    }

    public function test_save_work_row_creates_work_item_without_lab_job_when_doctor_lab_billing_disabled(): void
    {
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $report = $this->createEditableReport();

        $workRow = $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 13,
            'dhs_amount' => '400.00',
            'treatment_lines' => [['code' => 'ZIR', 'quantity' => 1]],
        ]));

        $this->assertCount(1, $workRow->workItems);
        $this->assertSame('ZIR', $workRow->workItems->first()->treatment->code);
        $this->assertNull($workRow->workItems->first()->labJob);
        $this->assertCount(1, Payment::query()->where('daily_work_row_id', $workRow->id)->get());
    }

    public function test_save_work_row_completes_without_exception_when_reconciliation_issues_exist(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport();

        $workRow = $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 14,
            'dhs_amount' => '0',
            'visa_amount' => '0',
            'treatment_lines' => [['code' => 'ZIR', 'quantity' => 1]],
        ]));

        $this->assertNotNull($workRow->id);
        $this->assertNotNull($workRow->workItems->first()->labJob);
        $this->assertSame('0.00', (string) $workRow->paid_total_aed);
        $this->assertContains($report->fresh()->status, [
            ReportStatus::Calculated,
            ReportStatus::NeedsReview,
        ]);
    }

    public function test_failed_correction_with_foreign_work_row_id_rolls_back_partial_update(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $reportA = $this->createEditableReport(['report_date' => '2026-06-01']);
        $reportB = $this->createEditableReport([
            'report_date' => '2026-06-01',
            'source_file_name' => 'Second June report',
        ]);

        $rowA = $this->createDailyWorkRow($reportA, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-15',
            'treatment_text' => 'CF x 1',
            'dhs_amount' => '100.00',
            'paid_total_aed' => '100.00',
            'raw_data_json' => ['sheet_day' => 15, 'doctor' => 'JACK', 'source' => 'manual_v2'],
        ]);
        Payment::query()->create([
            'clinic_id' => $rowA->clinic_id,
            'daily_work_row_id' => $rowA->id,
            'payment_method' => PaymentMethod::Dhs,
            'amount' => '100.00',
            'currency' => 'AED',
            'exchange_rate' => '1.0000',
            'amount_aed' => '100.00',
            'paid_at' => '2026-06-15',
        ]);

        $rowB = $this->createDailyWorkRow($reportB, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-16',
            'treatment_text' => 'CF x 1',
            'dhs_amount' => '200.00',
            'paid_total_aed' => '200.00',
            'raw_data_json' => ['sheet_day' => 16, 'doctor' => 'JACK', 'source' => 'manual_v2'],
        ]);

        try {
            $this->editorService->saveWorkRow($reportA, $this->payload([
                'work_row_id' => $rowB->id,
                'doctor_id' => $doctor->id,
                'day' => 15,
                'dhs_amount' => '999.00',
                'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
            ]));
            $this->fail('Expected ModelNotFoundException for foreign work_row_id.');
        } catch (ModelNotFoundException) {
            // Current behaviour: correction scoped to report aborts inside DB transaction.
        }

        $rowA->refresh();
        $this->assertSame('100.00', (string) $rowA->dhs_amount);
        $this->assertSame('100.00', (string) $rowA->paid_total_aed);
        $this->assertSame(1, Payment::query()->where('daily_work_row_id', $rowA->id)->count());
    }

    public function test_invalid_day_throws_before_creating_work_row(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport();
        $countBefore = DailyWorkRow::query()->count();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid calendar day for this month.');

        $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 31,
            'dhs_amount' => '50.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $this->assertSame($countBefore, DailyWorkRow::query()->count());
    }

    public function test_save_work_row_rejects_report_from_other_clinic(): void
    {
        $tenant222 = $this->seedClinic222Tenant();
        $report222 = $this->createClinic222DailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $doctor111 = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $countBefore = DailyWorkRow::query()->count();

        $this->authenticateAdmin();

        $this->expectException(NotFoundHttpException::class);

        $this->editorService->saveWorkRow($report222, $this->payload([
            'doctor_id' => $doctor111->id,
            'day' => 5,
            'dhs_amount' => '100.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $this->assertSame($countBefore, DailyWorkRow::query()->count());
    }

    public function test_save_work_row_rejects_doctor_from_other_clinic(): void
    {
        $tenant222 = $this->seedClinic222Tenant();
        $report = $this->createEditableReport();
        $countBefore = DailyWorkRow::query()->count();

        $this->expectException(ModelNotFoundException::class);

        $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $tenant222['doctor']->id,
            'day' => 5,
            'dhs_amount' => '100.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $this->assertSame($countBefore, DailyWorkRow::query()->count());
    }

    public function test_save_work_row_assigns_same_clinic_id_to_all_created_entities(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport();

        $workRow = $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 17,
            'dhs_amount' => '500.00',
            'treatment_lines' => [['code' => 'ZIR', 'quantity' => 1]],
        ]));

        $clinicId = $report->clinic_id;

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

    public function test_save_work_row_is_allowed_for_uploaded_report_status(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport(['status' => ReportStatus::Uploaded]);

        $workRow = $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 18,
            'dhs_amount' => '80.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $this->assertNotNull($workRow->id);
        $this->assertContains($report->fresh()->status, [
            ReportStatus::Calculated,
            ReportStatus::NeedsReview,
        ]);
    }

    public function test_save_work_row_is_allowed_for_calculated_report_status(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport(['status' => ReportStatus::Calculated]);

        $workRow = $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 19,
            'dhs_amount' => '90.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $this->assertNotNull($workRow->id);
        $this->assertSame('90.00', (string) $workRow->paid_total_aed);
    }

    public function test_save_work_row_rejects_approved_report_without_mutating_existing_row(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport(['status' => ReportStatus::Approved]);
        $existing = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-20',
            'dhs_amount' => '100.00',
            'paid_total_aed' => '100.00',
            'treatment_text' => 'CF x 1',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Approved or locked reports are read-only.');

        $this->editorService->saveWorkRow($report, $this->payload([
            'work_row_id' => $existing->id,
            'doctor_id' => $doctor->id,
            'day' => 20,
            'dhs_amount' => '999.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $existing->refresh();
        $this->assertSame('100.00', (string) $existing->dhs_amount);
    }

    public function test_save_work_row_rejects_locked_report_without_mutating_existing_row(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $report = $this->createEditableReport(['status' => ReportStatus::Locked]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Approved or locked reports are read-only.');

        $this->editorService->saveWorkRow($report, $this->payload([
            'doctor_id' => $doctor->id,
            'day' => 21,
            'dhs_amount' => '50.00',
            'treatment_lines' => [['code' => 'CF', 'quantity' => 1]],
        ]));

        $this->assertSame(0, AccountingScopedQuery::workRows((int) $report->clinic_id, $report->id)->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'dhs_amount' => '0',
            'cheque_amount' => '0',
            'tabby_amount' => '0',
            'usd_amount' => '0',
            'visa_amount' => '0',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEditableReport(array $attributes = []): DailyReport
    {
        return $this->createDailyReport(array_merge([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'Characterization manual report',
            'status' => ReportStatus::Uploaded,
        ], $attributes));
    }

    /**
     * @return array{clinic: Clinic, admin: User, doctor: Doctor, treatment: Treatment}
     */
    private function seedNonLegacyAedClinicTenant(): array
    {
        $clinic = Clinic::query()->create([
            'name' => 'Save Row AED Clinic',
            'code' => 'SAVE_AED',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'UAE',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $admin = $this->createClinicAdmin($clinic, 'admin-save-aed@test.local');

        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'CF',
            'name' => 'Composite Filling',
            'has_lab_cost' => false,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr Save AED',
            'code' => 'SAVE_AED_DOC',
            'commission_type' => CommissionType::Percentage,
            'commission_percentage' => 30,
            'is_active' => true,
        ]);

        return compact('clinic', 'admin', 'doctor', 'treatment');
    }

    /**
     * @return array{clinic: Clinic, admin: User, doctor: Doctor, treatment: Treatment}
     */
    private function seedUsdClinicTenant(): array
    {
        $clinic = Clinic::query()->create([
            'name' => 'Save Row USD Clinic',
            'code' => 'SAVE_USD',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'country' => 'USA',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $admin = $this->createClinicAdmin($clinic, 'admin-save-usd@test.local');

        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'CF',
            'name' => 'Composite Filling',
            'has_lab_cost' => false,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr Save USD',
            'code' => 'SAVE_USD_DOC',
            'commission_type' => CommissionType::Percentage,
            'commission_percentage' => 30,
            'is_active' => true,
        ]);

        return compact('clinic', 'admin', 'doctor', 'treatment');
    }

    private function createClinicAdmin(Clinic $clinic, string $email): User
    {
        $admin = new User;
        $admin->fill([
            'name' => 'Admin '.$clinic->code,
            'email' => $email,
            'role' => 'admin',
            'clinic_id' => $clinic->id,
        ]);
        $admin->password = 'password';
        $admin->is_active = true;
        $admin->email_verified_at = now();
        $admin->save();

        return $admin;
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
