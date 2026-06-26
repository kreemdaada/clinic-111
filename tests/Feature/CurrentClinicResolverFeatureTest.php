<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentClinicResolverFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_created_lab_belongs_to_authenticated_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('labs.store'), [
                'name' => 'Feature Resolver Lab',
                'code' => 'FEAT_RES_LAB',
            ])
            ->assertRedirect(route('labs.index'));

        $lab = Lab::query()->where('code', 'FEAT_RES_LAB')->firstOrFail();
        $this->assertSame($admin->clinic_id, $lab->clinic_id);
    }

    public function test_created_doctor_belongs_to_authenticated_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('doctors.store'), [
                'name' => 'Dr Feature',
                'code' => 'FEAT_DOC',
                'commission_type' => 'percentage',
                'commission_percentage' => 30,
                'default_lab_id' => $mainLab->id,
            ])
            ->assertRedirect(route('doctors.index'));

        $doctor = Doctor::query()->where('code', 'FEAT_DOC')->firstOrFail();
        $this->assertSame($admin->clinic_id, $doctor->clinic_id);
    }

    public function test_created_treatment_belongs_to_authenticated_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('treatments.store'), [
                'code' => 'FEAT_TX',
                'name' => 'Feature Treatment',
                'has_lab_cost' => '0',
            ])
            ->assertRedirect(route('treatments.index'));

        $treatment = Treatment::query()->where('code', 'FEAT_TX')->firstOrFail();
        $this->assertSame($admin->clinic_id, $treatment->clinic_id);
    }

    public function test_created_lab_price_belongs_to_authenticated_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $lab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'BG')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('lab-prices.store'), [
                'lab_id' => $lab->id,
                'treatment_id' => $treatment->id,
                'unit_cost' => '150.00',
                'currency' => 'AED',
            ])
            ->assertRedirect(route('lab-prices.index'));

        $price = LabPrice::query()
            ->where('lab_id', $lab->id)
            ->where('treatment_id', $treatment->id)
            ->where('doctor_id', null)
            ->where('unit_cost', '150.00')
            ->firstOrFail();

        $this->assertSame($admin->clinic_id, $price->clinic_id);
    }

    public function test_created_fixed_fee_belongs_to_authenticated_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'CF')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('doctor-fixed-fees.store'), [
                'doctor_id' => $doctor->id,
                'treatment_id' => $treatment->id,
                'fee_amount' => '99.00',
                'currency' => 'AED',
            ])
            ->assertRedirect(route('doctor-fixed-fees.index'));

        $fee = DoctorFixedFee::query()
            ->where('doctor_id', $doctor->id)
            ->where('treatment_id', $treatment->id)
            ->where('fee_amount', '99.00')
            ->firstOrFail();

        $this->assertSame($admin->clinic_id, $fee->clinic_id);
    }
}
