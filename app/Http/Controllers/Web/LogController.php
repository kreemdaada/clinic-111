<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Services\Export\DoctorsIncomeExcelExportService;
use App\Services\Import\ImportExtractionLogService;
use App\Support\DoctorCodeResolver;
use App\Support\ExtractionLogDoctorGrouper;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Web UI for import audit logs and structured extraction diagnostics.
 *
 * Routes: GET /logs, /logs/extraction/{id}, /logs/extraction/{id}/download
 */
class LogController extends Controller
{
    public function __construct(
        private readonly ImportExtractionLogService $importExtractionLogService,
        private readonly DoctorsIncomeExcelExportService $incomeExporter,
    ) {}

    /**
     * Logs index redirects to the import page (open extraction log per report from there).
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('imports.index');
    }

    /**
     * Show structured per-doctor extraction log for one imported report.
     *
     * Groups imported rows by doctor code, sorts by sheet day / Excel row,
     * and prepares skipped and unresolved doctor rows for the Blade UI.
     *
     * @param  DailyReport  $dailyReport  Route-model-bound report.
     * @return View Renders `logs.extraction` with diagnostics and issue summary.
     */
    public function extraction(DailyReport $dailyReport): View
    {
        $log = $this->importExtractionLogService->loadForReport($dailyReport);

        $importedByDoctor = [];
        $doctorTotals = [];
        $unknownDoctorErrors = [];

        if ($log !== null) {
            foreach ($log['imported_rows'] ?? [] as $row) {
                $resolved = DoctorCodeResolver::resolve(
                    isset($row['doctor_code']) ? (string) $row['doctor_code'] : null,
                    isset($row['doctor_label']) ? (string) $row['doctor_label'] : null,
                );

                if (! $resolved['is_known']) {
                    continue;
                }

                $importedByDoctor[$resolved['code']][] = $row;
            }

            ksort($importedByDoctor);

            foreach ($importedByDoctor as $doctorCode => $rows) {
                usort($rows, function (array $a, array $b): int {
                    $dayCompare = ((int) ($a['sheet_day'] ?? 0)) <=> ((int) ($b['sheet_day'] ?? 0));

                    if ($dayCompare !== 0) {
                        return $dayCompare;
                    }

                    return ((int) ($a['excel_row'] ?? 0)) <=> ((int) ($b['excel_row'] ?? 0));
                });
                $importedByDoctor[$doctorCode] = $rows;
            }

            $doctorTotals = ExtractionLogDoctorGrouper::knownDoctorTotals($log);
            $unknownDoctorErrors = $log['unknown_doctor_errors']
                ?? ExtractionLogDoctorGrouper::unknownDoctorErrors($log);
        }

        $unresolvedRows = [];

        if ($log !== null) {
            $unresolvedRows = $log['unresolved_rows'] ?? [];

            usort($unresolvedRows, function (array $a, array $b): int {
                $dayCompare = ((int) ($a['sheet_day'] ?? 0)) <=> ((int) ($b['sheet_day'] ?? 0));

                if ($dayCompare !== 0) {
                    return $dayCompare;
                }

                return ((int) ($a['excel_row'] ?? 0)) <=> ((int) ($b['excel_row'] ?? 0));
            });
        }

        $doctorCodes = array_keys($importedByDoctor);

        return view('logs.extraction', [
            'dailyReport' => $dailyReport,
            'log' => $log,
            'importedByDoctor' => $importedByDoctor,
            'doctorTotals' => $doctorTotals,
            'doctorCodes' => $doctorCodes,
            'unknownDoctorErrors' => $unknownDoctorErrors,
            'unresolvedRows' => $unresolvedRows,
            'incomeDownloadFileName' => $this->incomeExporter->downloadFileName($dailyReport),
            'showImportComplete' => session()->pull('import_complete', false),
        ]);
    }

    /**
     * Download the raw JSON extraction log file for offline debugging.
     *
     * @param  DailyReport  $dailyReport  Route-model-bound report.
     * @return BinaryFileResponse Attachment `extraction-report-{id}.json`.
     */
    public function downloadExtraction(DailyReport $dailyReport): BinaryFileResponse
    {
        $path = $this->importExtractionLogService->getLogPath($dailyReport);

        if ($path === null || ! is_file($path)) {
            abort(404, 'Extraction log not found for this report.');
        }

        return response()->download($path, 'extraction-report-'.$dailyReport->id.'.json');
    }
}
