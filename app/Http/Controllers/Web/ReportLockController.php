<?php

namespace App\Http\Controllers\Web;

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
        } catch (RuntimeException $exception) {
            return back()->withErrors(['approve' => $exception->getMessage()]);
        }

        return back()->with('success', 'Report approved and locked.');
    }

    public function unlock(UnlockDailyReportRequest $request, DailyReport $dailyReport): RedirectResponse
    {
        $this->dailyReportQueryService->assertAccessible($dailyReport);

        try {
            $this->lockService->unlock($dailyReport, $request->user(), $request->validated('reason'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['unlock' => $exception->getMessage()]);
        }

        return back()->with('success', 'Report unlocked for editing.');
    }
}
