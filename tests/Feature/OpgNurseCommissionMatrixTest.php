<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\NurseCommission;
use App\Models\NurseCommissionRate;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\Accounting\LabJobCalculationService;
use App\Services\Accounting\MonthlyIncomeCalculationService;
use App\Services\Accounting\NurseCommissionCalculationService;
use App\Services\Configuration\OpgTreatmentProvisioner;
use App\Services\Import\DailyReportImportService;
use App\Services\Import\TreatmentImportValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpgNurseCommissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function opgTreatment(string $code = 'OPG_NORMAL', array $overrides = []): Treatment
    {
        $defaults = [
            'code' => $code,
            'name' => $code === 'OPG_3D' ? 'OPG 3D' : 'OPG-Normal',
            'treatment_price' => $code === 'OPG_3D' ? '360.00' : '200.00',
            'treatment_price_currency' => 'AED',
        ];

        $treatment = Treatment::query()->create($this->withClinicId(array_merge($defaults, $overrides)));
        $treatment->requires_nurse_commission = true;
        $treatment->has_lab_cost = false;
        $treatment->is_active = true;
        $treatment->save();

        return $treatment->fresh();
    }

    private function nurseWithRate(Treatment $treatment, string $percentage = '5.00'): Nurse
    {
        $nurse = Nurse::factory()->create([
            'clinic_id' => $treatment->clinic_id,
            'is_active' => true,
        ]);

        NurseCommissionRate::factory()->create([
            'clinic_id' => $treatment->clinic_id,
            'nurse_id' => $nurse->id,
            'treatment_id' => $treatment->id,
            'commission_percentage' => $percentage,
            'is_active' => true,
        ]);

        return $nurse;
    }

    public function test_opg_provisioning_is_idempotent_and_preserves_custom_prices(): void
    {
        $clinic = $this->clinic111();

        Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'OPG_NORMAL',
            'name' => 'Custom OPG',
            'treatment_price' => '250.00',
            'treatment_price_currency' => 'AED',
        ]);
        $existing = Treatment::query()->where('clinic_id', $clinic->id)->where('code', 'OPG_NORMAL')->firstOrFail();
        $existing->requires_nurse_commission = true;
        $existing->has_lab_cost = false;
        $existing->is_active = true;
        $existing->save();

        $result = app(OpgTreatmentProvisioner::class)->provisionForClinic($clinic->fresh());

        $this->assertNotContains('OPG_NORMAL', $result->created);
        $this->assertSame('250.00', (string) Treatment::query()->where('code', 'OPG_NORMAL')->value('treatment_price'));
    }

    public function test_nurse_commission_rate_create_update_deactivate_activate(): void
    {
        $this->seedAccountingData();
        $admin = $this->authenticateAdmin();
        $treatment = $this->opgTreatment();
        $nurse = Nurse::factory()->create(['clinic_id' => $treatment->clinic_id, 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('nurses.commission-rates.store', $nurse), [
                'treatment_id' => $treatment->id,
                'commission_percentage' => '5.00',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $rate = NurseCommissionRate::query()->where('treatment_id', $treatment->id)->firstOrFail();

        $this->actingAs($admin)
            ->put(route('nurses.commission-rates.update', [$nurse, $rate]), [
                'commission_percentage' => '7.50',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('7.50', (string) $rate->fresh()->commission_percentage);

        $this->actingAs($admin)
            ->delete(route('nurses.commission-rates.destroy', [$nurse, $rate]))
            ->assertRedirect();

        $this->assertFalse($rate->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('nurses.commission-rates.activate', [$nurse, $rate->fresh()]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($rate->fresh()->is_active);
    }

    public function test_duplicate_active_rate_returns_validation_error_not_500(): void
    {
        $this->seedAccountingData();
        $admin = $this->authenticateAdmin();
        $treatment = $this->opgTreatment();
        $nurse = $this->nurseWithRate($treatment);

        $response = $this->actingAs($admin)
            ->from(route('nurses.index'))
            ->post(route('nurses.commission-rates.store', $nurse), [
                'treatment_id' => $treatment->id,
                'commission_percentage' => '6.00',
            ]);

        $response->assertRedirect(route('nurses.index'));
        $response->assertSessionHasErrors('treatment_id');
        $response->assertStatus(302);
    }

    public function test_rate_on_ineligible_treatment_is_rejected(): void
    {
        $this->seedAccountingData();
        $admin = $this->authenticateAdmin();
        $treatment = Treatment::query()->where('code', 'MC')->firstOrFail();
        $nurse = Nurse::factory()->create(['clinic_id' => $treatment->clinic_id, 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->from(route('nurses.index'))
            ->post(route('nurses.commission-rates.store', $nurse), [
                'treatment_id' => $treatment->id,
                'commission_percentage' => '5.00',
            ]);

        $response->assertRedirect(route('nurses.index'));
        $response->assertSessionHasErrors('treatment_id');
    }

    public function test_editor_save_without_nurse_returns_422_not_500(): void
    {
        $this->seedAccountingData();
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = $this->opgTreatment();

        $report = $this->createDailyReport([
            'report_date' => '2026-09-01',
            'source_type' => 'manual_entry',
            'status' => 'uploaded',
        ]);

        $response = $this->actingAs($accountant)->postJson(route('daily-report.rows.save', $report), [
            'doctor_id' => $doctor->id,
            'day' => 1,
            'dhs_amount' => '200',
            'treatment_lines' => [
                ['code' => $treatment->code, 'quantity' => 1],
            ],
        ]);

        $this->assertEditorValidationError($response, 'treatment_lines.0.nurse_id');
    }

    private function assertEditorValidationError($response, string $field): void
    {
        $this->assertNotSame(500, $response->status());
        $errors = $response->json('errors') ?? session('errors')?->getMessages() ?? [];
        $this->assertArrayHasKey($field, $errors);
        $this->assertStringNotContainsString('RuntimeException', $response->getContent());
    }

    public function test_editor_save_without_rate_returns_422(): void
    {
        $this->seedAccountingData();
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = $this->opgTreatment();
        $nurse = Nurse::factory()->create(['clinic_id' => $treatment->clinic_id, 'is_active' => true]);

        $report = $this->createDailyReport([
            'report_date' => '2026-09-01',
            'source_type' => 'manual_entry',
            'status' => 'uploaded',
        ]);

        $response = $this->actingAs($accountant)->postJson(route('daily-report.rows.save', $report), [
            'doctor_id' => $doctor->id,
            'day' => 1,
            'dhs_amount' => '200',
            'treatment_lines' => [
                ['code' => $treatment->code, 'quantity' => 1, 'nurse_id' => $nurse->id],
            ],
        ]);

        $this->assertEditorValidationError($response, 'treatment_lines.0.nurse_id');
    }

    public function test_editor_save_without_treatment_price_returns_422(): void
    {
        $this->seedAccountingData();
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = $this->opgTreatment('OPG_NORMAL', ['treatment_price' => null, 'treatment_price_currency' => null]);
        $nurse = $this->nurseWithRate($treatment);

        $report = $this->createDailyReport([
            'report_date' => '2026-09-01',
            'source_type' => 'manual_entry',
            'status' => 'uploaded',
        ]);

        $response = $this->actingAs($accountant)->postJson(route('daily-report.rows.save', $report), [
            'doctor_id' => $doctor->id,
            'day' => 1,
            'dhs_amount' => '200',
            'treatment_lines' => [
                ['code' => $treatment->code, 'quantity' => 1, 'nurse_id' => $nurse->id],
            ],
        ]);

        $this->assertEditorValidationError($response, 'treatment_lines.0.code');
    }

    public function test_editor_rejects_cross_clinic_nurse_with_422(): void
    {
        $this->seedAccountingData();
        $tenant = $this->seedClinic222Tenant();
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = $this->opgTreatment();

        $report = $this->createDailyReport([
            'report_date' => '2026-09-01',
            'source_type' => 'manual_entry',
            'status' => 'uploaded',
        ]);

        $foreignNurseModel = Nurse::factory()->create(['clinic_id' => $tenant['clinic']->id, 'is_active' => true]);

        $response = $this->actingAs($accountant)->postJson(route('daily-report.rows.save', $report), [
            'doctor_id' => $doctor->id,
            'day' => 1,
            'dhs_amount' => '200',
            'treatment_lines' => [
                ['code' => $treatment->code, 'quantity' => 1, 'nurse_id' => $foreignNurseModel->id],
            ],
        ]);

        $this->assertEditorValidationError($response, 'treatment_lines.0.nurse_id');
    }

    public function test_editor_catalog_includes_nurses_for_opg_only(): void
    {
        $this->seedAccountingData();
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $opg = $this->opgTreatment();
        $nurse = $this->nurseWithRate($opg, '5.00');

        $response = $this->actingAs($accountant)
            ->getJson(route('doctors.treatments', $doctor).'?month=2026-10&day=1');

        $response->assertOk();
        $catalog = collect($response->json('data'));
        $opgEntry = $catalog->firstWhere('code', 'OPG_NORMAL');
        $mcEntry = $catalog->firstWhere('code', 'MC');

        $this->assertTrue($opgEntry['requires_nurse_commission']);
        $this->assertNotEmpty($opgEntry['nurses']);
        $this->assertSame($nurse->id, $opgEntry['nurses'][0]['id']);
        $this->assertFalse($mcEntry['requires_nurse_commission']);
    }

    public function test_commission_calculation_examples(): void
    {
        $this->seedAccountingData();
        $doctor = Doctor::query()->where('is_active', true)->firstOrFail();

        $aedTreatment = $this->opgTreatment('OPG_NORMAL');
        $nurseAed = $this->nurseWithRate($aedTreatment, '5.00');

        $report = $this->createDailyReport(['report_date' => '2026-11-01', 'source_type' => 'manual_entry', 'status' => 'uploaded']);
        $row = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-11-01',
            'treatment_text' => 'OPG_NORMAL x 3',
            'raw_data_json' => ['nurse_assignments' => ['OPG_NORMAL' => $nurseAed->id]],
        ]);
        $item = $this->createWorkItem($row, ['treatment_id' => $aedTreatment->id, 'quantity' => 3, 'nurse_id' => $nurseAed->id]);
        app(NurseCommissionCalculationService::class)->calculateForWorkRow($row);
        $commission = $item->fresh()->nurseCommission;
        $this->assertSame('10.00', (string) $commission->unit_commission_aed);
        $this->assertSame('30.00', (string) $commission->total_commission_aed);

        $usdTreatment = $this->opgTreatment('OPG_USD', [
            'code' => 'OPG_USD',
            'name' => 'OPG USD',
            'treatment_price' => '100.00',
            'treatment_price_currency' => 'USD',
        ]);
        $nurseUsd = $this->nurseWithRate($usdTreatment, '5.00');
        $rowUsd = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-11-02',
            'treatment_text' => 'OPG_USD x 1',
            'raw_data_json' => ['nurse_assignments' => ['OPG_USD' => $nurseUsd->id]],
        ]);
        $itemUsd = $this->createWorkItem($rowUsd, ['treatment_id' => $usdTreatment->id, 'quantity' => 1, 'nurse_id' => $nurseUsd->id]);
        app(NurseCommissionCalculationService::class)->calculateForWorkRow($rowUsd);
        $this->assertSame('18.25', (string) $itemUsd->fresh()->nurseCommission->total_commission_aed);
    }

    public function test_opg_without_lab_cost_produces_no_lab_warning(): void
    {
        $this->seedAccountingData();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = $this->opgTreatment();

        $report = $this->createDailyReport(['report_date' => '2026-12-01', 'source_type' => 'manual_entry', 'status' => 'uploaded']);
        $row = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-12-01',
            'treatment_text' => 'OPG_NORMAL x 1',
        ]);
        $item = $this->createWorkItem($row, ['treatment_id' => $treatment->id, 'quantity' => 1]);

        app(LabJobCalculationService::class)->calculateForWorkRow($row);

        $this->assertNull($item->fresh()->labJob);
        $labWarnings = app(TreatmentImportValidationService::class)->collectLabPriceWarnings($row->fresh(['workItems.treatment']));
        $this->assertSame([], $labWarnings);
    }

    public function test_import_without_nurse_sets_needs_review_warning(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = $this->opgTreatment();

        $report = $this->createDailyReport([
            'report_date' => '2027-01-01',
            'source_type' => 'manual_entry',
            'status' => 'calculated',
        ]);

        $row = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2027-01-01',
            'treatment_text' => 'OPG_NORMAL x 1',
        ]);
        $this->createWorkItem($row, ['treatment_id' => $treatment->id, 'quantity' => 1]);

        app(DailyReportImportService::class)->processParsedReport($report->fresh());

        $report->refresh();
        $this->assertSame(ReportStatus::NeedsReview, $report->status);

        $warnings = app(TreatmentImportValidationService::class)->collectNurseCommissionWarnings($row->fresh(['workItems.treatment', 'workItems.nurseCommission']));
        $this->assertCount(1, $warnings);
        $this->assertSame('nurse_commission_incomplete', $warnings[0]->warningCode);
    }

    public function test_web_approve_incomplete_opg_shows_flash_not_500(): void
    {
        $this->seedAccountingData();
        $admin = $this->authenticateAdmin();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = $this->opgTreatment();

        $report = $this->createDailyReport([
            'report_date' => '2027-02-01',
            'source_type' => 'manual_entry',
            'status' => 'needs_review',
        ]);

        $row = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2027-02-01',
            'treatment_text' => 'OPG_NORMAL x 1',
        ]);
        $this->createWorkItem($row, ['treatment_id' => $treatment->id, 'quantity' => 1]);

        $response = $this->actingAs($admin)
            ->from(route('daily-report.edit', $report))
            ->post(route('daily-report.approve', $report));

        $response->assertRedirect(route('daily-report.edit', $report));
        $response->assertSessionHasErrors('approve');
        $response->assertStatus(302);
        $this->assertStringContainsString('nurse', strtolower(session('errors')->first('approve')));
    }

    public function test_monthly_income_includes_opg_and_nurse_commission_without_double_counting(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = $this->opgTreatment();
        $nurse = $this->nurseWithRate($treatment);

        $report = $this->createDailyReport(['report_date' => '2027-03-01', 'source_type' => 'manual_entry', 'status' => 'calculated']);
        $row = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2027-03-01',
            'treatment_text' => 'OPG_NORMAL x 3',
            'dhs_amount' => '500.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '0.00',
            'usd_to_aed_amount' => '0.00',
            'visa_amount' => '0.00',
            'paid_total_aed' => '500.00',
            'raw_data_json' => ['nurse_assignments' => ['OPG_NORMAL' => $nurse->id]],
        ]);
        $item = $this->createWorkItem($row, ['treatment_id' => $treatment->id, 'quantity' => 3, 'nurse_id' => $nurse->id]);
        app(NurseCommissionCalculationService::class)->calculateForWorkRow($row);
        app(DailyReportImportService::class)->processParsedReport($report->fresh());

        $summary = app(MonthlyIncomeCalculationService::class)->calculateForMonth('2027-03')
            ->firstWhere('doctorId', $doctor->id);

        $this->assertNotNull($summary);
        $this->assertSame('600.00', $summary->opgNormalValueAed);
        $this->assertSame('30.00', $summary->nurseCommissionAed);
    }

    public function test_monthly_income_opg_value_matches_hyphenated_treatment_code(): void
    {
        $this->seedAccountingData();
        $this->authenticateAdmin();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = $this->opgTreatment('OPG-NORMAL');
        $nurse = $this->nurseWithRate($treatment);

        $report = $this->createDailyReport(['report_date' => '2027-05-01', 'source_type' => 'manual_entry', 'status' => 'calculated']);
        $row = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2027-05-01',
            'treatment_text' => 'OPG-NORMAL x 1',
            'dhs_amount' => '200.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '0.00',
            'usd_to_aed_amount' => '0.00',
            'visa_amount' => '0.00',
            'paid_total_aed' => '200.00',
            'raw_data_json' => ['nurse_assignments' => ['OPG-NORMAL' => $nurse->id]],
        ]);
        $this->createWorkItem($row, ['treatment_id' => $treatment->id, 'quantity' => 1, 'nurse_id' => $nurse->id]);
        app(NurseCommissionCalculationService::class)->calculateForWorkRow($row);
        app(DailyReportImportService::class)->processParsedReport($report->fresh());

        $summary = app(MonthlyIncomeCalculationService::class)->calculateForMonth('2027-05')
            ->firstWhere('doctorId', $doctor->id);

        $this->assertNotNull($summary);
        $this->assertSame('200.00', $summary->opgNormalValueAed);
        $this->assertSame('10.00', $summary->nurseCommissionAed);
    }

    public function test_monthly_income_web_page_loads_for_viewer(): void
    {
        $this->seedAccountingData();
        $viewer = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('monthly-income.index', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee('Nurse Commission');
    }

    public function test_nurse_admin_page_shows_commission_rates_section(): void
    {
        $this->seedAccountingData();
        $admin = $this->authenticateAdmin();
        $treatment = $this->opgTreatment();
        $nurse = $this->nurseWithRate($treatment);

        $this->actingAs($admin)
            ->get(route('nurses.index'))
            ->assertOk()
            ->assertSee('Nurse commission rates')
            ->assertSee($nurse->name);
    }

    public function test_activate_rate_with_inactive_nurse_is_rejected(): void
    {
        $this->seedAccountingData();
        $admin = $this->authenticateAdmin();
        $treatment = $this->opgTreatment();
        $nurse = Nurse::factory()->create(['clinic_id' => $treatment->clinic_id, 'is_active' => false]);

        $rate = NurseCommissionRate::factory()->create([
            'clinic_id' => $treatment->clinic_id,
            'nurse_id' => $nurse->id,
            'treatment_id' => $treatment->id,
            'commission_percentage' => '5.00',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('nurses.index'))
            ->post(route('nurses.commission-rates.activate', [$nurse, $rate]))
            ->assertRedirect(route('nurses.index'))
            ->assertSessionHasErrors('nurse_id');
    }

    public function test_successful_editor_save_persists_nurse_commission_snapshot(): void
    {
        $this->seedAccountingData();
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = $this->opgTreatment();
        $nurse = $this->nurseWithRate($treatment);

        $report = $this->createDailyReport([
            'report_date' => '2027-04-01',
            'source_type' => 'manual_entry',
            'status' => 'uploaded',
        ]);

        $response = $this->actingAs($accountant)->postJson(route('daily-report.rows.save', $report), [
            'doctor_id' => $doctor->id,
            'day' => 4,
            'dhs_amount' => '200',
            'treatment_lines' => [
                ['code' => $treatment->code, 'quantity' => 2, 'nurse_id' => $nurse->id],
            ],
        ]);

        $response->assertOk();
        $workItem = WorkItem::query()->whereHas('dailyWorkRow', fn ($q) => $q->where('daily_report_id', $report->id))->firstOrFail();
        $this->assertSame($nurse->id, $workItem->nurse_id);
        $this->assertInstanceOf(NurseCommission::class, $workItem->fresh()->nurseCommission);
        $this->assertSame('20.00', (string) $workItem->nurseCommission->total_commission_aed);
    }
}
