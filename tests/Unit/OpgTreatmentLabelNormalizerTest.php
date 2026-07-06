<?php

namespace Tests\Unit;

use App\Support\OpgTreatmentCodes;
use App\Support\OpgTreatmentLabelNormalizer;
use Tests\TestCase;

class OpgTreatmentLabelNormalizerTest extends TestCase
{
    public function test_recognizes_plain_opg_as_opg_normal(): void
    {
        $parsed = OpgTreatmentLabelNormalizer::parse('OPG');

        $this->assertNotNull($parsed);
        $this->assertSame(OpgTreatmentCodes::NORMAL, $parsed['code']);
        $this->assertSame(1, $parsed['quantity']);
    }

    public function test_recognizes_opg_with_suffix_as_opg_normal(): void
    {
        $parsed = OpgTreatmentLabelNormalizer::parse('OPG trast');

        $this->assertNotNull($parsed);
        $this->assertSame(OpgTreatmentCodes::NORMAL, $parsed['code']);
        $this->assertNull($parsed['nurse_alias']);
    }

    public function test_recognizes_opg_3d_before_generic_opg(): void
    {
        $parsed = OpgTreatmentLabelNormalizer::parse('OPG 3D');

        $this->assertNotNull($parsed);
        $this->assertSame(OpgTreatmentCodes::THREE_D, $parsed['code']);
    }

    public function test_extracts_nurse_alias_from_treatment_text(): void
    {
        $parsed = OpgTreatmentLabelNormalizer::parse('OPG - Jiji');

        $this->assertNotNull($parsed);
        $this->assertSame('Jiji', $parsed['nurse_alias']);
    }

    public function test_reads_explicit_quantity(): void
    {
        $parsed = OpgTreatmentLabelNormalizer::parse('OPG x 2');

        $this->assertNotNull($parsed);
        $this->assertSame(2, $parsed['quantity']);
    }
}
