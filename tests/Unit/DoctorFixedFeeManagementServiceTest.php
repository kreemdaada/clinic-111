<?php

namespace Tests\Unit;

use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Treatment;
use App\Services\Accounting\DoctorFixedFeeManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DoctorFixedFeeManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private DoctorFixedFeeManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->service = app(DoctorFixedFeeManagementService::class);
    }

    public function test_create_fixed_fee(): void
    {
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $treatment = $this->createTreatment('DFF_SVC', 'DFF Service');

        $fee = $this->service->create([
            'doctor_id' => $doctor->id,
            'treatment_id' => $treatment->id,
            'fee_amount' => '450.00',
            'currency' => 'AED',
        ]);

        $this->assertTrue($fee->is_active);
        $this->assertSame('450.00', (string) $fee->fee_amount);
        $this->assertSame('AED', $fee->currency);
    }

    public function test_duplicate_active_fee_throws_validation_exception(): void
    {
        $existing = DoctorFixedFee::query()->where('is_active', true)->firstOrFail();

        $this->expectException(ValidationException::class);

        $this->service->create([
            'doctor_id' => $existing->doctor_id,
            'treatment_id' => $existing->treatment_id,
            'fee_amount' => '99.00',
            'currency' => 'AED',
        ]);
    }

    public function test_duplicate_creates_inactive_copy(): void
    {
        $existing = DoctorFixedFee::query()->where('is_active', true)->firstOrFail();

        $copy = $this->service->duplicate($existing);

        $this->assertNotSame($existing->id, $copy->id);
        $this->assertFalse($copy->is_active);
        $this->assertSame($existing->doctor_id, $copy->doctor_id);
        $this->assertSame($existing->treatment_id, $copy->treatment_id);
    }

    public function test_activate_checks_overlap(): void
    {
        $existing = DoctorFixedFee::query()->where('is_active', true)->firstOrFail();
        $copy = $this->service->duplicate($existing);

        $this->expectException(ValidationException::class);

        $this->service->activate($copy);
    }

    public function test_deactivate_preserves_row(): void
    {
        $existing = DoctorFixedFee::query()->where('is_active', true)->firstOrFail();

        $deactivated = $this->service->deactivate($existing);

        $this->assertFalse($deactivated->is_active);
        $this->assertDatabaseHas('doctor_fixed_fees', ['id' => $existing->id, 'is_active' => false]);
    }

    private function createTreatment(string $code, string $name): Treatment
    {
        $treatment = Treatment::query()->create([
            'code' => $code,
            'name' => $name,
        ]);
        $treatment->has_lab_cost = false;
        $treatment->is_active = true;
        $treatment->save();

        return $treatment->fresh();
    }
}
