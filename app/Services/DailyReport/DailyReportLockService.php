<?php

namespace App\Services\DailyReport;

use App\Enums\ReportStatus;
use App\Models\DailyReport;
use App\Models\User;
use App\Services\Accounting\Concerns\ScopesAccountingQueries;
use App\Services\Audit\AuditLogService;
use App\Services\Configuration\CurrentClinicResolver;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Approve, lock, and unlock daily reports for production accounting controls.
 */
class DailyReportLockService
{
    use ScopesAccountingQueries;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentClinicResolver $currentClinicResolver,
        private readonly NurseCommissionApprovalGuard $nurseCommissionApprovalGuard,
    ) {}

    public function approve(DailyReport $dailyReport, User $user): DailyReport
    {
        $this->assertSameClinic($dailyReport);

        if ($dailyReport->isLocked()) {
            throw new RuntimeException('Report is already approved or locked.');
        }

        if (! in_array($dailyReport->status, [ReportStatus::Calculated, ReportStatus::NeedsReview], true)) {
            throw new RuntimeException('Only calculated reports can be approved.');
        }

        $this->nurseCommissionApprovalGuard->assertCanApprove($dailyReport);

        return DB::transaction(function () use ($dailyReport, $user) {
            $oldStatus = $dailyReport->status->value;

            $dailyReport->forceFill([
                'status' => ReportStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $user->id,
                'locked_at' => now(),
                'locked_by' => $user->id,
                'unlock_reason' => null,
                'unlocked_at' => null,
                'unlocked_by' => null,
            ])->save();

            $this->auditLogService->logReportApproved($dailyReport, $oldStatus);

            return $dailyReport->fresh();
        });
    }

    public function unlock(DailyReport $dailyReport, User $user, string $reason): DailyReport
    {
        $this->assertSameClinic($dailyReport);

        if (! $dailyReport->isLocked()) {
            throw new RuntimeException('Report is not locked.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException('Unlock reason is required.');
        }

        return DB::transaction(function () use ($dailyReport, $user, $reason) {
            $oldStatus = $dailyReport->status->value;

            $dailyReport->forceFill([
                'status' => ReportStatus::NeedsReview,
                'unlocked_at' => now(),
                'unlocked_by' => $user->id,
                'unlock_reason' => $reason,
            ])->save();

            $this->auditLogService->logReportUnlocked($dailyReport, $oldStatus, $reason);

            return $dailyReport->fresh();
        });
    }
}
