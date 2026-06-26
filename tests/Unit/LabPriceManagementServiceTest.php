<?php

namespace Tests\Unit;

use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Services\Accounting\LabPriceManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LabPriceManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private LabPriceManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->service = app(LabPriceManagementService::class);
    }

    public function test_create_general_price(): void
    {
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $treatment = $this->createLabCostTreatment('SVC_LP', 'Service LP');

        $price = $this->service->create([
            'lab_id' => $mainLab->id,
            'treatment_id' => $treatment->id,
            'unit_cost' => '199.99',
            'currency' => 'AED',
        ]);

        $this->assertTrue($price->is_active);
        $this->assertNull($price->doctor_id);
        $this->assertSame('199.99', (string) $price->unit_cost);
    }

    public function test_duplicate_active_price_throws_validation_exception(): void
    {
        $existing = LabPrice::query()->where('is_active', true)->whereNull('doctor_id')->firstOrFail();

        $this->expectException(ValidationException::class);

        $this->service->create([
            'lab_id' => $existing->lab_id,
            'treatment_id' => $existing->treatment_id,
            'unit_cost' => '50.00',
            'currency' => 'AED',
        ]);
    }

    public function test_duplicate_creates_inactive_copy(): void
    {
        $existing = LabPrice::query()->where('is_active', true)->firstOrFail();

        $copy = $this->service->duplicate($existing);

        $this->assertNotSame($existing->id, $copy->id);
        $this->assertFalse($copy->is_active);
        $this->assertSame($existing->lab_id, $copy->lab_id);
        $this->assertSame($existing->treatment_id, $copy->treatment_id);
    }

    public function test_activate_checks_overlap(): void
    {
        $existing = LabPrice::query()->where('is_active', true)->whereNull('doctor_id')->firstOrFail();
        $copy = $this->service->duplicate($existing);

        $this->expectException(ValidationException::class);

        $this->service->activate($copy);
    }

    public function test_doctor_override_is_stored_separately_from_general_price(): void
    {
        $doctor = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $riyadhLab = Lab::query()->where('code', 'RIYADH_LAB')->firstOrFail();
        $treatment = $this->createLabCostTreatment('SVC_OVR', 'Service Override');

        $price = $this->service->create([
            'lab_id' => $riyadhLab->id,
            'treatment_id' => $treatment->id,
            'doctor_id' => $doctor->id,
            'unit_cost' => '425.00',
            'currency' => 'AED',
        ]);

        $this->assertSame($doctor->id, $price->doctor_id);
        $this->assertDatabaseHas('lab_prices', [
            'id' => $price->id,
            'doctor_id' => $doctor->id,
            'is_active' => true,
        ]);
    }

    private function createLabCostTreatment(string $code, string $name): Treatment
    {
        $treatment = Treatment::query()->create($this->withClinicId([
            'code' => $code,
            'name' => $name,
        ]));
        $treatment->has_lab_cost = true;
        $treatment->is_active = true;
        $treatment->save();

        return $treatment->fresh();
    }
}
