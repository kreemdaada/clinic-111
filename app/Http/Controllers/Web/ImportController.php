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

class ImportController extends Controller
{
    public function __construct(
        private readonly DailyReportImportService $importService,
        private readonly DoctorsIncomeExcelExportService $incomeExporter,
    ) {}

    public function index(): View
    {
        $recentReports = DailyReport::query()
            ->latest('id')
            ->limit(10)
            ->get();

        return view('imports.index', [
            'recentReports' => $recentReports,
            'uploadMaxFilesize' => ini_get('upload_max_filesize'),
            'postMaxSize' => ini_get('post_max_size'),
        ]);
    }

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

    public function downloadIncome(DailyReport $dailyReport): BinaryFileResponse
    {
        return $this->incomeExporter->downloadResponse($dailyReport);
    }
}
