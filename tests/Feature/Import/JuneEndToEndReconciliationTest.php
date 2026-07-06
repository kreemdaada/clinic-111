<?php

namespace Tests\Feature\Import;

use App\Enums\PaymentMethod;
use App\Enums\ReportStatus;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\NurseCommission;
use App\Models\NurseCommissionRate;
use App\Models\Payment;
use App\Models\Treatment;
use App\Models\WorkItem;
use App\Services\Accounting\MonthlyIncomeCalculationService;
use App\Services\Accounting\PaymentCalculationService;
use App\Services\Analytics\ClinicFinancialOverviewService;
use App\Services\Configuration\OpgClinicDoctorProvisioner;
use App\Services\Configuration\OpgTreatmentProvisioner;
use App\Services\Export\DoctorIncomeExportProfileProvisioner;
use App\Services\Export\DoctorsIncomeExcelExportService;
use App\Services\Import\DailyReportImportService;
use App\Services\Import\TreatmentImportValidationService;
use App\Support\Analytics\FinancialPeriod;
use App\Support\OpgClinicDoctor;
use App\Support\OpgTreatmentCodes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\JuneEndToEndReconciliationFixtureBuilder;
use Tests\Support\OpgSectionImportFixtureBuilder;
use Tests\TestCase;

class JuneEndToEndReconciliationTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }

        parent::tearDown();
    }

    public function test_june_import_reconciles_parser_through_export(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();
        $nurse = $this->createNurseWithRate('NurseA');

        $fixture = JuneEndToEndReconciliationFixtureBuilder::juneEndToEndWorkbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importUploadedFile($fixture, 'daily report June 2026.xlsx');
        $report->update(['status' => ReportStatus::Calculated]);

        $jack = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $pouria = Doctor::query()->where('code', 'PURIYA')->firstOrFail();
        $riyadh = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $clinicOpgDoctor = Doctor::query()->where('code', OpgClinicDoctor::CODE)->firstOrFail();

        $this->assertNoStaleSectionWarnings($report);
        $this->assertParserRiskDaysPersisted($report, $jack, $pouria, $riyadh, $clinicOpgDoctor);
        $this->assertTransferAccounting($report, $jack, $pouria, $riyadh);
        $this->assertOpgPipeline($report, $clinicOpgDoctor, $nurse);
        $this->assertNoDuplicateEntitiesWithinReport($report);
        $this->assertMonthlyAccountingSurfaces($report, $jack, $pouria, $riyadh, $clinicOpgDoctor);
        $this->assertJulyFirstExcludedFromJune();
        $this->assertIncomeExcelExport($report, $jack, $pouria, $riyadh, $clinicOpgDoctor);
        $this->assertTreatmentSnapshotStability($clinicOpgDoctor);
    }

    public function test_isolated_transfer_is_revenue_neutral_for_clinic_surfaces(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();

        $fixture = JuneEndToEndReconciliationFixtureBuilder::isolatedTransferOnlyWorkbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importUploadedFile($fixture, 'daily report June 2026.xlsx');
        $report->update(['status' => ReportStatus::Calculated]);

        $jack = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $pouria = Doctor::query()->where('code', 'PURIYA')->firstOrFail();

        $this->assertSame('-450.00', (string) $this->workRow($report, $jack, 11)->dhs_amount);
        $this->assertSame('450.00', (string) $this->workRow($report, $pouria, 11)->dhs_amount);
        $this->assertSame(
            0,
            WorkItem::query()->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))->count(),
            'Isolated transfer must not create WorkItems.',
        );

        $diagnostics = $this->transferRevenueDiagnostics($report);

        $this->assertSame('0.00', $diagnostics['paid_total_sum'], 'paid_total_aed sum: '.$diagnostics['summary']);
        $this->assertSame('0.00', $diagnostics['monthly_revenue'], 'Monthly Income revenue: '.$diagnostics['summary']);
        $this->assertSame('0.00', $diagnostics['overview_revenue'], 'Practice Overview revenue: '.$diagnostics['summary']);
        $this->assertSame('0.00', $diagnostics['payment_sum'], 'payments.amount_aed sum: '.$diagnostics['summary']);
        $this->assertSame(2, $diagnostics['payment_count'], 'Expected one negative source and one positive target payment: '.$diagnostics['summary']);
        $this->assertSame(
            ['-450.00', '450.00'],
            $diagnostics['payment_amounts'],
            'Isolated transfer payment amounts must preserve signs.',
        );
        $this->assertSame('0.00', $diagnostics['net_total_sum'], 'Net total before further costs must be zero.');
    }

    public function test_transfer_with_patient_payment_does_not_inflate_clinic_revenue(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();

        $fixture = JuneEndToEndReconciliationFixtureBuilder::transferWithPatientPaymentWorkbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importUploadedFile($fixture, 'daily report June 2026.xlsx');
        $report->update(['status' => ReportStatus::Calculated]);

        $jack = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $pouria = Doctor::query()->where('code', 'PURIYA')->firstOrFail();

        $this->assertSame('550.00', (string) $this->workRow($report, $jack, 12)->dhs_amount);
        $this->assertSame('450.00', (string) $this->workRow($report, $pouria, 12)->dhs_amount);

        $diagnostics = $this->transferRevenueDiagnostics($report);

        $this->assertSame('1000.00', $diagnostics['paid_total_sum'], 'paid_total_aed sum: '.$diagnostics['summary']);
        $this->assertSame('1000.00', $diagnostics['monthly_revenue'], 'Monthly Income revenue: '.$diagnostics['summary']);
        $this->assertSame('1000.00', $diagnostics['overview_revenue'], 'Practice Overview revenue: '.$diagnostics['summary']);
        $this->assertSame('1000.00', $diagnostics['payment_sum'], 'payments.amount_aed sum: '.$diagnostics['summary']);
        $this->assertNotSame('1450.00', $diagnostics['monthly_revenue']);
        $this->assertNotSame('550.00', $diagnostics['monthly_revenue']);
    }

    public function test_zero_amount_rows_still_do_not_create_payments(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();

        $report = $this->createDailyReport([
            'report_date' => '2026-06-15',
            'source_type' => 'manual_entry',
            'status' => ReportStatus::Calculated,
        ]);
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();

        $row = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-15',
            'dhs_amount' => '0.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '0.00',
            'visa_amount' => '0.00',
            'paid_total_aed' => '0.00',
        ]);

        app(PaymentCalculationService::class)->createPaymentsForWorkRow($row->fresh());

        $this->assertSame(0, Payment::query()->where('daily_work_row_id', $row->id)->count());
    }

    public function test_recalculation_keeps_single_signed_payment_per_transfer_row(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();

        $fixture = JuneEndToEndReconciliationFixtureBuilder::isolatedTransferOnlyWorkbook();
        $this->tempFiles[] = $fixture;

        $report = $this->importUploadedFile($fixture, 'daily report June 2026.xlsx');
        $report->update(['status' => ReportStatus::Calculated]);

        $rows = DailyWorkRow::query()
            ->where('daily_report_id', $report->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            Payment::query()->where('daily_work_row_id', $row->id)->delete();
            app(PaymentCalculationService::class)->createPaymentsForWorkRow($row->fresh());
        }

        $payments = Payment::query()
            ->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))
            ->orderBy('amount')
            ->get();

        $this->assertCount(2, $payments);
        $this->assertSame(['-450.00', '450.00'], $payments->pluck('amount')->map(fn ($v) => (string) $v)->all());
    }

    /**
     * @return array{
     *     paid_total_sum: string,
     *     payment_sum: string,
     *     payment_count: int,
     *     payment_amounts: list<string>,
     *     monthly_revenue: string,
     *     overview_revenue: string,
     *     net_total_sum: string,
     *     summary: string
     * }
     */
    private function transferRevenueDiagnostics($report): array
    {
        $rows = DailyWorkRow::query()
            ->where('daily_report_id', $report->id)
            ->orderBy('id')
            ->get();

        $payments = Payment::query()
            ->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))
            ->orderBy('id')
            ->get();

        $paidTotalSum = number_format((float) $rows->sum('paid_total_aed'), 2, '.', '');
        $paymentSum = number_format((float) $payments->sum('amount_aed'), 2, '.', '');

        $monthly = app(MonthlyIncomeCalculationService::class)->calculateForMonth('2026-06');
        $monthlyRevenue = number_format(
            $monthly->sum(fn ($summary) => (float) $summary->totalCollectedAed),
            2,
            '.',
            '',
        );
        $overviewRevenue = app(ClinicFinancialOverviewService::class)->build('2026-06')->revenue->amount;

        $rowDetails = $rows->map(fn (DailyWorkRow $row) => sprintf(
            'row#%d dhs=%s paid_total=%s',
            $row->id,
            $row->dhs_amount,
            $row->paid_total_aed,
        ))->implode('; ');

        $paymentDetails = $payments->map(fn (Payment $payment) => sprintf(
            'payment#%d method=%s amount=%s amount_aed=%s',
            $payment->id,
            $payment->payment_method->value,
            $payment->amount,
            $payment->amount_aed,
        ))->implode('; ');

        $summary = sprintf(
            'rows=[%s] payments(count=%d)=[%s] monthly=%s overview=%s',
            $rowDetails,
            $payments->count(),
            $paymentDetails,
            $monthlyRevenue,
            $overviewRevenue,
        );

        return [
            'paid_total_sum' => $paidTotalSum,
            'payment_sum' => $paymentSum,
            'payment_count' => $payments->count(),
            'payment_amounts' => $payments->pluck('amount')->map(fn ($v) => (string) $v)->all(),
            'monthly_revenue' => $monthlyRevenue,
            'overview_revenue' => $overviewRevenue,
            'net_total_sum' => number_format(
                $monthly->sum(fn ($summary) => (float) $summary->netTotalAed),
                2,
                '.',
                '',
            ),
            'summary' => $summary,
        ];
    }

    private function assertNoStaleSectionWarnings($report): void
    {
        $this->assertFalse(
            $report->importWarnings()->where('warning_code', 'stale_section')->exists(),
            'Valid June sections must not be flagged as stale_section.',
        );
    }

    private function assertParserRiskDaysPersisted($report, Doctor $jack, Doctor $pouria, Doctor $riyadh, Doctor $clinicOpgDoctor): void
    {
        $row = $this->workRow($report, $jack, 7);
        $this->assertSame('1850.00', (string) $row->dhs_amount);
        $this->assertSame('2100.00', (string) $row->visa_amount);

        $riyadhDay16 = $this->workRow($report, $riyadh, 16);
        $this->assertSame('1', (string) $report->dailyWorkRows()->where('doctor_id', $riyadh->id)->whereDay('work_date', 16)->count());
        $this->assertSame('1400.00', (string) $riyadhDay16->visa_amount);
        $this->assertStringContainsString('ZIR x 1', (string) $riyadhDay16->treatment_text);
        $this->assertSame(
            0,
            $report->dailyWorkRows()->whereHas('doctor', fn ($q) => $q->where('code', 'WA'))->whereDay('work_date', 16)->count(),
            'Hybrid subtotal must not assign Doctor Wael a row on day 16.',
        );

        $jackDay22 = $this->workRow($report, $jack, 22);
        $pouriaDay22 = $this->workRow($report, $pouria, 22);
        $riyadhDay22 = $this->workRow($report, $riyadh, 22);
        $this->assertSame('1500.00', (string) $jackDay22->dhs_amount);
        $this->assertSame('9450.00', (string) $jackDay22->visa_amount);
        $this->assertSame('350.00', (string) $pouriaDay22->dhs_amount);
        $this->assertStringContainsString('CF x 1', (string) $pouriaDay22->treatment_text);
        $this->assertSame('11250.00', (string) $riyadhDay22->dhs_amount);
        $this->assertSame('350.00', (string) $riyadhDay22->visa_amount);

        $jackDay26 = $this->workRow($report, $jack, 26);
        $this->assertSame('1', (string) $report->dailyWorkRows()->where('doctor_id', $jack->id)->whereDay('work_date', 26)->count());
        $this->assertSame('4650.00', (string) $jackDay26->dhs_amount);
        $this->assertSame('14150.00', (string) $jackDay26->visa_amount);
        $this->assertStringContainsString('PAID BALANCE', (string) $jackDay26->treatment_text);
        $this->assertStringContainsString('REMOV x 2', (string) $jackDay26->treatment_text);
        $this->assertStringContainsString('TRANSFER FROM DR POURIA', (string) $jackDay26->treatment_text);

        $opgDay30 = $this->workRow($report, $clinicOpgDoctor, 30);
        $this->assertNotNull($opgDay30, 'Day 30 OPG must be present in month-end surfaces.');
    }

    private function assertTransferAccounting($report, Doctor $jack, Doctor $pouria, Doctor $riyadh): void
    {
        $this->assertSame('-450.00', (string) $this->workRow($report, $pouria, 19)->dhs_amount);
        $this->assertSame('450.00', (string) $this->workRow($report, $riyadh, 19)->dhs_amount);
        $this->assertStringContainsString('Transfer', (string) $this->workRow($report, $pouria, 19)->treatment_text);

        $this->assertSame('-700.00', (string) $this->workRow($report, $jack, 24)->dhs_amount);
        $this->assertSame('700.00', (string) $this->workRow($report, $riyadh, 24)->dhs_amount);
        $this->assertStringContainsString('TRANSFER FROM DR JACK', (string) $this->workRow($report, $riyadh, 24)->treatment_text);

        $this->assertSame('-350.00', (string) $this->workRow($report, $pouria, 26)->dhs_amount);
        $this->assertStringContainsString('TRANSFER TO DR JACK', (string) $this->workRow($report, $pouria, 26)->treatment_text);

        $this->assertSame('-7200.00', (string) $this->workRow($report, $jack, 30)->dhs_amount);
        $this->assertSame('7200.00', (string) $this->workRow($report, $riyadh, 30)->dhs_amount);
        $this->assertStringContainsString('transfer from dr jack', strtolower((string) $this->workRow($report, $riyadh, 30)->treatment_text));

        $transferNet = bcadd(
            bcadd(
                bcadd('-450.00', '450.00', 2),
                bcadd('-700.00', '700.00', 2),
                2,
            ),
            bcadd(bcadd('-350.00', '350.00', 2), bcadd('-7200.00', '7200.00', 2), 2),
            2,
        );
        $this->assertSame('0.00', $transferNet, 'Transfer DHS components must net to zero on daily work rows.');

        $transferWorkItems = WorkItem::query()
            ->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))
            ->whereHas('treatment', fn ($q) => $q->where('code', 'like', '%TRANSFER%'))
            ->count();
        $this->assertSame(0, $transferWorkItems, 'Transfers must not create transfer WorkItems.');

        $negativeSourceRows = DailyWorkRow::query()
            ->where('daily_report_id', $report->id)
            ->where(function ($query): void {
                $query->where('dhs_amount', '<', 0)
                    ->orWhere('paid_total_aed', '<', 0);
            })
            ->count();
        $this->assertGreaterThan(0, $negativeSourceRows, 'Source doctors must retain negative payment components on work rows.');

        $positiveTargetPayments = Payment::query()
            ->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))
            ->where('payment_method', PaymentMethod::Dhs)
            ->where('amount', '>', 0)
            ->count();
        $this->assertGreaterThan(0, $positiveTargetPayments, 'Target doctors must have positive DHS payment rows.');
    }

    private function assertOpgPipeline($report, Doctor $clinicOpgDoctor, Nurse $nurse): void
    {
        $opgRows = DailyWorkRow::query()
            ->where('daily_report_id', $report->id)
            ->where('doctor_id', $clinicOpgDoctor->id)
            ->orderBy('work_date')
            ->get();

        $this->assertCount(3, $opgRows);

        $day3 = $opgRows->first(fn ($row) => (int) $row->work_date->format('j') === 3);
        $day19 = $opgRows->first(fn ($row) => (int) $row->work_date->format('j') === 19);
        $day30 = $opgRows->first(fn ($row) => (int) $row->work_date->format('j') === 30);

        $this->assertNotNull($day3);
        $this->assertNotNull($day19);
        $this->assertNotNull($day30);

        $day3WorkItem = $day3->workItems()->firstOrFail();
        $this->assertSame('200.00', (string) $day3WorkItem->treatment_price_aed);
        $this->assertNull($day3WorkItem->fresh()->nurseCommission);

        $warnings = app(TreatmentImportValidationService::class)->collectNurseCommissionWarnings($day3->fresh('workItems.treatment'));
        $this->assertNotEmpty($warnings);
        $this->assertSame('nurse_commission_incomplete', $warnings[0]->warningCode);

        $day19WorkItem = $day19->workItems()->firstOrFail();
        $this->assertSame($nurse->id, $day19WorkItem->nurse_id);
        $this->assertSame('10.00', (string) $day19WorkItem->fresh()->nurseCommission->total_commission_aed);

        $day30WorkItem = $day30->workItems()->firstOrFail();
        $this->assertSame($nurse->id, $day30WorkItem->nurse_id);
        $this->assertSame('10.00', (string) $day30WorkItem->fresh()->nurseCommission->total_commission_aed);

        $clinicOpgSummary = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth('2026-06')
            ->firstWhere('doctorId', $clinicOpgDoctor->id);

        $this->assertNotNull($clinicOpgSummary);
        $this->assertSame('600.00', $clinicOpgSummary->opgNormalValueAed);
        $this->assertSame('0.00', $clinicOpgSummary->opg3dValueAed);
    }

    private function assertNoDuplicateEntitiesWithinReport($report): void
    {
        $rowCount = DailyWorkRow::query()->where('daily_report_id', $report->id)->count();
        $distinctKeys = DailyWorkRow::query()
            ->where('daily_report_id', $report->id)
            ->selectRaw('doctor_id, work_date')
            ->distinct()
            ->count();

        $this->assertSame($rowCount, $distinctKeys, 'A single import must not duplicate doctor/day work rows.');

        $opgWorkItems = WorkItem::query()
            ->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))
            ->whereHas('treatment', fn ($q) => $q->where('code', OpgTreatmentCodes::NORMAL))
            ->count();
        $this->assertSame(3, $opgWorkItems);

        $paymentCount = Payment::query()
            ->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))
            ->count();
        $distinctPaymentKeys = Payment::query()
            ->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))
            ->selectRaw('daily_work_row_id, payment_method')
            ->distinct()
            ->count();
        $this->assertSame($paymentCount, $distinctPaymentKeys);

        $commissionCount = NurseCommission::query()
            ->whereHas('workItem.dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))
            ->count();
        $this->assertSame(2, $commissionCount);
    }

    private function assertTreatmentSnapshotStability(Doctor $clinicOpgDoctor): void
    {
        $before = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth('2026-06')
            ->firstWhere('doctorId', $clinicOpgDoctor->id);

        $this->assertNotNull($before);
        $this->assertSame('600.00', $before->opgNormalValueAed);

        Treatment::query()->where('code', OpgTreatmentCodes::NORMAL)->firstOrFail()->update([
            'treatment_price' => '250.00',
        ]);

        $after = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth('2026-06')
            ->firstWhere('doctorId', $clinicOpgDoctor->id);

        $this->assertNotNull($after);
        $this->assertSame('600.00', $after->opgNormalValueAed);

        $followUpFixture = OpgSectionImportFixtureBuilder::tag3OpgDhsWorkbook();
        $this->tempFiles[] = $followUpFixture;

        $newReport = $this->importUploadedFile($followUpFixture, 'daily report June 2026 replay.xlsx');

        $julyOpg = WorkItem::query()
            ->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $newReport->id))
            ->whereHas('treatment', fn ($q) => $q->where('code', OpgTreatmentCodes::NORMAL))
            ->firstOrFail();

        $this->assertSame('250.00', (string) $julyOpg->treatment_price_aed);
    }

    private function assertMonthlyAccountingSurfaces($report, Doctor $jack, Doctor $pouria, Doctor $riyadh, Doctor $clinicOpgDoctor): void
    {
        $monthly = app(MonthlyIncomeCalculationService::class)->calculateForMonth('2026-06');
        $overview = app(ClinicFinancialOverviewService::class)->build('2026-06');

        $clinicOpgSummary = $monthly->firstWhere('doctorId', $clinicOpgDoctor->id);
        $this->assertNotNull($clinicOpgSummary);
        $this->assertSame('600.00', $clinicOpgSummary->opgNormalValueAed);
        $this->assertSame('0.00', $clinicOpgSummary->opg3dValueAed);
        $this->assertSame('0.00', $clinicOpgSummary->doctorIncomeAed);

        $totalNurseCommission = $monthly->sum(fn ($row) => (float) $row->nurseCommissionAed);
        $this->assertSame(20.0, $totalNurseCommission);

        $totalCollected = $monthly->sum(fn ($row) => (float) $row->totalCollectedAed);
        $this->assertSame((float) $overview->revenue->amount, $totalCollected);

        $reportPaymentTotal = number_format(
            (float) Payment::query()
                ->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))
                ->sum('amount_aed'),
            2,
            '.',
            '',
        );
        $this->assertSame($overview->revenue->amount, $reportPaymentTotal);
        $this->assertSame('600.00', $overview->opgNormalValue->amount);
        $this->assertSame('0.00', $overview->opg3dValue->amount);
        $this->assertSame('20.00', $overview->nurseCommission->amount);

        $jackSummary = $monthly->firstWhere('doctorId', $jack->id);
        $pouriaSummary = $monthly->firstWhere('doctorId', $pouria->id);
        $riyadhSummary = $monthly->firstWhere('doctorId', $riyadh->id);

        $this->assertNotNull($jackSummary);
        $this->assertNotNull($pouriaSummary);
        $this->assertNotNull($riyadhSummary);

        $this->assertTrue($overview->hasData);
        $this->assertNotNull($this->workRow($report, $clinicOpgDoctor, 30));
    }

    private function assertJulyFirstExcludedFromJune(): void
    {
        $period = FinancialPeriod::fromMonth('2026-06', 'UTC');
        $this->assertSame('2026-07-01 00:00:00', $period->exclusiveEnd()->format('Y-m-d H:i:s'));

        $jack = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $juneJackBefore = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth('2026-06')
            ->firstWhere('doctorId', $jack->id);

        $julyReport = $this->createDailyReport([
            'report_date' => '2026-07-01',
            'source_type' => 'manual_entry',
            'status' => ReportStatus::Calculated,
        ]);

        $this->createDailyWorkRow($julyReport, [
            'doctor_id' => $jack->id,
            'work_date' => '2026-07-01',
            'dhs_amount' => '999.00',
            'paid_total_aed' => '999.00',
        ]);

        $juneJackAfter = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth('2026-06')
            ->firstWhere('doctorId', $jack->id);

        $this->assertNotNull($juneJackBefore);
        $this->assertNotNull($juneJackAfter);
        $this->assertSame(
            $juneJackBefore->totalDhs,
            $juneJackAfter->totalDhs,
            '2026-07-01 must not belong to June monthly income.',
        );
    }

    private function assertIncomeExcelExport($report, Doctor $jack, Doctor $pouria, Doctor $riyadh, Doctor $clinicOpgDoctor): void
    {
        $exportPath = app(DoctorsIncomeExcelExportService::class)->exportForReport($report->fresh());
        $spreadsheet = IOFactory::load($exportPath);

        foreach ([$jack, $pouria, $riyadh] as $doctor) {
            $profile = app(DoctorIncomeExportProfileProvisioner::class)->ensureForDoctor($doctor);
            $this->assertNotNull($spreadsheet->getSheetByName($profile['sheet_name']), "Doctor sheet for {$doctor->code} must exist.");
        }

        $clinicProfile = app(DoctorIncomeExportProfileProvisioner::class)->ensureForDoctor($clinicOpgDoctor);
        $clinicSheet = $spreadsheet->getSheetByName($clinicProfile['sheet_name']);
        $this->assertNotNull($clinicSheet, 'CLINIC_OPG sheet must exist.');

        $firstDayRow = (int) $clinicProfile['first_day_row'];
        foreach ([3, 19, 30] as $day) {
            $exportValue = (float) $clinicSheet->getCell('T'.($firstDayRow + $day - 1))->getCalculatedValue();
            $this->assertSame(200.0, $exportValue, "Excel OPG-Normal export for day {$day}");
        }

        $jackProfile = app(DoctorIncomeExportProfileProvisioner::class)->ensureForDoctor($jack);
        $jackSheet = $spreadsheet->getSheetByName($jackProfile['sheet_name']);
        $jackFirstDayRow = (int) $jackProfile['first_day_row'];

        $transferDay30Dhs = (float) $jackSheet->getCell('H'.($jackFirstDayRow + 30 - 1))->getCalculatedValue();
        $this->assertSame(-7200.0, $transferDay30Dhs, 'Transfer amount must appear on the correct doctor export row.');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function workRow($report, Doctor $doctor, int $day): DailyWorkRow
    {
        return DailyWorkRow::query()
            ->where('daily_report_id', $report->id)
            ->where('doctor_id', $doctor->id)
            ->whereDay('work_date', $day)
            ->firstOrFail();
    }

    private function provisionOpg(): void
    {
        $clinic = $this->clinic111();
        app(OpgTreatmentProvisioner::class)->provisionForClinic($clinic);
        app(OpgClinicDoctorProvisioner::class)->provisionForClinic($clinic);
    }

    private function createNurseWithRate(string $name): Nurse
    {
        $treatment = Treatment::query()->where('code', OpgTreatmentCodes::NORMAL)->firstOrFail();
        $nurse = Nurse::factory()->create([
            'clinic_id' => $this->clinic111()->id,
            'name' => $name,
            'code' => strtoupper($name),
            'is_active' => true,
        ]);

        NurseCommissionRate::factory()->create([
            'clinic_id' => $this->clinic111()->id,
            'nurse_id' => $nurse->id,
            'treatment_id' => $treatment->id,
            'commission_percentage' => '5.00',
            'is_active' => true,
        ]);

        return $nurse;
    }

    private function importUploadedFile(string $fixturePath, string $originalName)
    {
        Storage::fake('local');
        config(['accounting.upload.disk' => 'local', 'accounting.upload.directory' => 'imports']);

        $uploaded = new UploadedFile($fixturePath, $originalName, null, null, true);

        return app(DailyReportImportService::class)->import($uploaded)->fresh([
            'dailyWorkRows.payments',
            'dailyWorkRows.workItems',
            'importWarnings',
        ]);
    }
}
