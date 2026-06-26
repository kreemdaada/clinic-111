<?php

namespace Tests\Unit;

use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Treatment;
use App\Services\Accounting\DoctorFixedFeeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DoctorFixedFeeResolverTest extends TestCase
{
    use RefreshDatabase;

    private DoctorFixedFeeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->resolver = app(DoctorFixedFeeResolver::class);
    }

    public function test_resolves_seeded_wa_impl_fee(): void
    {
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'IMPL')->firstOrFail();

        $fee = $this->resolver->resolve($doctor, $treatment);

        $this->assertNotNull($fee);
        $this->assertSame('500.00', number_format((float) $fee->fee_amount, 2, '.', ''));
        $this->assertTrue($fee->is_active);
    }

    public function test_ignores_inactive_fee(): void
    {
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'BG')->firstOrFail();
        $fee = DoctorFixedFee::query()
            ->where('doctor_id', $doctor->id)
            ->where('treatment_id', $treatment->id)
            ->firstOrFail();

        $fee->is_active = false;
        $fee->save();

        $this->assertNull($this->resolver->resolve($doctor, $treatment));
    }

    public function test_respects_validity_window(): void
    {
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'SINUS')->firstOrFail();

        DoctorFixedFee::query()
            ->where('doctor_id', $doctor->id)
            ->where('treatment_id', $treatment->id)
            ->update(['is_active' => false]);

        $fee = DoctorFixedFee::query()->create($this->withClinicId([
            'doctor_id' => $doctor->id,
            'treatment_id' => $treatment->id,
            'fee_amount' => '250.00',
            'currency' => 'USD',
            'valid_from' => '2025-01-01',
            'valid_to' => '2025-12-31',
        ]));
        $fee->is_active = true;
        $fee->save();

        Carbon::setTestNow('2025-06-01');
        $this->assertNotNull($this->resolver->resolve($doctor, $treatment));

        Carbon::setTestNow('2026-01-01');
        $this->assertNull($this->resolver->resolve($doctor, $treatment));

        Carbon::setTestNow();
    }
}
