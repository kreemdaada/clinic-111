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
use Illuminate\Support\Carbon;
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

        $this->service = app(LabPriceManagementService::class);
        $this->resolver = app(LabPriceResolver::class);
        $this->overlapValidator = app(LabPriceOverlapValidator::class);
    }

    public function test_lab_price_administration_workflow(): void
    {
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $riyadhLab = Lab::query()->where('code', 'RIYADH_LAB')->firstOrFail();
        $drRiyad = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $drJack = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $zir = Treatment::query()->where('code', 'ZIR')->firstOrFail();

        $this->deactivateExistingZirPrices($zir);

        // 1. Create general lab price (MAIN_LAB + ZIR, 360 AED).
        $general = $this->service->create([
            'lab_id' => $mainLab->id,
            'treatment_id' => $zir->id,
            'doctor_id' => null,
            'unit_cost' => '360.00',
            'currency' => 'AED',
            'is_active' => true,
        ]);

        $this->assertTrue($general->is_active);
        $this->assertNull($general->doctor_id);
        $this->assertSame('360.00', $this->formatMoney($general->unit_cost));

        // 2. Create doctor-specific override (RIYADH_LAB + ZIR + Dr Riyad, 400 AED).
        $override = $this->service->create([
            'lab_id' => $riyadhLab->id,
            'treatment_id' => $zir->id,
            'doctor_id' => $drRiyad->id,
            'unit_cost' => '400.00',
            'currency' => 'AED',
            'is_active' => true,
        ]);

        $this->assertTrue($override->is_active);
        $this->assertSame($drRiyad->id, $override->doctor_id);
        $this->assertSame('400.00', $this->formatMoney($override->unit_cost));

        // 3. LabPriceResolver returns doctor override vs general fallback.
        $riyadResolved = $this->resolver->resolve($drRiyad, $zir, $riyadhLab);
        $jackResolved = $this->resolver->resolve($drJack, $zir, $mainLab);

        $this->assertNotNull($riyadResolved);
        $this->assertSame('400.00', $this->formatMoney($riyadResolved->unit_cost));
        $this->assertSame($override->id, $riyadResolved->id);

        $this->assertNotNull($jackResolved);
        $this->assertSame('360.00', $this->formatMoney($jackResolved->unit_cost));
        $this->assertSame($general->id, $jackResolved->id);

        // 4. Duplicate the general price — inactive copy with identical configuration.
        $duplicate = $this->service->duplicate($general);

        $this->assertFalse($duplicate->is_active);
        $this->assertSame($general->lab_id, $duplicate->lab_id);
        $this->assertSame($general->treatment_id, $duplicate->treatment_id);
        $this->assertSame($general->doctor_id, $duplicate->doctor_id);
        $this->assertSame($general->currency, $duplicate->currency);
        $this->assertSame('360.00', $this->formatMoney($duplicate->unit_cost));

        // 5. Adjust duplicate: future valid_from and new unit cost before activation.
        $duplicate = $this->service->update($duplicate, [
            'valid_from' => '2030-01-01',
            'unit_cost' => '380.00',
        ]);

        $this->assertSame('2030-01-01', $duplicate->valid_from?->toDateString());
        $this->assertSame('380.00', $this->formatMoney($duplicate->unit_cost));
        $this->assertFalse($duplicate->is_active);

        // 6. Activation must fail while original is still active with overlapping validity.
        try {
            $this->service->activate($duplicate);
            $this->fail('Expected ValidationException when activating overlapping duplicate.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lab_id', $exception->errors());
        }

        $this->assertTrue($this->overlapValidator->hasActiveOverlap(
            $duplicate->lab_id,
            $duplicate->treatment_id,
            $duplicate->doctor_id,
            $duplicate->valid_from?->toDateString(),
            $duplicate->valid_to?->toDateString(),
            $duplicate->id,
        ));

        // 7. Close the original price before the duplicate's valid_from.
        $general = $this->service->update($general, [
            'valid_to' => '2029-12-31',
        ]);

        $this->assertSame('2029-12-31', $general->valid_to?->toDateString());
        $this->assertTrue($general->is_active);

        $this->assertFalse($this->overlapValidator->hasActiveOverlap(
            $duplicate->lab_id,
            $duplicate->treatment_id,
            $duplicate->doctor_id,
            $duplicate->valid_from?->toDateString(),
            $duplicate->valid_to?->toDateString(),
            $duplicate->id,
        ));

        // 8. Activate duplicate — resolver uses new price inside its validity window.
        $duplicate = $this->service->activate($duplicate);

        $this->assertTrue($duplicate->is_active);

        $legacyPrice = $this->resolver->resolve(
            $drJack,
            $zir,
            $mainLab,
            Carbon::parse('2029-06-01'),
        );
        $futurePrice = $this->resolver->resolve(
            $drJack,
            $zir,
            $mainLab,
            Carbon::parse('2030-06-01'),
        );

        $this->assertNotNull($legacyPrice);
        $this->assertSame($general->id, $legacyPrice->id);
        $this->assertSame('360.00', $this->formatMoney($legacyPrice->unit_cost));

        $this->assertNotNull($futurePrice);
        $this->assertSame($duplicate->id, $futurePrice->id);
        $this->assertSame('380.00', $this->formatMoney($futurePrice->unit_cost));

        // 9. Another overlapping active price must be rejected.
        try {
            $this->service->create([
                'lab_id' => $mainLab->id,
                'treatment_id' => $zir->id,
                'doctor_id' => null,
                'unit_cost' => '390.00',
                'currency' => 'AED',
                'valid_from' => '2030-01-01',
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
