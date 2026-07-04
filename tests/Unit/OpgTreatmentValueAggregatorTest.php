<?php

namespace Tests\Unit;

use App\Services\Accounting\OpgTreatmentValueAggregator;
use App\Support\OpgTreatmentCodes;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class OpgTreatmentValueAggregatorTest extends TestCase
{
    private OpgTreatmentValueAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aggregator = new OpgTreatmentValueAggregator;
    }

    public function test_opg_normal_quantity_three_equals_six_hundred(): void
    {
        $commissions = new Collection([
            $this->commission('OPG_NORMAL', '200.00', 3),
        ]);

        $total = $this->aggregator->sumForCanonicalCode($commissions, OpgTreatmentCodes::NORMAL);

        $this->assertSame('600.00', $total);
    }

    public function test_opg_3d_quantity_one_equals_three_sixty(): void
    {
        $commissions = new Collection([
            $this->commission('OPG_3D', '360.00', 1),
        ]);

        $total = $this->aggregator->sumForCanonicalCode($commissions, OpgTreatmentCodes::THREE_D);

        $this->assertSame('360.00', $total);
    }

    public function test_multiple_commissions_are_summed_for_matching_code(): void
    {
        $commissions = new Collection([
            $this->commission('OPG_NORMAL', '200.00', 1),
            $this->commission('OPG-NORMAL', '200.00', 2),
        ]);

        $total = $this->aggregator->sumForCanonicalCode($commissions, OpgTreatmentCodes::NORMAL);

        $this->assertSame('600.00', $total);
    }

    public function test_non_opg_treatments_are_excluded(): void
    {
        $commissions = new Collection([
            $this->commission('OPG_NORMAL', '200.00', 3),
            $this->commission('IMPL', '5000.00', 1),
            $this->commission('OPG_3D', '360.00', 1),
        ]);

        $normalTotal = $this->aggregator->sumForCanonicalCode($commissions, OpgTreatmentCodes::NORMAL);
        $threeDTotal = $this->aggregator->sumForCanonicalCode($commissions, OpgTreatmentCodes::THREE_D);

        $this->assertSame('600.00', $normalTotal);
        $this->assertSame('360.00', $threeDTotal);
    }

    public function test_line_value_aed_uses_snapshot_price_and_quantity(): void
    {
        $commission = $this->commission('OPG_NORMAL', '200.00', 3);

        $this->assertSame('600.00', $this->aggregator->lineValueAed($commission));
    }

    /**
     * @return object{treatment_code_snapshot: string, treatment_price_aed: string, quantity: int}
     */
    private function commission(string $code, string $priceAed, int $quantity): object
    {
        return (object) [
            'treatment_code_snapshot' => $code,
            'treatment_price_aed' => $priceAed,
            'quantity' => $quantity,
        ];
    }
}
