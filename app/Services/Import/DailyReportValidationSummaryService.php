<?php

namespace App\Services\Import;

use App\Models\DailyReport;
use App\Models\DailyReportImportWarning;
use App\Services\Accounting\Concerns\ScopesAccountingQueries;
use App\Services\Configuration\CurrentClinicResolver;
use App\Support\AccountingScopedQuery;

/**
 * Builds the validation summary payload for a daily report import.
 */
class DailyReportValidationSummaryService
{
    use ScopesAccountingQueries;

    public function __construct(
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

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
        $this->assertSameClinic($dailyReport);

        $clinicId = (int) $dailyReport->clinic_id;
        $workRows = AccountingScopedQuery::workRows($clinicId, $dailyReport->id)->get();
        $workRowIds = $workRows->pluck('id');

        $parsedItems = AccountingScopedQuery::workItems($clinicId)
            ->whereIn('daily_work_row_id', $workRowIds)
            ->count();

        $warnings = DailyReportImportWarning::query()
            ->where('daily_report_id', $dailyReport->id)
            ->orderBy('excel_row_number')
            ->orderBy('id')
            ->get()
            ->map(fn (DailyReportImportWarning $warning) => [
                'excel_row' => (int) ($warning->excel_row_number ?? 0),
                'doctor' => (string) ($warning->doctor_code ?? ''),
                'treatment_text' => $warning->treatment_text,
                'message' => $warning->message,
            ])
            ->all();

        return [
            'total_rows' => $workRows->count(),
            'parsed_items' => $parsedItems,
            'warnings_count' => count($warnings),
            'warnings' => $warnings,
        ];
    }
}
