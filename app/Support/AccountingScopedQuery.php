<?php

namespace App\Support;

use App\Models\DailyWorkRow;
use App\Models\LabJob;
use App\Models\NurseCommission;
use App\Models\Payment;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Explicit clinic_id + parent-key queries for accounting entities (ADR-029).
 *
 * Never traverse parent relations (e.g. $report->payments()). Always filter by clinic_id directly.
 */
final class AccountingScopedQuery
{
    /**
     * @return Builder<DailyWorkRow>
     */
    public static function workRows(int $clinicId, ?int $dailyReportId = null): Builder
    {
        $query = DailyWorkRow::query()->where('clinic_id', $clinicId);

        if ($dailyReportId !== null) {
            $query->where('daily_report_id', $dailyReportId);
        }

        return $query;
    }

    /**
     * @return Builder<Payment>
     */
    public static function payments(int $clinicId, ?int $dailyWorkRowId = null): Builder
    {
        $query = Payment::query()->where('clinic_id', $clinicId);

        if ($dailyWorkRowId !== null) {
            $query->where('daily_work_row_id', $dailyWorkRowId);
        }

        return $query;
    }

    /**
     * @return Builder<WorkItem>
     */
    public static function workItems(int $clinicId, ?int $dailyWorkRowId = null): Builder
    {
        $query = WorkItem::query()->where('clinic_id', $clinicId);

        if ($dailyWorkRowId !== null) {
            $query->where('daily_work_row_id', $dailyWorkRowId);
        }

        return $query;
    }

    /**
     * @return Builder<LabJob>
     */
    public static function labJobs(int $clinicId, ?int $workItemId = null): Builder
    {
        $query = LabJob::query()->where('clinic_id', $clinicId);

        if ($workItemId !== null) {
            $query->where('work_item_id', $workItemId);
        }

        return $query;
    }

    /**
     * @return Builder<NurseCommission>
     */
    public static function nurseCommissions(int $clinicId, ?int $workItemId = null): Builder
    {
        $query = NurseCommission::query()->where('clinic_id', $clinicId);

        if ($workItemId !== null) {
            $query->where('work_item_id', $workItemId);
        }

        return $query;
    }
}
