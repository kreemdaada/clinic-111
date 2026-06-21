<?php

namespace Tests\Unit;

use App\Models\Treatment;
use App\Support\LabCostTreatmentCatalog;
use Tests\TestCase;

class LabCostTreatmentCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_cf_and_sxp_do_not_have_lab_cost(): void
    {
        foreach (['CF', 'SXP', 'RCT', 'AF', 'RE-RCT', 'REPAIR', 'BLEACHING', 'EXO', 'APICO'] as $code) {
            $treatment = Treatment::query()->where('code', $code)->firstOrFail();

            $this->assertFalse($treatment->has_lab_cost, "{$code} must not generate JOB");
            $this->assertFalse(LabCostTreatmentCatalog::isLabCostCode($code));
        }
    }

    public function test_lab_cost_treatments_are_flagged_in_database(): void
    {
        foreach (LabCostTreatmentCatalog::codes() as $code) {
            $treatment = Treatment::query()->where('code', $code)->firstOrFail();

            $this->assertTrue($treatment->has_lab_cost, "{$code} must generate JOB");
            $this->assertTrue(LabCostTreatmentCatalog::isLabCostCode($code));
        }
    }
}
