<?php

namespace App\Console\Commands;

use App\Services\Accounting\WorkItemTreatmentSnapshotBackfillService;
use Illuminate\Console\Command;

class BackfillWorkItemTreatmentSnapshotsCommand extends Command
{
    protected $signature = 'work-items:backfill-treatment-snapshots';

    protected $description = 'Idempotently backfill missing work item treatment price snapshots';

    public function handle(WorkItemTreatmentSnapshotBackfillService $backfillService): int
    {
        $updated = $backfillService->run();

        $this->info("Backfilled {$updated} work item snapshot(s).");

        return self::SUCCESS;
    }
}
