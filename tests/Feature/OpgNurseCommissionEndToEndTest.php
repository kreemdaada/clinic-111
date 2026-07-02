<?php

namespace Tests\Feature;

use App\Exceptions\NurseCommissionApprovalBlockedException;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\NurseCommissionRate;
use App\Models\Treatment;
use App\Services\Accounting\NurseCommissionCalculationService;
use App\Services\Configuration\OpgTreatmentProvisioner;
use App\Services\DailyReport\DailyReportLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpgNurseCommissionEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_opg_provisioning_for_new_clinic(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'OPG Clinic',
            'code' => 'OPG_CLINIC',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'UAE',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $result = app(OpgTreatmentProvisioner::class)->provisionForClinic($clinic);

        $this->assertSame(['OPG_NORMAL', 'OPG_3D'], $result->created);

        $normal = Treatment::query()->where('clinic_id', $clinic->id)->where('code', 'OPG_NORMAL')->firstOrFail();
        $threeD = Treatment::query()->where('clinic_id', $clinic->id)->where('code', 'OPG_3D')->firstOrFail();

        $this->assertSame('200.00', (string) $normal->treatment_price);
        $this->assertSame('360.00', (string) $threeD->treatment_price);
        $this->assertTrue($normal->requires_nurse_commission);
        $this->assertTrue($threeD->requires_nurse_commission);
        $this->assertFalse($normal->has_lab_cost);
        $this->assertFalse($threeD->has_lab_cost);
    }

    public function test_similar_opg_code_reports_conflict_instead_of_duplicate(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Conflict Clinic',
            'code' => 'OPG_CONFLICT',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'UAE',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'OPG-NORMAL',
            'name' => 'Legacy OPG',
        ]);

        $result = app(OpgTreatmentProvisioner::class)->provisionForClinic($clinic);

        $this->assertNotContains('OPG_NORMAL', $result->created);
        $this->assertContains('OPG_3D', $result->created);
        $this->assertNotEmpty($result->conflicts);
        $this->assertStringContainsString('OPG-NORMAL', $result->conflicts[0]);
    }

    public function test_nurse_commission_calculation_uses_treatment_price_not_payment(): void
    {
        $this->seedAccountingData();
        $clinic = $this->clinic111();

        $treatment = Treatment::query()->create($this->withClinicId([
            'code' => 'OPG_NORMAL',
            'name' => 'OPG-Normal',
            'treatment_price' => '200.00',
            'treatment_price_currency' => 'AED',
        ]));
        $treatment->requires_nurse_commission = true;
        $treatment->has_lab_cost = false;
        $treatment->is_active = true;
        $treatment->save();

        $nurse = Nurse::factory()->create(['clinic_id' => $clinic->id, 'is_active' => true]);

        NurseCommissionRate::factory()->create([
            'clinic_id' => $clinic->id,
            'nurse_id' => $nurse->id,
            'treatment_id' => $treatment->id,
            'commission_percentage' => '5.00',
            'is_active' => true,
        ]);

        $report = $this->createDailyReport([
            'report_date' => '2026-07-01',
            'source_type' => 'manual_entry',
            'status' => 'uploaded',
        ]);

        $doctor = Doctor::query()->where('clinic_id', $clinic->id)->where('is_active', true)->firstOrFail();

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-07-01',
            'treatment_text' => 'OPG_NORMAL x 3',
            'paid_total_aed' => '0.00',
            'raw_data_json' => ['nurse_assignments' => ['OPG_NORMAL' => $nurse->id]],
        ]);

        $workItem = $this->createWorkItem($workRow, [
            'treatment_id' => $treatment->id,
            'quantity' => 3,
            'nurse_id' => $nurse->id,
        ]);

        app(NurseCommissionCalculationService::class)->calculateForWorkRow($workRow);

        $commission = $workItem->fresh()->nurseCommission;

        $this->assertNotNull($commission);
        $this->assertSame('10.00', (string) $commission->unit_commission_aed);
        $this->assertSame('30.00', (string) $commission->total_commission_aed);
        $this->assertNull($workItem->fresh()->labJob);
    }

    public function test_approve_blocked_without_nurse_commission_snapshot(): void
    {
        $this->seedAccountingData();
        $admin = $this->authenticateAdmin();

        $treatment = Treatment::query()->create($this->withClinicId([
            'code' => 'OPG_NORMAL',
            'name' => 'OPG-Normal',
            'treatment_price' => '200.00',
            'treatment_price_currency' => 'AED',
        ]));
        $treatment->requires_nurse_commission = true;
        $treatment->has_lab_cost = false;
        $treatment->is_active = true;
        $treatment->save();

        $report = $this->createDailyReport([
            'report_date' => '2026-08-01',
            'source_type' => 'manual_entry',
            'status' => 'needs_review',
        ]);

        $doctor = Doctor::query()->where('clinic_id', $this->clinic111()->id)->where('is_active', true)->firstOrFail();

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-08-01',
            'treatment_text' => 'OPG_NORMAL x 1',
        ]);

        $this->createWorkItem($workRow, [
            'treatment_id' => $treatment->id,
            'quantity' => 1,
        ]);

        $this->expectException(NurseCommissionApprovalBlockedException::class);
        $this->expectExceptionMessage('A nurse must be selected');

        app(DailyReportLockService::class)->approve($report->fresh(), $admin);
    }
}
