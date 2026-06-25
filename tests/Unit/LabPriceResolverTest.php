<?php

namespace Tests\Unit;

use App\Models\Doctor;
use App\Models\Lab;
use App\Models\Treatment;
use App\Services\Accounting\LabPriceResolver;
use Tests\TestCase;

class LabPriceResolverTest extends TestCase
{
    private LabPriceResolver $labPriceResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->labPriceResolver = app(LabPriceResolver::class);
    }

    public function test_zir_for_dr_riyad_uses_400_aed(): void
    {
        $doctorRiyad = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $treatmentZir = Treatment::query()->where('code', 'ZIR')->firstOrFail();
        $riyadhLab = Lab::query()->where('code', 'RIYADH_LAB')->firstOrFail();

        $labPrice = $this->labPriceResolver->resolve($doctorRiyad, $treatmentZir, $riyadhLab);

        $this->assertNotNull($labPrice);
        $this->assertSame('400.00', number_format((float) $labPrice->unit_cost, 2, '.', ''));
        $this->assertSame($doctorRiyad->id, $labPrice->doctor_id);
    }

    public function test_mc_for_all_doctors_uses_105_aed(): void
    {
        $doctorJack = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatmentMc = Treatment::query()->where('code', 'MC')->firstOrFail();
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();

        $labPrice = $this->labPriceResolver->resolve($doctorJack, $treatmentMc, $mainLab);

        $this->assertNotNull($labPrice);
        $this->assertSame('105.00', number_format((float) $labPrice->unit_cost, 2, '.', ''));
        $this->assertNull($labPrice->doctor_id);
    }

    public function test_zir_for_other_doctors_uses_360_aed(): void
    {
        $doctorJack = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatmentZir = Treatment::query()->where('code', 'ZIR')->firstOrFail();
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();

        $labPrice = $this->labPriceResolver->resolve($doctorJack, $treatmentZir, $mainLab);

        $this->assertNotNull($labPrice);
        $this->assertSame('360.00', number_format((float) $labPrice->unit_cost, 2, '.', ''));
        $this->assertNull($labPrice->doctor_id);
    }

    public function test_impl_zir_for_dr_riyad_uses_500_aed(): void
    {
        $doctorRiyad = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'IMPL-ZIR')->firstOrFail();
        $riyadhLab = Lab::query()->where('code', 'RIYADH_LAB')->firstOrFail();

        $labPrice = $this->labPriceResolver->resolve($doctorRiyad, $treatment, $riyadhLab);

        $this->assertNotNull($labPrice);
        $this->assertSame('500.00', number_format((float) $labPrice->unit_cost, 2, '.', ''));
        $this->assertSame($doctorRiyad->id, $labPrice->doctor_id);
    }

    public function test_impl_zir_for_other_doctors_uses_460_aed(): void
    {
        $doctorJack = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'IMPL-ZIR')->firstOrFail();
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();

        $labPrice = $this->labPriceResolver->resolve($doctorJack, $treatment, $mainLab);

        $this->assertNotNull($labPrice);
        $this->assertSame('460.00', number_format((float) $labPrice->unit_cost, 2, '.', ''));
        $this->assertNull($labPrice->doctor_id);
    }

    public function test_remov_lab_cost_is_100_aed(): void
    {
        $doctorPuriya = Doctor::query()->where('code', 'PURIYA')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'REMOV')->firstOrFail();
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();

        $labPrice = $this->labPriceResolver->resolve($doctorPuriya, $treatment, $mainLab);

        $this->assertNotNull($labPrice);
        $this->assertSame('100.00', number_format((float) $labPrice->unit_cost, 2, '.', ''));
    }

    public function test_post_for_dr_riyad_uses_riyadh_lab_override(): void
    {
        $doctorRiyad = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'POST')->firstOrFail();
        $riyadhLab = Lab::query()->where('code', 'RIYADH_LAB')->firstOrFail();

        $labPrice = $this->labPriceResolver->resolve($doctorRiyad, $treatment, $riyadhLab);

        $this->assertNotNull($labPrice);
        $this->assertSame('55.00', number_format((float) $labPrice->unit_cost, 2, '.', ''));
        $this->assertSame($doctorRiyad->id, $labPrice->doctor_id);
        $this->assertSame($riyadhLab->id, $labPrice->lab_id);
    }

    public function test_post_for_dr_riyad_resolve_with_fallback_uses_riyadh_lab(): void
    {
        $doctorRiyad = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'POST')->firstOrFail();
        $activeLabs = Lab::query()->where('is_active', true)->get();

        $resolved = $this->labPriceResolver->resolveWithLabFallback($doctorRiyad, $treatment, $activeLabs);

        $this->assertNotNull($resolved);
        $this->assertSame('RIYADH_LAB', $resolved['lab']->code);
        $this->assertSame('55.00', number_format((float) $resolved['price']->unit_cost, 2, '.', ''));
    }
}
