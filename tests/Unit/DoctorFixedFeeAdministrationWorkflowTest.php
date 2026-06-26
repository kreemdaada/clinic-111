<?php

namespace Tests\Unit;

use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Treatment;
use App\Services\Accounting\DoctorFixedFeeManagementService;
use App\Services\Accounting\DoctorFixedFeeResolver;
use App\Support\DoctorFixedFeeOverlapValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DoctorFixedFeeAdministrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private DoctorFixedFeeManagementService $service;

    private DoctorFixedFeeResolver $resolver;

    private DoctorFixedFeeOverlapValidator $overlapValidator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->authenticateAdmin();

        $this->service = app(DoctorFixedFeeManagementService::class);
        $this->resolver = app(DoctorFixedFeeResolver::class);
        $this->overlapValidator = app(DoctorFixedFeeOverlapValidator::class);
    }

    public function test_doctor_fixed_fee_administration_workflow(): void
    {
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'IMPL')->firstOrFail();

        $this->deactivateExistingImplFees($doctor, $treatment);

        // 1. Create active fee.
        $current = $this->service->create([
            'doctor_id' => $doctor->id,
            'treatment_id' => $treatment->id,
            'fee_amount' => '500.00',
            'currency' => 'AED',
            'is_active' => true,
        ]);

        $this->assertTrue($current->is_active);
        $this->assertSame('500.00', $this->formatMoney($current->fee_amount));

        // 2. Resolver returns active fee for today.
        $resolved = $this->resolver->resolve($doctor, $treatment);
        $this->assertNotNull($resolved);
        $this->assertSame($current->id, $resolved->id);

        // 3. Duplicate creates inactive copy.
        $duplicate = $this->service->duplicate($current);
        $this->assertFalse($duplicate->is_active);

        // 4. Adjust duplicate for future validity and new amount.
        $duplicate = $this->service->update($duplicate, [
            'valid_from' => '2030-01-01',
            'valid_to' => '2030-12-31',
            'fee_amount' => '550.00',
        ]);

        $this->assertFalse($duplicate->is_active);
        $this->assertSame('550.00', $this->formatMoney($duplicate->fee_amount));

        // 5. Overlap validator blocks activating duplicate while current is active.
        $this->assertTrue($this->overlapValidator->hasActiveOverlap(
            $doctor->id,
            $treatment->id,
            '2030-01-01',
            '2030-12-31',
            $duplicate->id,
        ));

        try {
            $this->service->activate($duplicate);
            $this->fail('Expected ValidationException when activating overlapping fee.');
        } catch (ValidationException) {
            // expected
        }

        // 6. Deactivate current, activate future fee, resolver follows date.
        $this->service->deactivate($current);
        $activated = $this->service->activate($duplicate);
        $this->assertTrue($activated->is_active);

        Carbon::setTestNow('2030-06-15');
        $futureResolved = $this->resolver->resolve($doctor, $treatment);
        $this->assertNotNull($futureResolved);
        $this->assertSame($duplicate->id, $futureResolved->id);
        Carbon::setTestNow();
    }

    private function deactivateExistingImplFees(Doctor $doctor, Treatment $treatment): void
    {
        DoctorFixedFee::query()
            ->where('doctor_id', $doctor->id)
            ->where('treatment_id', $treatment->id)
            ->update(['is_active' => false]);
    }

    private function formatMoney(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
