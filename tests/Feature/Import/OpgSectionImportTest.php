<?php

namespace Tests\Feature\Import;

use App\Enums\ReportStatus;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\NurseCommission;
use App\Models\NurseCommissionRate;
use App\Models\Treatment;
use App\Models\WorkItem;
use App\Services\Accounting\MonthlyIncomeCalculationService;
use App\Services\Accounting\OpgTreatmentValueAggregator;
use App\Services\Analytics\ClinicFinancialOverviewService;
use App\Services\Configuration\OpgClinicDoctorProvisioner;
use App\Services\Configuration\OpgTreatmentProvisioner;
use App\Services\Export\DoctorIncomeExportProfileProvisioner;
use App\Services\Export\DoctorsIncomeExcelExportService;
use App\Services\Import\DailyReportImportService;
use App\Services\Import\TreatmentImportValidationService;
use App\Support\OpgClinicDoctor;
use App\Support\OpgTreatmentCodes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\OpgSectionImportFixtureBuilder;
use Tests\TestCase;

class OpgSectionImportTest extends TestCase
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

    public function test_imported_opg_uses_existing_treatment(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();

        $report = $this->importFixture(OpgSectionImportFixtureBuilder::tag3OpgDhsWorkbook(), 'daily report June 2026.xlsx');

        $treatment = Treatment::query()->where('code', 'OPG_NORMAL')->firstOrFail();
        $workItem = WorkItem::query()->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))->firstOrFail();

        $this->assertSame($treatment->id, $workItem->treatment_id);
    }

    public function test_imported_opg_creates_normal_work_item(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();

        $report = $this->importFixture(OpgSectionImportFixtureBuilder::tag3OpgDhsWorkbook(), 'daily report June 2026.xlsx');

        $workItem = WorkItem::query()->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))->firstOrFail();

        $this->assertSame(1, $workItem->quantity);
        $this->assertStringContainsString('OPG_NORMAL', (string) $workItem->dailyWorkRow->treatment_text);
    }

    public function test_imported_opg_uses_existing_payment_pipeline(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();

        $report = $this->importFixture(OpgSectionImportFixtureBuilder::tag3OpgDhsWorkbook(), 'daily report June 2026.xlsx');
        $row = $report->dailyWorkRows()->firstOrFail();

        $this->assertSame('200.00', (string) $row->dhs_amount);
        $this->assertSame('200.00', (string) $row->paid_total_aed);
        $this->assertCount(1, $row->payments);
    }

    public function test_imported_opg_uses_existing_nurse_commission_pipeline(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();
        $nurse = $this->createNurseWithRate('Jiji');

        $report = $this->importFixture(
            OpgSectionImportFixtureBuilder::tag30OpgNurseInTreatmentTextWorkbook(),
            'daily report June 2026.xlsx',
        );

        $workItem = WorkItem::query()->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))->firstOrFail();

        $this->assertSame($nurse->id, $workItem->nurse_id);
        $this->assertNotNull($workItem->fresh()->nurseCommission);
        $this->assertSame('10.00', (string) $workItem->fresh()->nurseCommission->total_commission_aed);
    }

    public function test_unknown_nurse_creates_warning(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();

        $report = $this->importFixture(
            OpgSectionImportFixtureBuilder::tag30OpgNurseInTreatmentTextWorkbook(),
            'daily report June 2026.xlsx',
        );

        $this->assertSame(ReportStatus::NeedsReview, $report->status);
        $this->assertTrue($report->importWarnings()->where('warning_code', 'nurse_not_found')->exists());
    }

    public function test_cross_clinic_nurse_is_not_resolved(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();

        $this->seedClinic222Tenant();

        Nurse::factory()->create([
            'clinic_id' => $this->clinic222()->id,
            'name' => 'Jiji',
            'code' => 'JIJI',
            'is_active' => true,
        ]);

        $report = $this->importFixture(
            OpgSectionImportFixtureBuilder::tag30OpgNurseInTreatmentTextWorkbook(),
            'daily report June 2026.xlsx',
        );

        $workItem = WorkItem::query()->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))->firstOrFail();
        $this->assertNull($workItem->nurse_id);
    }

    public function test_disabled_nurse_is_rejected_or_warned_using_existing_rule(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();

        Nurse::factory()->create([
            'clinic_id' => $this->clinic111()->id,
            'name' => 'Jiji',
            'code' => 'JIJI',
            'is_active' => false,
        ]);

        $report = $this->importFixture(
            OpgSectionImportFixtureBuilder::tag30OpgNurseInTreatmentTextWorkbook(),
            'daily report June 2026.xlsx',
        );

        $this->assertTrue($report->importWarnings()->where('warning_code', 'nurse_inactive')->exists());
    }

    public function test_reimport_does_not_duplicate_opg_entries(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();

        $fixture = OpgSectionImportFixtureBuilder::tag3OpgDhsWorkbook();
        $this->tempFiles[] = $fixture;

        $first = $this->importUploadedFile($fixture, 'daily report June 2026.xlsx');
        $second = $this->importUploadedFile($fixture, 'daily report June 2026.xlsx');

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(1, WorkItem::query()->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $second->id))->count());
    }

    public function test_june_opg_month_fixture_totals_opg_normal_value(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();
        $nurse = $this->createNurseWithRate('Jiji');

        $fixture = OpgSectionImportFixtureBuilder::juneOpgMonthWorkbook();
        $this->tempFiles[] = $fixture;
        $report = $this->importUploadedFile($fixture, 'daily report June 2026.xlsx');

        $clinicOpgDoctor = Doctor::query()->where('code', OpgClinicDoctor::CODE)->firstOrFail();
        $summary = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth('2026-06')
            ->firstWhere('doctorId', $clinicOpgDoctor->id);

        $this->assertNotNull($summary);
        $this->assertSame('600.00', $summary->opgNormalValueAed);
        $this->assertSame('0.00', $summary->opg3dValueAed);
        $this->assertSame('600.00', $summary->totalCollectedAed);
    }

    public function test_opg_without_nurse_counts_toward_opg_treatment_value(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();

        $report = $this->importFixture(OpgSectionImportFixtureBuilder::tag3OpgDhsWorkbook(), 'daily report June 2026.xlsx');
        $workItem = WorkItem::query()
            ->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))
            ->with('treatment')
            ->firstOrFail();

        $this->assertNull($workItem->nurse_id);
        $this->assertSame(
            '200.00',
            app(OpgTreatmentValueAggregator::class)->lineValueAedFromWorkItem($workItem),
        );
    }

    public function test_opg_without_nurse_does_not_create_commission_snapshot(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();

        $report = $this->importFixture(OpgSectionImportFixtureBuilder::tag3OpgDhsWorkbook(), 'daily report June 2026.xlsx');
        $workItem = WorkItem::query()
            ->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))
            ->firstOrFail();

        $this->assertNull($workItem->fresh()->nurseCommission);
        $this->assertSame(0, NurseCommission::query()->where('work_item_id', $workItem->id)->count());
    }

    public function test_opg_without_nurse_emits_nurse_commission_incomplete_warning(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();

        $report = $this->importFixture(OpgSectionImportFixtureBuilder::tag3OpgDhsWorkbook(), 'daily report June 2026.xlsx');
        $row = $report->dailyWorkRows()->with('workItems.treatment', 'workItems.nurseCommission')->firstOrFail();

        $warnings = app(TreatmentImportValidationService::class)->collectNurseCommissionWarnings($row);

        $this->assertNotEmpty($warnings);
        $this->assertSame('nurse_commission_incomplete', $warnings[0]->warningCode);
    }

    public function test_unmapped_comment_text_is_not_used_as_nurse_alias(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();
        $this->createNurseWithRate('Jiji');

        $report = $this->importFixture(
            OpgSectionImportFixtureBuilder::opgWithUnmappedCommentWorkbook(),
            'daily report June 2026.xlsx',
        );

        $workItem = WorkItem::query()
            ->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))
            ->firstOrFail();

        $this->assertNull($workItem->nurse_id);
        $this->assertFalse($report->importWarnings()->where('warning_code', 'nurse_not_found')->exists());
    }

    public function test_ambiguous_nurse_alias_creates_warning(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();
        $treatment = Treatment::query()->where('code', OpgTreatmentCodes::NORMAL)->firstOrFail();

        foreach (['Jiji One', 'Jiji Two'] as $name) {
            $nurse = Nurse::factory()->create([
                'clinic_id' => $this->clinic111()->id,
                'name' => $name,
                'code' => strtoupper(str_replace(' ', '_', $name)),
                'is_active' => true,
            ]);

            NurseCommissionRate::factory()->create([
                'clinic_id' => $this->clinic111()->id,
                'nurse_id' => $nurse->id,
                'treatment_id' => $treatment->id,
                'commission_percentage' => '5.00',
                'is_active' => true,
            ]);
        }

        $report = $this->importFixture(
            OpgSectionImportFixtureBuilder::tag30OpgNurseInTreatmentTextWorkbook(),
            'daily report June 2026.xlsx',
        );

        $this->assertTrue($report->importWarnings()->where('warning_code', 'nurse_ambiguous')->exists());
    }

    public function test_historical_opg_value_unchanged_after_treatment_price_change(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();

        $report = $this->importFixture(OpgSectionImportFixtureBuilder::tag3OpgDhsWorkbook(), 'daily report June 2026.xlsx');
        $report->update(['status' => ReportStatus::Calculated]);

        $clinicOpgDoctor = Doctor::query()->where('code', OpgClinicDoctor::CODE)->firstOrFail();
        $before = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth('2026-06')
            ->firstWhere('doctorId', $clinicOpgDoctor->id);

        $this->assertNotNull($before);
        $this->assertSame('200.00', $before->opgNormalValueAed);

        Treatment::query()->where('code', OpgTreatmentCodes::NORMAL)->firstOrFail()->update([
            'treatment_price' => '250.00',
        ]);

        $after = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth('2026-06')
            ->firstWhere('doctorId', $clinicOpgDoctor->id);

        $this->assertNotNull($after);
        $this->assertSame(
            '200.00',
            $after->opgNormalValueAed,
            'Historical OPG treatment value must not change when admin updates treatment price later.',
        );
    }

    public function test_new_work_item_after_treatment_price_change_uses_updated_snapshot(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();

        Treatment::query()->where('code', OpgTreatmentCodes::NORMAL)->firstOrFail()->update([
            'treatment_price' => '250.00',
        ]);

        $report = $this->importFixture(OpgSectionImportFixtureBuilder::tag3OpgDhsWorkbook(), 'daily report June 2026.xlsx');
        $report->update(['status' => ReportStatus::Calculated]);

        $clinicOpgDoctor = Doctor::query()->where('code', OpgClinicDoctor::CODE)->firstOrFail();
        $summary = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth('2026-06')
            ->firstWhere('doctorId', $clinicOpgDoctor->id);

        $this->assertNotNull($summary);
        $this->assertSame('250.00', $summary->opgNormalValueAed);
    }

    public function test_reimport_same_opg_file_does_not_duplicate_work_items_or_commissions(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();
        $this->createNurseWithRate('Jiji');

        $fixture = OpgSectionImportFixtureBuilder::tag30OpgNurseInTreatmentTextWorkbook();
        $this->tempFiles[] = $fixture;

        $first = $this->importUploadedFile($fixture, 'daily report June 2026.xlsx');
        $second = $this->importUploadedFile($fixture, 'daily report June 2026.xlsx');

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(1, WorkItem::query()->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $second->id))->count());
        $this->assertSame(1, NurseCommission::query()->whereHas('workItem.dailyWorkRow', fn ($q) => $q->where('daily_report_id', $second->id))->count());
    }

    public function test_june_opg_days_persist_as_sheet_days_3_19_and_30(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();
        $this->createNurseWithRate('Jiji');

        $fixture = OpgSectionImportFixtureBuilder::juneOpgMonthWorkbook();
        $this->tempFiles[] = $fixture;
        $report = $this->importUploadedFile($fixture, 'daily report June 2026.xlsx');
        $report->update(['status' => ReportStatus::Calculated]);

        $clinicOpgDoctor = Doctor::query()->where('code', OpgClinicDoctor::CODE)->firstOrFail();
        $exportPath = app(DoctorsIncomeExcelExportService::class)->exportForReport($report->fresh());
        $exportProfile = app(DoctorIncomeExportProfileProvisioner::class)->ensureForDoctor($clinicOpgDoctor);
        $sheet = IOFactory::load($exportPath)->getSheetByName($exportProfile['sheet_name']);
        $firstDayRow = (int) $exportProfile['first_day_row'];

        $rowsByDay = $report->dailyWorkRows()
            ->where('doctor_id', $clinicOpgDoctor->id)
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn ($row) => (int) $row->work_date->format('j'));

        foreach ([3, 19, 30] as $expectedDay) {
            $row = $rowsByDay->get($expectedDay);
            $this->assertNotNull($row, "Expected OPG row for day {$expectedDay}");

            $sheetDay = (int) ($row->raw_data_json['sheet_day'] ?? 0);
            $this->assertSame($expectedDay, $sheetDay, "raw_data_json sheet_day for day {$expectedDay}");
            $this->assertSame(
                sprintf('2026-06-%02d', $expectedDay),
                $row->work_date->toDateString(),
                "work_date for day {$expectedDay}",
            );

            $exportValue = (float) $sheet->getCell('T'.($firstDayRow + $expectedDay - 1))->getCalculatedValue();
            $this->assertSame(200.0, $exportValue, "Excel OPG-Normal export for day {$expectedDay}");

            $commission = NurseCommission::query()
                ->whereHas('workItem', fn ($q) => $q->where('daily_work_row_id', $row->id))
                ->first();

            if ($expectedDay === 3) {
                $this->assertNull($commission, 'Day 3 OPG has no nurse commission');
            } else {
                $this->assertNotNull($commission, "Day {$expectedDay} OPG must have nurse commission");
                $this->assertSame($expectedDay, (int) $commission->workItem->dailyWorkRow->work_date->format('j'));
            }
        }
    }

    public function test_opg_revenue_is_not_double_counted_in_practice_overview(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();
        $this->createNurseWithRate('Jiji');

        $fixture = OpgSectionImportFixtureBuilder::juneOpgMonthWorkbook();
        $this->tempFiles[] = $fixture;
        $report = $this->importUploadedFile($fixture, 'daily report June 2026.xlsx');
        $report->update(['status' => ReportStatus::Calculated]);

        $clinicOpgDoctor = Doctor::query()->where('code', OpgClinicDoctor::CODE)->firstOrFail();
        $monthly = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth('2026-06')
            ->firstWhere('doctorId', $clinicOpgDoctor->id);
        $overview = app(ClinicFinancialOverviewService::class)->build('2026-06');

        $this->assertNotNull($monthly);
        $this->assertSame('600.00', $monthly->totalCollectedAed);
        $this->assertSame('0.00', $monthly->doctorIncomeAed);
        $this->assertSame('600.00', $overview->revenue->amount);
        $this->assertSame('600.00', $overview->opgNormalValue->amount);
        $this->assertSame($monthly->totalCollectedAed, $overview->revenue->amount);
    }

    public function test_june_fixture_reports_opg_values_across_accounting_surfaces(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();
        $this->createNurseWithRate('Jiji');

        $fixture = OpgSectionImportFixtureBuilder::juneOpgMonthWorkbook();
        $this->tempFiles[] = $fixture;
        $report = $this->importUploadedFile($fixture, 'daily report June 2026.xlsx');
        $report->update(['status' => ReportStatus::Calculated]);

        $clinicOpgDoctor = Doctor::query()->where('code', OpgClinicDoctor::CODE)->firstOrFail();
        $summary = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth('2026-06')
            ->firstWhere('doctorId', $clinicOpgDoctor->id);

        $overview = app(ClinicFinancialOverviewService::class)->build('2026-06');
        $exportPath = app(DoctorsIncomeExcelExportService::class)->exportForReport($report->fresh());
        $exportProfile = app(DoctorIncomeExportProfileProvisioner::class)->ensureForDoctor($clinicOpgDoctor);
        $sheet = IOFactory::load($exportPath)->getSheetByName($exportProfile['sheet_name']);

        $this->assertNotNull($summary);
        $this->assertSame('600.00', $summary->opgNormalValueAed);
        $this->assertSame('0.00', $summary->doctorIncomeAed);
        $this->assertSame('600.00', $summary->totalCollectedAed);
        $this->assertSame('600.00', $overview->opgNormalValue->amount);
        $this->assertNotNull($sheet);

        $firstDayRow = (int) $exportProfile['first_day_row'];
        $this->assertSame(200.0, (float) $sheet->getCell('T'.($firstDayRow + 3 - 1))->getCalculatedValue());
        $this->assertSame(200.0, (float) $sheet->getCell('T'.($firstDayRow + 19 - 1))->getCalculatedValue());
        $this->assertSame(200.0, (float) $sheet->getCell('T'.($firstDayRow + 30 - 1))->getCalculatedValue());
        $this->assertSame(
            2,
            NurseCommission::query()->whereHas('workItem.dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))->count(),
        );
    }

    public function test_clinic_opg_doctor_is_provisioned_once_with_zero_commission(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $clinic = $this->clinic111();

        app(OpgClinicDoctorProvisioner::class)->provisionForClinic($clinic);
        app(OpgClinicDoctorProvisioner::class)->provisionForClinic($clinic);

        $doctors = Doctor::query()
            ->where('clinic_id', $clinic->id)
            ->where('code', OpgClinicDoctor::CODE)
            ->get();

        $this->assertCount(1, $doctors);
        $this->assertSame('0.00', (string) $doctors->first()->commission_percentage);
    }

    public function test_clinic_opg_doctor_is_scoped_to_current_clinic_only(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->provisionOpg();
        $this->seedClinic222Tenant();

        $this->assertSame(
            1,
            Doctor::query()->where('clinic_id', $this->clinic111()->id)->where('code', OpgClinicDoctor::CODE)->count(),
        );
        $this->assertSame(
            0,
            Doctor::query()->where('clinic_id', $this->clinic222()->id)->where('code', OpgClinicDoctor::CODE)->count(),
        );
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

    private function importFixture(string $fixturePath, string $originalName)
    {
        $this->tempFiles[] = $fixturePath;

        return $this->importUploadedFile($fixturePath, $originalName);
    }

    private function importUploadedFile(string $fixturePath, string $originalName)
    {
        Storage::fake('local');
        config(['accounting.upload.disk' => 'local', 'accounting.upload.directory' => 'imports']);

        $uploaded = new UploadedFile($fixturePath, $originalName, null, null, true);

        return app(DailyReportImportService::class)->import($uploaded)->fresh(['dailyWorkRows.payments', 'dailyWorkRows.workItems', 'importWarnings']);
    }
}
