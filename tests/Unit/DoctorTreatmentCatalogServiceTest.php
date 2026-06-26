<?php

namespace Tests\Unit;

use App\Enums\CommissionType;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Services\DailyReport\DoctorTreatmentCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorTreatmentCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_doctor_only_gets_configured_treatments(): void
    {
        $this->seed();

        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $service = app(DoctorTreatmentCatalogService::class);

        $codes = $service->forDoctor($doctor)->pluck('code')->all();

        $this->assertContains('IMPL', $codes);
        $this->assertNotContains('ZIR', $codes);
    }

    public function test_percentage_doctor_gets_non_lab_and_billed_lab_treatments(): void
    {
        $this->seed();

        $doctor = Doctor::query()->where('code', 'PURIYA')->firstOrFail();
        $service = app(DoctorTreatmentCatalogService::class);

        $codes = $service->forDoctor($doctor)->pluck('code')->all();

        $this->assertContains('ZIR', $codes);
        $this->assertContains('CF', $codes);
        $this->assertContains('IMPL-ZIR', $codes);
    }

    public function test_new_percentage_doctor_sees_all_clinic_treatments_including_lab_cost(): void
    {
        $this->seed();

        $doctor = Doctor::query()->create($this->withClinicId([
            'name' => 'Dr Test',
            'code' => 'TESTDOC',
            'commission_type' => CommissionType::Percentage,
            'commission_percentage' => '30.00',
            'is_active' => true,
        ]));

        Treatment::query()->create($this->withClinicId([
            'code' => 'NEW_ZIR',
            'name' => 'New Zirconia',
            'has_lab_cost' => true,
            'is_active' => true,
        ]));

        $service = app(DoctorTreatmentCatalogService::class);
        $codes = $service->forDoctor($doctor)->pluck('code')->all();

        $this->assertContains('NEW_ZIR', $codes);
        $this->assertContains('CF', $codes);
    }

    public function test_clinic_catalog_does_not_include_other_clinic_treatments(): void
    {
        $this->seedAccountingData();
        $tenant = $this->seedClinic222Tenant();

        $service = app(DoctorTreatmentCatalogService::class);
        $codes = $service->forDoctor($tenant['doctor'])->pluck('code')->all();

        $this->assertContains('C222_TX', $codes);
        $this->assertNotContains('BG', $codes);
        $this->assertNotContains('CF', $codes);
    }

    public function test_riyad_post_shows_riyadh_lab_price_in_catalog(): void
    {
        $this->seed();

        $doctor = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $service = app(DoctorTreatmentCatalogService::class);

        $post = $service->forDoctor($doctor)->firstWhere('code', 'POST');

        $this->assertNotNull($post);
        $this->assertTrue($post['bills_lab_job']);
        $this->assertSame('55.00', $post['lab_price']['unit_cost_aed']);
        $this->assertSame('RIYADH_LAB', $post['lab_price']['lab_code']);
    }
}
