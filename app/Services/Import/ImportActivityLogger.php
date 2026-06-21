<?php

namespace App\Services\Import;

use App\Enums\AuditAction;
use App\Models\DailyReport;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Writes import outcomes to the import log channel and audit trail.
 */
class ImportActivityLogger
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Log a successful daily report import to the import channel and audit log.
     *
     * @param  DailyReport  $dailyReport  Imported report.
     * @param  int  $rowCount  Number of work rows persisted.
     * @param  string|null  $extractionLogPath  Absolute path to the JSON extraction log.
     */
    public function logSuccess(DailyReport $dailyReport, int $rowCount, ?string $extractionLogPath = null): void
    {
        $context = [
            'user_id' => Auth::id(),
            'report_id' => $dailyReport->id,
            'report_date' => $dailyReport->report_date->toDateString(),
            'source_file_name' => $dailyReport->source_file_name,
            'status' => $dailyReport->status->value,
            'row_count' => $rowCount,
            'extraction_log_path' => $extractionLogPath,
        ];

        Log::channel('import')->info('Daily report import succeeded.', $context);

        $this->auditLogService->log(
            AuditAction::ReportImport,
            $dailyReport,
            newValues: $context,
        );
    }

    /**
     * Log a failed import attempt to the import channel and audit log.
     *
     * @param  string  $fileName  Original uploaded file name.
     * @param  string  $errorMessage  Exception or validation message.
     */
    public function logFailure(string $fileName, string $errorMessage): void
    {
        $context = [
            'user_id' => Auth::id(),
            'source_file_name' => $fileName,
            'error' => $errorMessage,
            'status' => 'failed',
        ];

        Log::channel('import')->error('Daily report import failed.', $context);

        $this->auditLogService->log(
            AuditAction::ReportImport,
            newValues: $context,
        );
    }

    /**
     * Log reconciliation warnings/errors detected after import calculation.
     *
     * @param  DailyReport  $dailyReport  Report that was reconciled.
     * @param  array<int, array<string, mixed>>  $issues  Issues from {@see IncomeReconciliationService}.
     */
    public function logReconciliation(DailyReport $dailyReport, array $issues): void
    {
        $errorCount = count(array_filter($issues, fn (array $issue) => ($issue['severity'] ?? '') === 'error'));
        $warningCount = count($issues) - $errorCount;

        $context = [
            'user_id' => Auth::id(),
            'report_id' => $dailyReport->id,
            'report_date' => $dailyReport->report_date->toDateString(),
            'source_file_name' => $dailyReport->source_file_name,
            'reconciliation_errors' => $errorCount,
            'reconciliation_warnings' => $warningCount,
            'issues' => $issues,
        ];

        Log::channel('import')->warning('Daily report reconciliation issues detected.', $context);

        $this->auditLogService->log(
            AuditAction::ReportImport,
            $dailyReport,
            newValues: $context,
        );
    }

    /**
     * Read the most recent lines from import log files for UI display.
     *
     * @param  int  $limit  Maximum number of lines to return.
     * @return array<int, string> Most recent log lines, oldest first within the slice.
     */
    public function getRecentFileLogLines(int $limit = 100): array
    {
        $logDirectory = storage_path('logs');
        $logFiles = glob($logDirectory.'/import*.log');

        if ($logFiles === false || count($logFiles) === 0) {
            return [];
        }

        rsort($logFiles);

        $lines = [];

        foreach ($logFiles as $logPath) {
            $fileLines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if ($fileLines === false) {
                continue;
            }

            $lines = array_merge($fileLines, $lines);

            if (count($lines) >= $limit) {
                break;
            }
        }

        return array_slice($lines, -$limit);
    }
}
