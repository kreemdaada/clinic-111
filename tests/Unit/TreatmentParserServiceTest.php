<?php

namespace Tests\Unit;

use App\Services\Accounting\TreatmentParserService;
use Tests\TestCase;

class TreatmentParserServiceTest extends TestCase
{
    private TreatmentParserService $treatmentParserService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->treatmentParserService = app(TreatmentParserService::class);
    }

    public function test_parses_zir_x_notation_with_plus(): void
    {
        $parsedItems = $this->treatmentParserService->parse('ZIR x 2 + POST x 1');

        $itemsByCode = collect($parsedItems)->keyBy(fn ($item) => $item->treatmentCode);

        $this->assertSame(2, $itemsByCode->get('ZIR')->quantity);
        $this->assertSame(1, $itemsByCode->get('POST')->quantity);
        $this->assertSame(100, $itemsByCode->get('ZIR')->confidence);
        $this->assertSame(100, $itemsByCode->get('POST')->confidence);
    }

    public function test_imp_cr_alias_maps_to_impl_cr(): void
    {
        $parsedItems = $this->treatmentParserService->parse('IMP-CR x 3');

        $itemsByCode = collect($parsedItems)->keyBy(fn ($item) => $item->treatmentCode);

        $this->assertTrue($itemsByCode->has('IMPL-CR'));
        $this->assertSame(3, $itemsByCode->get('IMPL-CR')->quantity);
    }

    public function test_parses_zir_and_post_with_quantities(): void
    {
        $parsedItems = $this->treatmentParserService->parse('ZIR 4 + POST 2');

        $this->assertCount(2, $parsedItems);

        $itemsByCode = collect($parsedItems)->keyBy(fn ($item) => $item->treatmentCode);

        $this->assertTrue($itemsByCode->has('ZIR'));
        $this->assertTrue($itemsByCode->has('POST'));
        $this->assertSame(4, $itemsByCode->get('ZIR')->quantity);
        $this->assertSame(2, $itemsByCode->get('POST')->quantity);
    }

    public function test_impl_pipe_notation_counts_teeth_not_implant_quantity(): void
    {
        $parsedItems = $this->treatmentParserService->parse('Impl |67');

        $itemsByCode = collect($parsedItems)->keyBy(fn ($item) => $item->treatmentCode);

        $this->assertTrue($itemsByCode->has('IMPL'));
        $this->assertSame(2, $itemsByCode->get('IMPL')->quantity);
    }

    public function test_parses_per_patient_segments_from_joined_text(): void
    {
        $parsedItems = $this->treatmentParserService->parse('Exo |2 | Impl |67');

        $itemsByCode = collect($parsedItems)->keyBy(fn ($item) => $item->treatmentCode);

        $this->assertCount(2, $parsedItems);
        $this->assertTrue($itemsByCode->has('EXO'));
        $this->assertTrue($itemsByCode->has('IMPL'));
        $this->assertSame(2, $itemsByCode->get('IMPL')->quantity);
    }

    public function test_zir_cr_tooth_list_with_explicit_quantity(): void
    {
        $parsedItems = $this->treatmentParserService->parse('Zir cr 546|5');

        $itemsByCode = collect($parsedItems)->keyBy(fn ($item) => $item->treatmentCode);

        $this->assertTrue($itemsByCode->has('ZIR'));
        $this->assertSame(5, $itemsByCode->get('ZIR')->quantity);
    }

    public function test_zir_cr_with_file_number_suffix_ignores_file_number(): void
    {
        $parsedItems = $this->treatmentParserService->parse('ZIR CR 546|6517');

        $itemsByCode = collect($parsedItems)->keyBy(fn ($item) => $item->treatmentCode);

        $this->assertTrue($itemsByCode->has('ZIR'));
        $this->assertSame(3, $itemsByCode->get('ZIR')->quantity);
    }

    public function test_parses_hyphenated_crown_codes_with_quantities(): void
    {
        $parsedItems = $this->treatmentParserService->parse('ZIR-CR x 3 + IMPL-ZIR x 2');

        $itemsByCode = collect($parsedItems)->keyBy(fn ($item) => $item->treatmentCode);

        $this->assertSame(3, $itemsByCode->get('ZIR')->quantity);
        $this->assertSame(2, $itemsByCode->get('IMPL-ZIR')->quantity);
    }

    public function test_parses_mc_slash_cr_notation(): void
    {
        $parsedItems = $this->treatmentParserService->parse('M/C-CR x 1');

        $itemsByCode = collect($parsedItems)->keyBy(fn ($item) => $item->treatmentCode);

        $this->assertTrue($itemsByCode->has('MC'));
        $this->assertSame(1, $itemsByCode->get('MC')->quantity);
    }

    public function test_abb_parentheses_quantity(): void
    {
        $parsedItems = $this->treatmentParserService->parse('Abb (2)');

        $itemsByCode = collect($parsedItems)->keyBy(fn ($item) => $item->treatmentCode);

        $this->assertTrue($itemsByCode->has('ABT'));
        $this->assertSame(2, $itemsByCode->get('ABT')->quantity);
    }

    public function test_mc_cr_dual_quadrant_tooth_notation_counts_all_teeth(): void
    {
        $parsedItems = $this->treatmentParserService->parse('MC cr  8765|5678');

        $itemsByCode = collect($parsedItems)->keyBy(fn ($item) => $item->treatmentCode);

        $this->assertTrue($itemsByCode->has('MC'));
        $this->assertSame(8, $itemsByCode->get('MC')->quantity);
    }

    public function test_mc_pipe_single_tooth_is_quantity_one(): void
    {
        $parsedItems = $this->treatmentParserService->parse('MC cr |7 from advance 500 dhs');

        $itemsByCode = collect($parsedItems)->keyBy(fn ($item) => $item->treatmentCode);

        $this->assertTrue($itemsByCode->has('MC'));
        $this->assertSame(1, $itemsByCode->get('MC')->quantity);
    }

    public function test_parses_clinic_filling_notation(): void
    {
        $cases = [
            'CF 45' => ['CF' => 2],
            'CFx2' => ['CF' => 2],
            'CF 2|5 |4' => ['CF' => 3],
            'CF x3' => ['CF' => 3],
            'RCF 321|12' => ['RCF' => 5],
            'RCF x 5' => ['RCF' => 5],
            'SxP + CF 876' => ['SXP' => 1, 'CF' => 3],
            'SxP x1 + CF x3' => ['SXP' => 1, 'CF' => 3],
            'RCF |6+ CF |7' => ['RCF' => 1, 'CF' => 1],
            'SxP | SxP' => ['SXP' => 2],
        ];

        foreach ($cases as $text => $expectedQuantities) {
            $itemsByCode = collect($this->treatmentParserService->parse($text))
                ->keyBy(fn ($item) => $item->treatmentCode);

            foreach ($expectedQuantities as $code => $quantity) {
                $this->assertTrue($itemsByCode->has($code), "Missing {$code} for: {$text}");
                $this->assertSame($quantity, $itemsByCode->get($code)->quantity, "Wrong {$code} qty for: {$text}");
            }
        }
    }
}
