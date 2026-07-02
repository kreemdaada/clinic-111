<?php

namespace Tests\Unit;

use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Services\Accounting\LabPriceManagementService;
use App\Services\Accounting\LabPriceResolver;
use App\Support\LabPriceOverlapValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LabPriceAdministrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private LabPriceManagementService $service;

    private LabPriceResolver $resolver;

    private LabPriceOverlapValidator $overlapValidator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->authenticateAdmin();

        $this->service = app(LabPriceManagementService::class);
        $this->resolver = app(LabPriceResolver::class);
        $this->overlapValidator = app(LabPriceOverlapValidator::class);
    }

    public function test_lab_price_administration_workflow_without_validity_periods(): void
    {
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $riyadhLab = Lab::query()->where('code', 'RIYADH_LAB')->firstOrFail();
        $drRiyad = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $drJack = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $zir = Treatment::query()->where('code', 'ZIR')->firstOrFail();

        $this->deactivateExistingZirPrices($zir);

        $general = $this->service->create([
            'lab_id' => $mainLab->id,
            'treatment_id' => $zir->id,
            'doctor_id' => null,
            'unit_cost' => '360.00',
            'currency' => 'AED',
            'is_active' => true,
        ]);

        $override = $this->service->create([
            'lab_id' => $riyadhLab->id,
            'treatment_id' => $zir->id,
            'doctor_id' => $drRiyad->id,
            'unit_cost' => '400.00',
            'currency' => 'AED',
            'is_active' => true,
        ]);

        $riyadResolved = $this->resolver->resolve($drRiyad, $zir, $riyadhLab);
        $jackResolved = $this->resolver->resolve($drJack, $zir, $mainLab);

        $this->assertSame($override->id, $riyadResolved?->id);
        $this->assertSame($general->id, $jackResolved?->id);

        $duplicate = $this->service->duplicate($general);
        $this->assertFalse($duplicate->is_active);

        $duplicate = $this->service->update($duplicate, [
            'unit_cost' => '380.00',
        ]);

        try {
            $this->service->activate($duplicate);
            $this->fail('Expected ValidationException when activating overlapping duplicate.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lab_id', $exception->errors());
        }

        $this->assertTrue($this->overlapValidator->hasActiveOverlap(
            $duplicate->clinic_id,
            $duplicate->lab_id,
            $duplicate->treatment_id,
            $duplicate->doctor_id,
            $duplicate->currency,
            $duplicate->id,
        ));

        $this->service->deactivate($general);

        $duplicate = $this->service->activate($duplicate);
        $this->assertTrue($duplicate->is_active);

        $resolved = $this->resolver->resolve($drJack, $zir, $mainLab);
        $this->assertSame($duplicate->id, $resolved?->id);
        $this->assertSame('380.00', $this->formatMoney($resolved->unit_cost));

        try {
            $this->service->create([
                'lab_id' => $mainLab->id,
                'treatment_id' => $zir->id,
                'doctor_id' => null,
                'unit_cost' => '390.00',
                'currency' => 'AED',
                'is_active' => true,
            ]);
            $this->fail('Expected ValidationException when creating overlapping active price.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lab_id', $exception->errors());
        }
    }

    private function deactivateExistingZirPrices(Treatment $zir): void
    {
        LabPrice::query()
            ->where('treatment_id', $zir->id)
            ->update(['is_active' => false]);
    }

    private function formatMoney(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
