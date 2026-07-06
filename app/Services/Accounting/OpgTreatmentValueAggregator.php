<?php

namespace App\Services\Accounting;

use App\Models\Treatment;
use App\Models\WorkItem;
use App\Support\MoneyCalculator;
use App\Support\OpgTreatmentCodes;
use Illuminate\Support\Collection;

/**
 * Aggregates informative OPG treatment values from persisted work item snapshots.
 *
 * Value = treatment_price_aed snapshot × quantity in AED.
 * Independent of nurse assignment and nurse commission snapshots.
 */
final class OpgTreatmentValueAggregator
{
    public function __construct(
        private readonly WorkItemTreatmentSnapshotService $workItemTreatmentSnapshotService,
    ) {}

    /**
     * Sum treatment price × quantity for work items matching a canonical OPG code.
     *
     * @param  Collection<int, WorkItem>  $workItems
     */
    public function sumForWorkItems(Collection $workItems, string $canonicalTreatmentCode): string
    {
        $total = '0.00';

        foreach ($workItems as $workItem) {
            $code = $this->resolveTreatmentCode($workItem);

            if ($code === null || ! OpgTreatmentCodes::matches($code, $canonicalTreatmentCode)) {
                continue;
            }

            $lineValue = $this->lineValueAedFromWorkItem($workItem);

            if ($lineValue === null) {
                continue;
            }

            $total = MoneyCalculator::add($total, $lineValue);
        }

        return $total;
    }

    public function lineValueAedFromWorkItem(WorkItem $workItem): ?string
    {
        $priceAed = $workItem->treatment_price_aed;

        if ($priceAed !== null) {
            return MoneyCalculator::multiply((string) $priceAed, (int) $workItem->quantity);
        }

        $workItem->loadMissing('treatment');
        $treatment = $workItem->treatment;

        if ($treatment === null) {
            return null;
        }

        $legacyPriceAed = $this->legacyTreatmentPriceAed($treatment);

        if ($legacyPriceAed === null) {
            return null;
        }

        return MoneyCalculator::multiply($legacyPriceAed, (int) $workItem->quantity);
    }

    /**
     * Legacy fallback for rows without a persisted snapshot (best-effort backfill only).
     */
    public function legacyTreatmentPriceAed(Treatment $treatment): ?string
    {
        if ($treatment->treatment_price === null || $treatment->treatment_price_currency === null) {
            return null;
        }

        $attributes = $this->workItemTreatmentSnapshotService->buildAttributesFromTreatment($treatment);

        return $attributes['treatment_price_aed'];
    }

    private function resolveTreatmentCode(WorkItem $workItem): ?string
    {
        if ($workItem->treatment_code_snapshot !== null && $workItem->treatment_code_snapshot !== '') {
            return $workItem->treatment_code_snapshot;
        }

        $workItem->loadMissing('treatment');

        return $workItem->treatment?->code;
    }
}
