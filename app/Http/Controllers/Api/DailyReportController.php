<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyReports\ImportDailyReportRequest;
use App\Models\DailyReport;
use App\Services\DailyReport\DailyReportQueryService;
use App\Services\Import\DailyReportImportService;
use App\Services\Import\DailyReportValidationSummaryService;
use Illuminate\Http\JsonResponse;

/**
 * Daily Excel report import and review API.
 *
 * Routes: POST /api/daily-reports/import, GET /api/daily-reports/{id}.
 */
class DailyReportController extends Controller
{
    public function __construct(
        private readonly DailyReportQueryService $dailyReportQueryService,
    ) {}

    /**
     * Upload Excel, run full import pipeline, return calculated report JSON.
     *
     * @param  ImportDailyReportRequest  $request  Validated `.xlsx` / `.xlsm` upload.
     * @param  DailyReportImportService  $importService  Parse → pay → treat → lab pipeline.
     * @return JsonResponse 201 with formatted report including rows, payments, work items, lab jobs.
     */
    public function import(
        ImportDailyReportRequest $request,
        DailyReportImportService $importService,
    ): JsonResponse {
        $dailyReport = $importService->import($request->file('file'));

        $message = $dailyReport->status->value === 'needs_review'
            ? 'Daily report imported with parser warnings requiring review.'
            : 'Daily report imported and calculated successfully.';

        return response()->json([
            'message' => $message,
            'data' => $this->formatDailyReport($dailyReport),
        ], 201);
    }

    /**
     * Return one daily report with all nested relations loaded.
     *
     * @param  DailyReport  $dailyReport  Route-model-bound report ID.
     * @return JsonResponse Report header + daily_work_rows with doctor, payments, work items, lab jobs.
     */
    public function show(DailyReport $dailyReport): JsonResponse
    {
        $dailyReport = $this->dailyReportQueryService->loadReportGraph($dailyReport);

        return response()->json([
            'data' => $this->formatDailyReport($dailyReport),
        ]);
    }

    /**
     * Return parser validation summary for a daily report import.
     */
    public function validationSummary(
        DailyReport $dailyReport,
        DailyReportValidationSummaryService $validationSummaryService,
    ): JsonResponse {
        $this->dailyReportQueryService->assertAccessible($dailyReport);

        return response()->json([
            'data' => $validationSummaryService->build($dailyReport),
        ]);
    }

    /**
     * Transform a DailyReport model into the API JSON shape.
     *
     * @param  DailyReport  $dailyReport  Report with `dailyWorkRows` relation loaded.
     * @return array<string, mixed> Snake_case keys for JSON serialization.
     */
    private function formatDailyReport(DailyReport $dailyReport): array
    {
        return [
            'id' => $dailyReport->id,
            'report_date' => $dailyReport->report_date->toDateString(),
            'source_type' => $dailyReport->source_type->value,
            'source_file_name' => $dailyReport->source_file_name,
            'status' => $dailyReport->status->value,
            'daily_work_rows' => $dailyReport->dailyWorkRows->map(function ($row) {
                return [
                    'id' => $row->id,
                    'doctor' => [
                        'id' => $row->doctor->id,
                        'code' => $row->doctor->code,
                        'name' => $row->doctor->name,
                    ],
                    'work_date' => $row->work_date->toDateString(),
                    'excel_row_number' => $row->excel_row_number,
                    'treatment_text' => $row->treatment_text,
                    'paid_total_aed' => $row->paid_total_aed,
                    'payments' => $row->payments->map(fn ($payment) => [
                        'id' => $payment->id,
                        'payment_method' => $payment->payment_method->value,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'amount_aed' => $payment->amount_aed,
                        'paid_at' => $payment->paid_at->toDateString(),
                    ]),
                    'work_items' => $row->workItems->map(fn ($item) => [
                        'id' => $item->id,
                        'treatment_code' => $item->treatment->code,
                        'treatment_name' => $item->treatment->name,
                        'quantity' => $item->quantity,
                        'confidence' => $item->confidence,
                        'warning_message' => $item->warning_message,
                        'lab_job' => $item->labJob ? [
                            'id' => $item->labJob->id,
                            'lab_id' => $item->labJob->lab_id,
                            'quantity' => $item->labJob->quantity,
                            'unit_cost' => $item->labJob->unit_cost,
                            'total_cost_aed' => $item->labJob->total_cost_aed,
                            'status' => $item->labJob->status->value,
                        ] : null,
                    ]),
                ];
            }),
        ];
    }
}
