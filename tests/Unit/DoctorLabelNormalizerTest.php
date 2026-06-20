<?php

namespace Tests\Unit;

use App\Support\DoctorLabelNormalizer;
use PHPUnit\Framework\TestCase;

class DoctorLabelNormalizerTest extends TestCase
{
    public function test_poria_maps_to_puriya(): void
    {
        $this->assertSame('PURIYA', DoctorLabelNormalizer::extractCodeGuess('Dr Poria'));
    }

    public function test_pouria_variants_map_to_puriya(): void
    {
        $this->assertSame('PURIYA', DoctorLabelNormalizer::extractCodeGuess('DR Pouria'));
        $this->assertSame('PURIYA', DoctorLabelNormalizer::extractCodeGuess('Dr. Pouria'));
        $this->assertSame('PURIYA', DoctorLabelNormalizer::extractCodeGuess('Dr Pouria'));
    }

    public function test_riyadh_maps_to_riyad(): void
    {
        $this->assertSame('RIYAD', DoctorLabelNormalizer::extractCodeGuess('Dr. Riyadh'));
    }

    public function test_wael_maps_to_wa(): void
    {
        $this->assertSame('WA', DoctorLabelNormalizer::extractCodeGuess('dr Wael'));
    }
}
