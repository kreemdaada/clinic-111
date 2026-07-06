<?php

namespace Tests\Unit;

use App\Models\Treatment;
use App\Models\WorkItem;
use App\Services\Accounting\OpgTreatmentValueAggregator;
use App\Services\Accounting\WorkItemTreatmentSnapshotService;
use App\Support\OpgTreatmentCodes;
use Tests\TestCase;

class OpgTreatmentValueAggregatorTest extends TestCase
{
    private OpgTreatmentValueAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aggregator = new OpgTreatmentValueAggregator(new WorkItemTreatmentSnapshotService);
    }

    public function test_opg_normal_quantity_three_equals_six_hundred(): void
    {
        $workItems = collect([
            $this->workItem('OPG_NORMAL', '200.00', 'AED', 3),
        ]);

        $total = $this->aggregator->sumForWorkItems($workItems, OpgTreatmentCodes::NORMAL);

        $this->assertSame('600.00', $total);
    }

    public function test_opg_3d_quantity_one_equals_three_sixty(): void
    {
        $workItems = collect([
            $this->workItem('OPG_3D', '360.00', 'AED', 1),
        ]);

        $total = $this->aggregator->sumForWorkItems($workItems, OpgTreatmentCodes::THREE_D);

        $this->assertSame('360.00', $total);
    }

    public function test_multiple_work_items_are_summed_for_matching_code(): void
    {
        $workItems = collect([
            $this->workItem('OPG_NORMAL', '200.00', 'AED', 1),
            $this->workItem('OPG-NORMAL', '200.00', 'AED', 2),
        ]);

        $total = $this->aggregator->sumForWorkItems($workItems, OpgTreatmentCodes::NORMAL);

        $this->assertSame('600.00', $total);
    }

    public function test_non_opg_treatments_are_excluded(): void
    {
        $workItems = collect([
            $this->workItem('OPG_NORMAL', '200.00', 'AED', 3),
            $this->workItem('IMPL', '5000.00', 'AED', 1),
            $this->workItem('OPG_3D', '360.00', 'AED', 1),
        ]);

        $normalTotal = $this->aggregator->sumForWorkItems($workItems, OpgTreatmentCodes::NORMAL);
        $threeDTotal = $this->aggregator->sumForWorkItems($workItems, OpgTreatmentCodes::THREE_D);

        $this->assertSame('600.00', $normalTotal);
        $this->assertSame('360.00', $threeDTotal);
    }

    public function test_line_value_aed_uses_snapshot_price_and_quantity(): void
    {
        $workItem = $this->workItem('OPG_NORMAL', '200.00', 'AED', 3);

        $this->assertSame('600.00', $this->aggregator->lineValueAedFromWorkItem($workItem));
    }

    public function test_work_item_without_nurse_still_counts_toward_opg_value(): void
    {
        $workItem = $this->workItem('OPG_NORMAL', '200.00', 'AED', 1);
        $workItem->nurse_id = null;

        $this->assertSame('200.00', $this->aggregator->lineValueAedFromWorkItem($workItem));
    }

    public function test_historical_snapshot_ignores_later_treatment_price_changes(): void
    {
        $workItem = $this->workItem('OPG_NORMAL', '200.00', 'AED', 1);
        $workItem->treatment->treatment_price = '250.00';

        $this->assertSame('200.00', $this->aggregator->lineValueAedFromWorkItem($workItem));
    }

    private function workItem(string $code, string $price, string $currency, int $quantity): WorkItem
    {
        $treatment = new Treatment([
            'code' => $code,
            'treatment_price' => $price,
            'treatment_price_currency' => $currency,
            'requires_nurse_commission' => true,
        ]);

        $snapshotService = new WorkItemTreatmentSnapshotService;
        $snapshot = $snapshotService->buildAttributesFromTreatment($treatment);

        $workItem = new WorkItem(array_merge(['quantity' => $quantity], $snapshot));
        $workItem->setRelation('treatment', $treatment);

        return $workItem;
    }
}
