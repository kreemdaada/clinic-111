<?php

namespace App\Services\Import;

use App\Models\DailyReport;
use App\Models\DailyReportImportWarning;

/**
 * Builds the validation summary payload for a daily report import.
 */
class DailyReportValidationSummaryService
{
    /**
     * @return array{
     *     total_rows: int,
     *     parsed_items: int,
     *     warnings_count: int,
     *     warnings: array<int, array{excel_row: int, doctor: string, treatment_text: string|null, message: string}>
     * }
     */
    public function build(DailyReport $dailyReport): array
    {
        $dailyReport->load([
            'dailyWorkRows.workItems',
            'importWarnings',
        ]);

        $parsedItems = $dailyReport->dailyWorkRows->sum(
            fn ($row) => $row->workItems->count(),
        );

        $warnings = $dailyReport->importWarnings
            ->sortBy([
                ['excel_row_number', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->map(fn (DailyReportImportWarning $warning) => [
                'excel_row' => (int) ($warning->excel_row_number ?? 0),
                'doctor' => (string) ($warning->doctor_code ?? ''),
                'treatment_text' => $warning->treatment_text,
                'message' => $warning->message,
            ])
            ->all();

        return [
            'total_rows' => $dailyReport->dailyWorkRows->count(),
            'parsed_items' => $parsedItems,
            'warnings_count' => count($warnings),
            'warnings' => $warnings,
        ];
    }
}
