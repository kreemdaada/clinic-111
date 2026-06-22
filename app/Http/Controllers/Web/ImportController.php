<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyReports\ImportDailyReportRequest;
use App\Models\DailyReport;
use App\Services\Export\DoctorsIncomeExcelExportService;
use App\Services\Import\DailyReportImportService;
use Illuminate\Http\RedirectResponse;
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
    ) {}

    /**
     * Show import dropzone, PHP upload limits, and recent import history.
     *
     * @return View Renders `imports.index`.
     */
    public function index(): View
    {
        $recentReports = DailyReport::query()
            ->latest('id')
            ->limit(10)
            ->get();

        return view('imports.index', [
            'recentReports' => $recentReports,
        ]);
    }

    /**
     * Accept uploaded Excel, import, and immediately download Server Income Excel.
     *
     * On failure redirects back with validation/error message (no partial DB state).
     *
     * @param  ImportDailyReportRequest  $request  Validated `.xlsx` / `.xlsm` file.
     * @return BinaryFileResponse|RedirectResponse Excel download or back() with errors.
     */
    public function store(ImportDailyReportRequest $request): BinaryFileResponse|RedirectResponse
    {
        try {
            $dailyReport = $this->importService->import($request->file('file'));

            return $this->incomeExporter->downloadResponse($dailyReport);
        } catch (Throwable $exception) {
            return back()
                ->withErrors(['file' => $exception->getMessage()]);
        }
    }

    /**
     * Re-download Server Income Excel for a previously imported report.
     *
     * @param  DailyReport  $dailyReport  Route-model-bound report.
     * @return BinaryFileResponse Generated `Server Income {Month Year}.xlsx`.
     */
    public function downloadIncome(DailyReport $dailyReport): BinaryFileResponse
    {
        return $this->incomeExporter->downloadResponse($dailyReport);
    }
}
