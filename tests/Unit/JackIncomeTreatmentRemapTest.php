<?php

namespace Tests\Unit;

use App\Services\Export\DoctorsIncomeExcelExportService;
use ReflectionMethod;
use Tests\TestCase;

class JackIncomeTreatmentRemapTest extends TestCase
{
    public function test_impl_zir_merges_into_zir_when_both_present(): void
    {
        $remapped = $this->remap([
            'ZIR' => 3,
            'IMPL-ZIR' => 2,
            'ABT' => 2,
        ]);

        $this->assertSame(5, $remapped['ZIR']);
        $this->assertArrayNotHasKey('IMPL-ZIR', $remapped);
        $this->assertSame(2, $remapped['ABT']);
    }

    public function test_impl_zir_moves_to_impl_cr_when_mc_present(): void
    {
        $remapped = $this->remap([
            'MC' => 6,
            'IMPL-ZIR' => 2,
            'ABT' => 2,
        ]);

        $this->assertSame(6, $remapped['MC']);
        $this->assertSame(2, $remapped['IMPL-CR']);
        $this->assertArrayNotHasKey('IMPL-ZIR', $remapped);
    }

    public function test_impl_zir_stays_when_only_impl_zir_present(): void
    {
        $remapped = $this->remap([
            'IMPL-ZIR' => 1,
            'ABT' => 1,
        ]);

        $this->assertSame(1, $remapped['IMPL-ZIR']);
        $this->assertSame(1, $remapped['ABT']);
    }

    /**
     * @param  array<string, int>  $treatments
     * @return array<string, int>
     */
    private function remap(array $treatments): array
    {
        $service = app(DoctorsIncomeExcelExportService::class);
        $method = new ReflectionMethod(DoctorsIncomeExcelExportService::class, 'remapJackIncomeTreatmentCounts');

        /** @var array<string, int> $result */
        $result = $method->invoke($service, $treatments);

        return $result;
    }
}
