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
}
