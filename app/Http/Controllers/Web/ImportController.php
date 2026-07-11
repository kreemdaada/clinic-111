<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\IncomeExportBlockedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DailyReports\ImportDailyReportRequest;
use App\Models\DailyReport;
use App\Services\Configuration\BusinessConfigurationService;
use App\Services\DailyReport\DailyReportQueryService;
use App\Services\Export\DoctorsIncomeExcelExportService;
use App\Services\Import\DailyReportImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Web UI for drag-and-drop daily Excel import and Server Income download.
 *
 * Routes: GET/POST /imports, GET /imports/{id}/income
 */
class ImportController extends Controller
{
    /**
     * @param  DailyReportImportService  $importService  Full parse → calculate pipeline.
     * @param  DoctorsIncomeExcelExportService  $incomeExporter  Builds Server Income `.xlsx`.
     */
    public function __construct(
        private readonly DailyReportImportService $importService,
        private readonly DoctorsIncomeExcelExportService $incomeExporter,
        private readonly DailyReportQueryService $dailyReportQueryService,
        private readonly BusinessConfigurationService $businessConfigurationService,
    ) {}

    /**
     * Show import dropzone, PHP upload limits, and recent import history.
     *
     * @return View Renders `imports.index`.
     */
    public function index(): View
    {
        $recentReports = $this->dailyReportQueryService->listRecent(10);
        $configurationStatus = $this->businessConfigurationService->status();

        return view('imports.index', [
            'recentReports' => $recentReports,
            'configurationStatus' => $configurationStatus,
            'canImport' => $configurationStatus['ready_for_import'],
        ]);
    }

    /**
     * Accept uploaded Excel, run import, then show extraction log with download preview.
     *
     * On failure redirects back with validation/error message (no partial DB state).
     *
     * @param  ImportDailyReportRequest  $request  Validated `.xlsx` / `.xlsm` file.
     * @return RedirectResponse Extraction log page or back() with errors.
     */
    public function store(ImportDailyReportRequest $request): RedirectResponse
    {
        try {
            $dailyReport = $this->importService->import($request->file('file'));

            return redirect()
                ->route('logs.extraction', $dailyReport)
                ->with('import_complete', true);
        } catch (Throwable $exception) {
            return back()
                ->withErrors(['file' => $exception->getMessage()]);
        }
    }

    /**
     * Re-download Server Income Excel for a previously imported report.
     *
     * @param  DailyReport  $dailyReport  Route-model-bound report.
     * @return BinaryFileResponse|RedirectResponse Generated Excel or redirect with guidance.
     */
    public function downloadIncome(DailyReport $dailyReport): BinaryFileResponse|RedirectResponse
    {
        $this->dailyReportQueryService->assertAccessible($dailyReport);

        try {
            return $this->incomeExporter->downloadResponse($dailyReport);
        } catch (IncomeExportBlockedException $exception) {
            return redirect()
                ->back(fallback: route('logs.extraction', $dailyReport))
                ->withErrors([
                    'income_export' => $exception->userFacingMessages(),
                ]);
        }
    }

    /**
     * Delete a previously imported report and all related rows/logs.
     */
    public function destroy(DailyReport $dailyReport): RedirectResponse
    {
        $this->dailyReportQueryService->assertAccessible($dailyReport);

        if ($dailyReport->isLocked()) {
            return back()->withErrors([
                'delete' => __('messages.reports.locked_cannot_delete'),
            ]);
        }

        $relativeLogPath = 'import-extractions/report-'.$dailyReport->id.'.json';

        if (Storage::disk('local')->exists($relativeLogPath)) {
            Storage::disk('local')->delete($relativeLogPath);
        }

        $dailyReport->delete();

        return redirect()
            ->route('imports.index')
            ->with('status', __('configuration.flash.import_deleted'));
    }
}
