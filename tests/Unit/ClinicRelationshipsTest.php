<?php

namespace Tests\Unit;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_clinic_has_many_configuration_relations(): void
    {
        $clinic = $this->clinic111();

        $this->assertGreaterThan(0, $clinic->users()->count());
        $this->assertGreaterThan(0, $clinic->doctors()->count());
        $this->assertGreaterThan(0, $clinic->labs()->count());
        $this->assertGreaterThan(0, $clinic->treatments()->count());
        $this->assertGreaterThan(0, $clinic->labPrices()->count());
        $this->assertGreaterThan(0, $clinic->doctorFixedFees()->count());
    }

    public function test_configuration_models_belong_to_clinic(): void
    {
        $clinic = $this->clinic111();

        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $lab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'ZIR')->firstOrFail();
        $labPrice = LabPrice::query()->where('treatment_id', $treatment->id)->where('lab_id', $lab->id)->firstOrFail();
        $fixedFee = DoctorFixedFee::query()->where('doctor_id', Doctor::query()->where('code', 'WA')->value('id'))->firstOrFail();

        $this->assertTrue($user->clinic->is($clinic));
        $this->assertTrue($doctor->clinic->is($clinic));
        $this->assertTrue($lab->clinic->is($clinic));
        $this->assertTrue($treatment->clinic->is($clinic));
        $this->assertTrue($labPrice->clinic->is($clinic));
        $this->assertTrue($fixedFee->clinic->is($clinic));
    }

    public function test_new_configuration_record_defaults_to_clinic_111(): void
    {
        $clinic = $this->clinic111();

        $lab = Lab::query()->create([
            'name' => 'Relationship Test Lab',
            'code' => 'REL_TEST_LAB',
        ]);

        $this->assertSame($clinic->id, $lab->clinic_id);
        $this->assertTrue($lab->clinic->is($clinic));
    }
}
