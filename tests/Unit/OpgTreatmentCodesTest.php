<?php

namespace Tests\Unit;

use App\Support\OpgTreatmentCodes;
use PHPUnit\Framework\TestCase;

class OpgTreatmentCodesTest extends TestCase
{
    public function test_normal_variants_match(): void
    {
        $this->assertTrue(OpgTreatmentCodes::isNormal('OPG_NORMAL'));
        $this->assertTrue(OpgTreatmentCodes::isNormal('OPG-NORMAL'));
        $this->assertTrue(OpgTreatmentCodes::isNormal('opg-normal'));
        $this->assertFalse(OpgTreatmentCodes::isNormal('OPG_3D'));
    }

    public function test_3d_variants_match(): void
    {
        $this->assertTrue(OpgTreatmentCodes::is3d('OPG_3D'));
        $this->assertTrue(OpgTreatmentCodes::is3d('OPG-3D'));
        $this->assertTrue(OpgTreatmentCodes::is3d('OPG 3D'));
        $this->assertFalse(OpgTreatmentCodes::is3d('OPG-NORMAL'));
    }
}
