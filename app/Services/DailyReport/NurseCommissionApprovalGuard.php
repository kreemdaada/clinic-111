<?php

namespace App\Services\DailyReport;

use App\Exceptions\NurseCommissionApprovalBlockedException;
use App\Models\DailyReport;
use App\Models\WorkItem;
use App\Support\AccountingScopedQuery;

/**
 * Blocks approve when eligible work items lack a nurse commission snapshot.
 */
class NurseCommissionApprovalGuard
{
    /**
     * @return list<string> Human-readable blocking messages.
     */
    public function blockingMessages(DailyReport $dailyReport): array
    {
        $messages = [];
        $clinicId = (int) $dailyReport->clinic_id;

        $workItems = AccountingScopedQuery::workItems($clinicId)
            ->whereHas('dailyWorkRow', fn ($query) => $query->where('daily_report_id', $dailyReport->id))
            ->with(['treatment', 'nurseCommission', 'dailyWorkRow.doctor'])
            ->get()
            ->filter(fn (WorkItem $workItem) => $workItem->treatment->requires_nurse_commission);

        foreach ($workItems as $workItem) {
            if ($workItem->nurseCommission !== null) {
                continue;
            }

            $doctorCode = $workItem->dailyWorkRow->doctor?->code ?? 'Unknown';
            $treatmentName = $workItem->treatment->name;
            $workDate = $workItem->dailyWorkRow->work_date?->toDateString() ?? 'unknown date';

            $messages[] = "A nurse must be selected for {$treatmentName} (doctor {$doctorCode}, {$workDate}).";
        }

        return $messages;
    }

    public function assertCanApprove(DailyReport $dailyReport): void
    {
        $messages = $this->blockingMessages($dailyReport);

        if ($messages !== []) {
            throw new NurseCommissionApprovalBlockedException($messages);
        }
    }
}
