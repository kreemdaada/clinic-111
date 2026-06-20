<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DailyReport;
use App\Services\Import\ImportActivityLogger;
use App\Services\Import\ImportExtractionLogService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogController extends Controller
{
    public function __construct(
        private readonly ImportActivityLogger $importActivityLogger,
        private readonly ImportExtractionLogService $importExtractionLogService,
    ) {}

    public function index(): View
    {
        $auditLogs = AuditLog::query()
            ->with('user')
            ->latest('id')
            ->limit(50)
            ->get();

        $recentReports = DailyReport::query()
            ->latest('id')
            ->limit(20)
            ->get();

        $fileLogLines = $this->importActivityLogger->getRecentFileLogLines(100);

        return view('logs.index', [
            'auditLogs' => $auditLogs,
            'recentReports' => $recentReports,
            'fileLogLines' => $fileLogLines,
        ]);
    }

    public function extraction(DailyReport $dailyReport): View
    {
        $log = $this->importExtractionLogService->loadForReport($dailyReport);

        $importedByDoctor = [];

        if ($log !== null) {
            foreach ($log['imported_rows'] ?? [] as $row) {
                $doctorCode = (string) ($row['doctor_code'] ?? 'UNKNOWN');
                $importedByDoctor[$doctorCode][] = $row;
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
        }

        $skippedByDoctor = [];
        $unresolvedRows = [];

        if ($log !== null) {
            foreach ($log['skipped_rows'] ?? [] as $row) {
                $doctorKey = (string) ($row['doctor_label'] ?? 'UNKNOWN');
                $skippedByDoctor[$doctorKey][] = $row;
            }

            $unresolvedRows = $log['unresolved_rows'] ?? [];

            usort($unresolvedRows, function (array $a, array $b): int {
                $dayCompare = ((int) ($a['sheet_day'] ?? 0)) <=> ((int) ($b['sheet_day'] ?? 0));

                if ($dayCompare !== 0) {
                    return $dayCompare;
                }

                return ((int) ($a['excel_row'] ?? 0)) <=> ((int) ($b['excel_row'] ?? 0));
            });
        }

        return view('logs.extraction', [
            'dailyReport' => $dailyReport,
            'log' => $log,
            'importedByDoctor' => $importedByDoctor,
            'skippedByDoctor' => $skippedByDoctor,
            'unresolvedRows' => $unresolvedRows,
        ]);
    }

    public function downloadExtraction(DailyReport $dailyReport): BinaryFileResponse
    {
        $path = $this->importExtractionLogService->getLogPath($dailyReport);

        if ($path === null || ! is_file($path)) {
            abort(404, 'Extraction log not found for this report.');
        }

        return response()->download($path, 'extraction-report-'.$dailyReport->id.'.json');
    }
}
