<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Models\Doctor;
use App\Models\NurseCommission;
use App\Models\Treatment;
use App\Services\DailyReport\DailyReportQueryService;
use App\Services\Export\DoctorsIncomeExcelExportService;
use App\Services\Import\ExtractionLogPresentationService;
use App\Services\Import\ImportExtractionLogService;
use App\Support\DoctorCodeResolver;
use App\Support\ExtractionLogDoctorGrouper;
use App\Support\OpgClinicDoctor;
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
        private readonly ExtractionLogPresentationService $extractionLogPresentationService,
        private readonly DoctorsIncomeExcelExportService $incomeExporter,
        private readonly DailyReportQueryService $dailyReportQueryService,
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
        $this->dailyReportQueryService->assertAccessible($dailyReport);

        $dailyReport->loadMissing('clinic');
        $clinic = $dailyReport->clinic;

        $log = $this->importExtractionLogService->loadForReport($dailyReport);

        $importedByDoctor = [];
        $doctorTotals = [];
        $unknownDoctorErrors = [];

        if ($log !== null) {
            foreach ($log['imported_rows'] ?? [] as $row) {
                $doctorCode = isset($row['doctor_code']) ? (string) $row['doctor_code'] : null;
                $doctorLabel = isset($row['doctor_label']) ? (string) $row['doctor_label'] : null;

                if (OpgClinicDoctor::matches($doctorCode, $doctorLabel)) {
                    continue;
                }

                $resolved = DoctorCodeResolver::resolve($doctorCode, $doctorLabel);

                if (! $resolved['is_known']) {
                    continue;
                }

                $importedByDoctor[$resolved['code']][] = $row;
            }

            ksort($importedByDoctor);

            $treatmentNamesByCode = Treatment::query()
                ->where('clinic_id', $clinic->id)
                ->pluck('name', 'code')
                ->all();

            foreach ($importedByDoctor as $doctorCode => $rows) {
                usort($rows, function (array $a, array $b): int {
                    $dayCompare = ((int) ($a['sheet_day'] ?? 0)) <=> ((int) ($b['sheet_day'] ?? 0));

                    if ($dayCompare !== 0) {
                        return $dayCompare;
                    }

                    return ((int) ($a['excel_row'] ?? 0)) <=> ((int) ($b['excel_row'] ?? 0));
                });
                $importedByDoctor[$doctorCode] = array_map(
                    function (array $row) use ($clinic, $treatmentNamesByCode): array {
                        $presented = $this->extractionLogPresentationService->presentImportedRow($row, $clinic);
                        $presented['display_treatment_text'] = $this->displayTreatmentText(
                            (string) ($presented['treatment_text'] ?? ''),
                            $treatmentNamesByCode,
                        );

                        return $presented;
                    },
                    $rows,
                );
            }

            $doctorTotals = $this->extractionLogPresentationService->presentDoctorTotals(
                ExtractionLogDoctorGrouper::knownDoctorTotals($log),
                $clinic,
            );

            $doctorDisplayNames = Doctor::query()
                ->where('clinic_id', $clinic->id)
                ->whereIn('code', array_keys($doctorTotals))
                ->pluck('name', 'code');

            foreach ($doctorTotals as $code => $totals) {
                $doctorTotals[$code]['display_name'] = $this->doctorDisplayName(
                    (string) ($doctorDisplayNames[$code] ?? $totals['doctor_label'] ?? $code),
                );
            }

            $unknownDoctorErrors = ExtractionLogDoctorGrouper::unknownDoctorErrors($log);
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
            'nurseCommissionsByWorkRow' => $this->nurseCommissionsByWorkRow($dailyReport),
        ]);
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function nurseCommissionsByWorkRow(DailyReport $dailyReport): array
    {
        $commissions = NurseCommission::query()
            ->where('clinic_id', $dailyReport->clinic_id)
            ->whereHas('workItem.dailyWorkRow', fn ($query) => $query->where('daily_report_id', $dailyReport->id))
            ->with('workItem.treatment')
            ->get();

        $grouped = [];

        foreach ($commissions as $commission) {
            $workRowId = (int) $commission->workItem->daily_work_row_id;

            $grouped[$workRowId][] = [
                'treatment_name' => $commission->treatment_name_snapshot,
                'treatment_code' => $commission->treatment_code_snapshot,
                'quantity' => (int) $commission->quantity,
                'nurse_name' => $commission->nurse_name_snapshot,
                'treatment_price' => (string) $commission->treatment_price_original,
                'treatment_price_currency' => $commission->treatment_price_currency,
                'commission_percentage' => (string) $commission->commission_percentage,
                'unit_commission_aed' => (string) $commission->unit_commission_aed,
                'total_commission_aed' => (string) $commission->total_commission_aed,
            ];
        }

        return $grouped;
    }

    /**
     * Download the raw JSON extraction log file for offline debugging.
     *
     * @param  DailyReport  $dailyReport  Route-model-bound report.
     * @return BinaryFileResponse Attachment `extraction-report-{id}.json`.
     */
    public function downloadExtraction(DailyReport $dailyReport): BinaryFileResponse
    {
        $this->dailyReportQueryService->assertAccessible($dailyReport);

        $path = $this->importExtractionLogService->getLogPath($dailyReport);

        if ($path === null || ! is_file($path)) {
            abort(404, 'Import log not found for this report.');
        }

        return response()->download($path, 'extraction-report-'.$dailyReport->id.'.json');
    }

    private function doctorDisplayName(string $name): string
    {
        $trimmed = trim($name);

        return preg_replace('/^Dr\.?\s+/i', '', $trimmed) ?? $trimmed;
    }

    /**
     * @param  array<string, string>  $treatmentNamesByCode
     */
    private function displayTreatmentText(string $text, array $treatmentNamesByCode): string
    {
        $displayText = trim($text);

        if ($displayText === '') {
            return '—';
        }

        foreach ($treatmentNamesByCode as $code => $name) {
            $displayText = preg_replace('/\b'.preg_quote($code, '/').'\b/', $name, $displayText) ?? $displayText;
        }

        return $displayText;
    }
}
