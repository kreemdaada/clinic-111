<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyReports\ImportDailyReportRequest;
use App\Models\DailyReport;
use App\Services\Import\DailyReportImportService;
use Illuminate\Http\JsonResponse;

class DailyReportController extends Controller
{
    public function import(
        ImportDailyReportRequest $request,
        DailyReportImportService $importService,
    ): JsonResponse {
        $dailyReport = $importService->import($request->file('file'));

        return response()->json([
            'message' => 'Daily report imported and calculated successfully.',
            'data' => $this->formatDailyReport($dailyReport),
        ], 201);
    }

    public function show(DailyReport $dailyReport): JsonResponse
    {
        $dailyReport->load([
            'dailyWorkRows.doctor',
            'dailyWorkRows.payments',
            'dailyWorkRows.workItems.treatment',
            'dailyWorkRows.workItems.labJob.lab',
        ]);

        return response()->json([
            'data' => $this->formatDailyReport($dailyReport),
        ]);
    }

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
                    'patient_name' => $row->patient_name,
                    'mrn' => $row->mrn,
                    'file_number' => $row->file_number,
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
