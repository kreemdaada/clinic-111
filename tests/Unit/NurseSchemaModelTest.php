<?php

namespace Tests\Unit;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\NurseCommission;
use App\Models\NurseCommissionRate;
use App\Models\Treatment;
use App\Models\TreatmentPrice;
use App\Models\WorkItem;
use Illuminate\Database\QueryException;
use RuntimeException;
use Tests\TestCase;

class NurseSchemaModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_requires_nurse_commission_defaults_to_false(): void
    {
        $clinic = $this->clinic111();

        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'XRAY_TEST',
            'name' => 'X-Ray Test Treatment',
        ]);

        $this->assertFalse($treatment->requires_nurse_commission);
        $this->assertDatabaseHas('treatments', [
            'id' => $treatment->id,
            'requires_nurse_commission' => false,
        ]);
    }

    public function test_nurse_code_is_unique_within_clinic(): void
    {
        $clinic = $this->clinic111();

        Nurse::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'NURSE_A',
            'name' => 'Nurse A',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        Nurse::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'NURSE_A',
            'name' => 'Nurse A Duplicate',
            'is_active' => true,
        ]);
    }

    public function test_same_nurse_code_is_allowed_in_different_clinics(): void
    {
        $tenant222 = $this->seedClinic222Tenant();

        Nurse::query()->create([
            'clinic_id' => $this->clinic111()->id,
            'code' => 'SHARED_NURSE',
            'name' => 'Clinic 111 Nurse',
            'is_active' => true,
        ]);

        $nurse222 = Nurse::query()->create([
            'clinic_id' => $tenant222['clinic']->id,
            'code' => 'SHARED_NURSE',
            'name' => 'Clinic 222 Nurse',
            'is_active' => true,
        ]);

        $this->assertSame('SHARED_NURSE', $nurse222->code);
        $this->assertDatabaseCount('nurses', 2);
    }

    public function test_nurse_belongs_to_clinic(): void
    {
        $clinic = $this->clinic111();
        $nurse = Nurse::factory()->forClinic($clinic)->create();

        $this->assertTrue($nurse->clinic->is($clinic));
        $this->assertTrue($clinic->nurses->contains($nurse));
    }

    public function test_treatment_price_belongs_to_clinic_and_treatment(): void
    {
        $clinic = $this->clinic111();
        $treatment = Treatment::query()->where('clinic_id', $clinic->id)->firstOrFail();

        $price = TreatmentPrice::factory()->create([
            'clinic_id' => $clinic->id,
            'treatment_id' => $treatment->id,
            'unit_price' => '250.00',
            'currency' => 'AED',
        ]);

        $this->assertTrue($price->clinic->is($clinic));
        $this->assertTrue($price->treatment->is($treatment));
        $this->assertTrue($treatment->treatmentPrices->contains($price));
        $this->assertTrue($clinic->treatmentPrices->contains($price));
    }

    public function test_nurse_commission_rate_belongs_to_clinic_nurse_and_treatment(): void
    {
        $clinic = $this->clinic111();
        $nurse = Nurse::factory()->forClinic($clinic)->create();
        $treatment = Treatment::query()->where('clinic_id', $clinic->id)->firstOrFail();

        $rate = NurseCommissionRate::factory()->create([
            'clinic_id' => $clinic->id,
            'nurse_id' => $nurse->id,
            'treatment_id' => $treatment->id,
            'commission_percentage' => '15.00',
        ]);

        $this->assertTrue($rate->clinic->is($clinic));
        $this->assertTrue($rate->nurse->is($nurse));
        $this->assertTrue($rate->treatment->is($treatment));
        $this->assertTrue($nurse->nurseCommissionRates->contains($rate));
        $this->assertTrue($treatment->nurseCommissionRates->contains($rate));
    }

    public function test_nurse_commission_belongs_to_one_work_item(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'MC')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
        ]);
        $workItem = $this->createWorkItem($workRow, ['treatment_id' => $treatment->id]);

        $commission = NurseCommission::factory()->forWorkItem($workItem)->create();

        $this->assertTrue($commission->workItem->is($workItem));
        $this->assertTrue($workItem->nurseCommission->is($commission));
    }

    public function test_database_prevents_two_nurse_commissions_for_same_work_item(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'MC')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
        ]);
        $workItem = $this->createWorkItem($workRow, ['treatment_id' => $treatment->id]);

        NurseCommission::factory()->forWorkItem($workItem)->create();

        $this->expectException(QueryException::class);

        NurseCommission::factory()->forWorkItem($workItem)->create();
    }

    public function test_nurse_commission_decimal_casts(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'MC')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
        ]);
        $workItem = $this->createWorkItem($workRow, ['treatment_id' => $treatment->id]);

        $commission = NurseCommission::factory()->forWorkItem($workItem)->create([
            'treatment_price_original' => '199.99',
            'exchange_rate_to_aed' => '3.6725',
            'treatment_price_aed' => '734.48',
            'commission_percentage' => '12.50',
            'unit_commission_aed' => '91.81',
            'total_commission_aed' => '91.81',
        ]);

        $commission->refresh();

        $this->assertSame('199.99', $commission->treatment_price_original);
        $this->assertSame('3.6725', $commission->exchange_rate_to_aed);
        $this->assertSame('734.48', $commission->treatment_price_aed);
        $this->assertSame('12.50', $commission->commission_percentage);
        $this->assertSame('91.81', $commission->unit_commission_aed);
        $this->assertSame('91.81', $commission->total_commission_aed);
    }

    public function test_nurse_commission_clinic_id_is_immutable(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'MC')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
        ]);
        $workItem = $this->createWorkItem($workRow, ['treatment_id' => $treatment->id]);
        $commission = NurseCommission::factory()->forWorkItem($workItem)->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('clinic_id is immutable');

        $commission->update(['clinic_id' => $commission->clinic_id + 1]);
    }

    public function test_all_nurse_schema_relationships_are_defined(): void
    {
        $clinic = $this->clinic111();
        $nurse = Nurse::factory()->forClinic($clinic)->create();
        $treatment = Treatment::query()->where('clinic_id', $clinic->id)->firstOrFail();

        $price = TreatmentPrice::factory()->create([
            'clinic_id' => $clinic->id,
            'treatment_id' => $treatment->id,
        ]);

        $rate = NurseCommissionRate::factory()->create([
            'clinic_id' => $clinic->id,
            'nurse_id' => $nurse->id,
            'treatment_id' => $treatment->id,
        ]);

        $doctor = Doctor::query()->where('clinic_id', $clinic->id)->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
        ]);
        $workItem = $this->createWorkItem($workRow, ['treatment_id' => $treatment->id]);
        $commission = NurseCommission::factory()->forWorkItem($workItem, $nurse)->create();

        $this->assertInstanceOf(Clinic::class, $nurse->clinic);
        $this->assertInstanceOf(Clinic::class, $price->clinic);
        $this->assertInstanceOf(Treatment::class, $price->treatment);
        $this->assertInstanceOf(Clinic::class, $rate->clinic);
        $this->assertInstanceOf(Nurse::class, $rate->nurse);
        $this->assertInstanceOf(Treatment::class, $rate->treatment);
        $this->assertInstanceOf(WorkItem::class, $commission->workItem);
        $this->assertInstanceOf(Nurse::class, $commission->nurse);
        $this->assertInstanceOf(Treatment::class, $commission->treatment);

        $this->assertTrue($clinic->nurses->contains($nurse));
        $this->assertTrue($clinic->treatmentPrices->contains($price));
        $this->assertTrue($clinic->nurseCommissionRates->contains($rate));
        $this->assertTrue($clinic->nurseCommissions->contains($commission));
        $this->assertTrue($nurse->nurseCommissions->contains($commission));
        $this->assertTrue($treatment->nurseCommissions->contains($commission));
    }

    public function test_nurse_factory_inactive_state(): void
    {
        $nurse = Nurse::factory()->inactive()->create();

        $this->assertFalse($nurse->is_active);
    }

    public function test_treatment_price_factory_inactive_state(): void
    {
        $price = TreatmentPrice::factory()->inactive()->create();

        $this->assertFalse($price->is_active);
    }

    public function test_nurse_commission_rate_factory_inactive_state(): void
    {
        $rate = NurseCommissionRate::factory()->inactive()->create();

        $this->assertFalse($rate->is_active);
    }
}
