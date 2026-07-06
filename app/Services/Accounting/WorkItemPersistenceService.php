<?php

namespace App\Services\Accounting;

use App\Models\DailyWorkRow;
use App\Models\Treatment;
use App\Models\WorkItem;

/**
 * Single entry point for creating work items with treatment snapshots.
 */
class WorkItemPersistenceService
{
    public function __construct(
        private readonly WorkItemTreatmentSnapshotService $workItemTreatmentSnapshotService,
    ) {}

    public function create(
        DailyWorkRow $dailyWorkRow,
        Treatment $treatment,
        int $quantity,
        int $confidence = 100,
        ?string $warningMessage = null,
    ): WorkItem {
        $attributes = array_merge(
            [
                'clinic_id' => $dailyWorkRow->clinic_id,
                'daily_work_row_id' => $dailyWorkRow->id,
                'treatment_id' => $treatment->id,
                'quantity' => $quantity,
                'confidence' => $confidence,
                'warning_message' => $warningMessage,
            ],
            $this->workItemTreatmentSnapshotService->buildAttributesFromTreatment($treatment),
        );

        return WorkItem::query()->create($attributes);
    }

    public function refreshTreatmentSnapshot(WorkItem $workItem, Treatment $treatment): WorkItem
    {
        return $this->workItemTreatmentSnapshotService->applySnapshotFromTreatment($workItem, $treatment);
    }
}
