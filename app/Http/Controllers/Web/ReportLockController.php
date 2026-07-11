<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\NurseCommissionApprovalBlockedException;
use App\Exceptions\UserFacingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DailyReports\UnlockDailyReportRequest;
use App\Models\DailyReport;
use App\Services\DailyReport\DailyReportLockService;
use App\Services\DailyReport\DailyReportQueryService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * Admin approve/unlock workflow for daily reports.
 */
class ReportLockController extends Controller
{
    public function __construct(
        private readonly DailyReportLockService $lockService,
        private readonly DailyReportQueryService $dailyReportQueryService,
    ) {}

    public function approve(DailyReport $dailyReport): RedirectResponse
    {
        $this->dailyReportQueryService->assertAccessible($dailyReport);

        try {
            $this->lockService->approve($dailyReport, request()->user());
        } catch (NurseCommissionApprovalBlockedException|UserFacingException $exception) {
            return back()->withErrors(['approve' => $exception->getMessage()]);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['approve' => $exception->getMessage()]);
        }

        return back();
    }

    public function unlock(UnlockDailyReportRequest $request, DailyReport $dailyReport): RedirectResponse
    {
        $this->dailyReportQueryService->assertAccessible($dailyReport);

        try {
            $this->lockService->unlock($dailyReport, $request->user(), $request->validated('reason'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['unlock' => $exception->getMessage()]);
        }

        return back()->with('success', __('messages.reports.unlocked_for_editing'));
    }
}
