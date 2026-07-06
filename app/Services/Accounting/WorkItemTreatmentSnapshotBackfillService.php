<?php

namespace App\Services\Accounting;

use App\Models\WorkItem;

/**
 * Idempotent backfill of work item treatment snapshots for legacy rows.
 *
 * Priority: existing nurse commission snapshot, then current treatment catalog price.
 */
class WorkItemTreatmentSnapshotBackfillService
{
    public function __construct(
        private readonly WorkItemTreatmentSnapshotService $workItemTreatmentSnapshotService,
    ) {}

    public function run(): int
    {
        $updated = 0;

        WorkItem::query()
            ->with(['treatment', 'nurseCommission'])
            ->orderBy('id')
            ->chunkById(200, function ($workItems) use (&$updated) {
                foreach ($workItems as $workItem) {
                    $attributes = $this->workItemTreatmentSnapshotService->resolveBackfillAttributes($workItem);

                    if ($attributes === null) {
                        continue;
                    }

                    $workItem->fill($attributes);
                    $workItem->save();
                    $updated++;
                }
            });

        return $updated;
    }
}
