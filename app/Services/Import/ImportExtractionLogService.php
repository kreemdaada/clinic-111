<?php

namespace App\Services\Import;

use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Services\Accounting\TreatmentParserService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Structured extraction log: one entry per doctor/day from parser + DB calculations.
 * Human-readable lines are written to stderr (visible in ./bin/serve terminal).
 */
class ImportExtractionLogService
{
    /** @var array<string, mixed> */
    private array $document = [];

    /** @var array<int, array<string, mixed>> */
    private array $parserEvents = [];

    /**
     * @param  TreatmentParserService  $treatmentParserService  Injected treatment parser service.
     * @param  ImportRowDiagnosticsBuilder  $diagnosticsBuilder  Builds per-row calculation diagnostics.
     */
    public function __construct(
        private readonly TreatmentParserService $treatmentParserService,
        private readonly ImportRowDiagnosticsBuilder $diagnosticsBuilder,
    ) {}

    /**
     * Initialize a new extraction log document for the given report.
     *
     * Resets in-memory state and prints a start banner to the import terminal.
     *
     * @param  DailyReport  $dailyReport  Report being imported.
     * @param  string  $sourcePath  Absolute path to the uploaded Excel file.
     */
    public function startReport(DailyReport $dailyReport, string $sourcePath): void
    {
        $this->document = [
            'report_id' => $dailyReport->id,
            'report_month' => $dailyReport->report_date->format('Y-m'),
            'source_file_name' => $dailyReport->source_file_name,
            'source_path' => $sourcePath,
            'started_at' => now()->toIso8601String(),
            'imported_rows' => [],
            'skipped_rows' => [],
            'unresolved_rows' => [],
            'reconciliation_issues' => [],
            'issue_summary' => ['error' => 0, 'warning' => 0, 'info' => 0],
            'doctor_totals' => [],
        ];
        $this->parserEvents = [];

        $this->terminal(sprintf(
            '[IMPORT] Starting report #%d — %s — %s',
            $dailyReport->id,
            $dailyReport->report_date->format('F Y'),
            $dailyReport->source_file_name,
        ));
    }

    /**
     * Merge parser extraction events into the log document.
     *
     * Skipped rows are stored separately; extracted rows are queued for later enrichment.
     *
     * @param  array<int, array<string, mixed>>  $events  Events from {@see ExcelDailyReportParser}.
     */
    public function recordParserEvents(array $events): void
    {
        $this->parserEvents = $events;

        foreach ($events as $event) {
            if (($event['status'] ?? '') === 'skipped') {
                $this->document['skipped_rows'][] = $event;
                $this->terminalSkippedRow($event);

                continue;
            }

            if (($event['status'] ?? '') === 'extracted') {
                $this->document['imported_rows'][] = array_merge($event, [
                    'work_row_id' => null,
                    'paid_total_aed' => null,
                    'usd_to_aed_amount' => null,
                    'treatments_parsed' => [],
                    'lab_total_aed' => null,
                    'diagnostics' => null,
                    'issues' => [],
                ]);

                $this->terminalExtractedPreview($event);
            }
        }
    }

    /**
     * Store income-reconciliation issues and echo each to the terminal.
     *
     * @param  array<int, array<string, mixed>>  $issues  Issues from {@see IncomeReconciliationService}.
     */
    public function recordReconciliationIssues(array $issues): void
    {
        $this->document['reconciliation_issues'] = $issues;

        foreach ($issues as $issue) {
            $this->terminalIssue($issue, 'RECON');
        }
    }

    /**
     * Print a compact preview line for a parser-extracted row.
     *
     * @param  array<string, mixed>  $event  Parser extraction event.
     */
    private function terminalExtractedPreview(array $event): void
    {
        $this->terminal(sprintf(
            '[PARSE] %s | Tag %s | Excel-Zeile %s | DHS=%s USD=%s VISA=%s TOTAL=%s',
            $event['doctor_label'] ?? '?',
            $event['sheet_day'] ?? '?',
            $event['excel_row'] ?? '?',
            $event['dhs_aed'] ?? '0',
            $event['usd'] ?? '0',
            $event['visa_aed'] ?? '0',
            $this->formatPaymentTotal($event),
        ));
        $this->terminal('        TEXT: '.$this->truncateTreatmentText((string) ($event['treatment_text'] ?? ''), 120));
    }

    /**
     * Compute DHS + USD→AED + VISA total for terminal display.
     *
     * @param  array<string, mixed>  $event  Parser extraction event with payment fields.
     * @return string Combined total in AED with 2 decimal places.
     */
    private function formatPaymentTotal(array $event): string
    {
        $dhs = preg_replace('/[^\d.\-]/', '', (string) ($event['dhs_aed'] ?? '0')) ?: '0';
        $usd = preg_replace('/[^\d.\-]/', '', (string) ($event['usd'] ?? '0')) ?: '0';
        $visa = preg_replace('/[^\d.\-]/', '', (string) ($event['visa_aed'] ?? '0')) ?: '0';
        $usdToAed = is_numeric($usd) ? bcmul($usd, '3.65', 2) : '0.00';

        return bcadd(bcadd($dhs, $usdToAed, 2), $visa, 2);
    }

    /**
     * Log a parsed row whose doctor label could not be matched to the database.
     *
     * @param  array<string, mixed>  $parsedRow  Raw row from the Excel parser.
     */
    public function recordUnresolvedDoctorRow(array $parsedRow): void
    {
        $entry = [
            'status' => 'unresolved_doctor',
            'reason' => 'unknown_doctor_label',
            'sheet_day' => (int) ($parsedRow['sheet_day'] ?? 0),
            'sheet_name' => $parsedRow['sheet_name'] ?? null,
            'excel_row' => (int) ($parsedRow['raw_row_number'] ?? $parsedRow['excel_row'] ?? 0),
            'doctor_label' => $parsedRow['doctor'] ?? null,
            'dhs_aed' => $this->normalizeAmountForLog($parsedRow['dhs_amount'] ?? null),
            'usd' => $this->normalizeAmountForLog($parsedRow['usd_amount'] ?? null),
            'visa_aed' => $this->normalizeAmountForLog($parsedRow['visa_amount'] ?? null),
            'treatment_text' => $parsedRow['treatment_text'] ?? null,
            'g_cell' => $parsedRow['raw_cells']['G'] ?? null,
            'total_cost' => $this->normalizeAmountForLog($parsedRow['total_cost'] ?? null),
        ];

        $this->document['unresolved_rows'][] = $entry;

        $this->terminal(sprintf(
            '[IMPORT UNRESOLVED] %s day %s row %s | DHS=%s USD=%s VISA=%s | %s',
            $entry['doctor_label'] ?? '?',
            $entry['sheet_day'] ?? '?',
            $entry['excel_row'] ?? '?',
            $entry['dhs_aed'] ?? '-',
            $entry['usd'] ?? '-',
            $entry['visa_aed'] ?? '-',
            $this->truncateTreatmentText((string) ($entry['treatment_text'] ?? '')),
        ));
    }

    /**
     * Normalize a cell value to a two-decimal string for log output.
     *
     * @param  mixed  $value  Raw amount from Excel or database.
     * @return string|null Formatted amount, or null if not numeric.
     */
    private function normalizeAmountForLog(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d.\-]/', '', (string) $value);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    /**
     * Record a newly persisted work row in the extraction log (pre-calculation).
     *
     * @param  DailyWorkRow  $dailyWorkRow  Saved database row.
     * @param  array<string, mixed>  $parsedRow  Original parsed Excel data.
     */
    public function recordPersistedRow(DailyWorkRow $dailyWorkRow, array $parsedRow): void
    {
        $dailyWorkRow->loadMissing('doctor');

        $excelRow = (int) ($parsedRow['raw_row_number'] ?? $parsedRow['excel_row'] ?? 0);
        $key = $this->rowKey(
            (int) ($parsedRow['sheet_day'] ?? 0),
            (string) ($parsedRow['doctor'] ?? ''),
            $excelRow,
        );

        $entry = [
            'work_row_id' => $dailyWorkRow->id,
            'work_date' => $dailyWorkRow->work_date?->toDateString(),
            'doctor_label' => $parsedRow['doctor'] ?? null,
            'doctor_code' => $dailyWorkRow->doctor?->code,
            'sheet_day' => (int) ($parsedRow['sheet_day'] ?? 0),
            'excel_row' => $excelRow,
            'sheet_name' => $parsedRow['sheet_name'] ?? null,
            'status' => 'imported',
            'dhs_aed' => (string) $dailyWorkRow->dhs_amount,
            'usd' => (string) $dailyWorkRow->usd_amount,
            'usd_to_aed' => (string) $dailyWorkRow->usd_to_aed_amount,
            'visa_aed' => (string) $dailyWorkRow->visa_amount,
            'paid_total_aed' => (string) $dailyWorkRow->paid_total_aed,
            'treatment_text' => $dailyWorkRow->treatment_text,
            'g_cell' => $parsedRow['raw_cells']['G'] ?? null,
            'total_cost' => (string) $dailyWorkRow->total_cost,
            'crown_count' => $dailyWorkRow->crown_count,
            'flags' => $this->detectFlags($dailyWorkRow->treatment_text, $parsedRow['raw_cells']['G'] ?? null),
            'treatments_parsed' => [],
            'lab_total_aed' => '0.00',
        ];

        $this->upsertImportedRow($key, $entry);

        Log::channel('import')->info('Extracted daily subtotal.', $entry);
    }

    /**
     * Enrich an imported row with treatment parsing and lab-job diagnostics.
     *
     * Called after {@see TreatmentParserService} and lab calculations complete.
     *
     * @param  DailyWorkRow  $dailyWorkRow  Work row with work items loaded.
     */
    public function recordCalculatedRow(DailyWorkRow $dailyWorkRow): void
    {
        if ($this->document === []) {
            return;
        }

        $dailyWorkRow->loadMissing(['doctor', 'workItems.treatment', 'workItems.labJob']);

        $parsedRow = is_array($dailyWorkRow->raw_data_json) ? $dailyWorkRow->raw_data_json : [];
        $excelRow = (int) ($parsedRow['raw_row_number'] ?? $parsedRow['excel_row'] ?? 0);
        $key = $this->rowKey(
            (int) ($parsedRow['sheet_day'] ?? 0),
            (string) ($parsedRow['doctor'] ?? ($dailyWorkRow->doctor?->code ?? '')),
            $excelRow,
        );

        $diagnostics = $this->diagnosticsBuilder->buildForWorkRow($dailyWorkRow);

        $treatments = array_merge(
            $diagnostics['treatments_lab'] ?? [],
            array_map(fn (array $t): array => array_merge($t, ['counts_for_job' => false]), $diagnostics['treatments_ignored'] ?? []),
        );

        $patch = [
            'work_row_id' => $dailyWorkRow->id,
            'treatments_parsed' => $treatments,
            'lab_total_aed' => $diagnostics['job']['total_aed'] ?? '0.00',
            'diagnostics' => $diagnostics,
            'issues' => $diagnostics['issues'] ?? [],
            'has_issues' => ($diagnostics['issue_count'] ?? 0) > 0,
        ];

        if ($this->upsertImportedRow($key, $patch)) {
            $this->terminalImportedRow($this->findImportedRow($key));

            return;
        }

        $this->document['imported_rows'][] = array_merge([
            'work_row_id' => $dailyWorkRow->id,
            'work_date' => $dailyWorkRow->work_date?->toDateString(),
            'doctor_code' => $dailyWorkRow->doctor?->code,
            'paid_total_aed' => (string) $dailyWorkRow->paid_total_aed,
            'treatment_text' => $dailyWorkRow->treatment_text,
            'treatments_parsed' => $treatments,
            'lab_total_aed' => $diagnostics['job']['total_aed'] ?? '0.00',
            'diagnostics' => $diagnostics,
            'issues' => $diagnostics['issues'] ?? [],
        ], $patch);

        $this->terminalImportedRow($this->findImportedRow($key));
    }

    /**
     * Write the extraction log JSON to disk and print a summary to the terminal.
     *
     * @param  DailyReport  $dailyReport  Report whose import has finished.
     * @return string Absolute path to the saved JSON log file.
     */
    public function finalize(DailyReport $dailyReport): string
    {
        $this->document['finished_at'] = now()->toIso8601String();
        $this->document['doctor_totals'] = $this->buildDoctorTotals();
        $this->document['issue_summary'] = $this->buildIssueSummary();

        $relativePath = 'import-extractions/report-'.$dailyReport->id.'.json';
        Storage::disk('local')->put($relativePath, json_encode($this->document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $absolutePath = Storage::disk('local')->path($relativePath);

        $this->terminal('[IMPORT] --- Summary by doctor ---');

        foreach ($this->document['doctor_totals'] as $doctorCode => $totals) {
            $line = sprintf(
                '[IMPORT] %s: %d rows | paid=%s AED | job=%s AED | skipped=%d | issues=%d',
                $doctorCode,
                $totals['day_count'],
                $totals['paid_total_aed'],
                $totals['lab_total_aed'],
                $totals['skipped_rows_on_sheet'],
                $totals['issue_count'] ?? 0,
            );
            $this->terminal($line);

            Log::channel('import')->info('Extraction summary by doctor.', [
                'report_id' => $dailyReport->id,
                'doctor_code' => $doctorCode,
                'day_count' => $totals['day_count'],
                'paid_total_aed' => $totals['paid_total_aed'],
                'lab_total_aed' => $totals['lab_total_aed'],
                'skipped_rows' => $totals['skipped_rows_on_sheet'],
            ]);
        }

        $summary = $this->document['issue_summary'];
        $this->terminal(sprintf(
            '[IMPORT] --- Issues: %d errors, %d warnings, %d info ---',
            $summary['error'] ?? 0,
            $summary['warning'] ?? 0,
            $summary['info'] ?? 0,
        ));

        $this->terminal('[IMPORT] --- Done ---');
        $this->terminal('[IMPORT] JSON log: '.$absolutePath);
        $this->terminal('[IMPORT] Web UI: /logs/extraction/'.$dailyReport->id);

        return $absolutePath;
    }

    /**
     * Resolve the on-disk path for a report's extraction log, if it exists.
     *
     * @param  DailyReport  $dailyReport  Report to look up.
     * @return string|null Absolute path, or null when no log file has been written.
     */
    public function getLogPath(DailyReport $dailyReport): ?string
    {
        $relativePath = 'import-extractions/report-'.$dailyReport->id.'.json';

        if (! Storage::disk('local')->exists($relativePath)) {
            return null;
        }

        return Storage::disk('local')->path($relativePath);
    }

    /**
     * Load a previously saved extraction log document from disk.
     *
     * @param  DailyReport  $dailyReport  Report whose log to read.
     * @return array<string, mixed>|null Decoded JSON document, or null if missing/invalid.
     */
    public function loadForReport(DailyReport $dailyReport): ?array
    {
        $relativePath = 'import-extractions/report-'.$dailyReport->id.'.json';

        if (! Storage::disk('local')->exists($relativePath)) {
            return null;
        }

        $decoded = json_decode(Storage::disk('local')->get($relativePath), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Print a detailed terminal block for a fully calculated imported row.
     *
     * Shows payments, treatment text, JOB lines, flags, and per-row issues.
     *
     * @param  array<string, mixed>  $row  Enriched imported-row entry from the log document.
     */
    private function terminalImportedRow(array $row): void
    {
        $diag = is_array($row['diagnostics'] ?? null) ? $row['diagnostics'] : null;
        $flags = $row['flags'] ?? [];

        $this->terminal(sprintf(
            '[IMPORT] === %s (%s) | Tag %s | Excel-Zeile %s ===',
            $row['doctor_code'] ?? '?',
            $row['doctor_label'] ?? '?',
            $row['sheet_day'] ?? '?',
            $row['excel_row'] ?? '?',
        ));

        $paymentOk = $diag === null || ($diag['payments']['payment_ok'] ?? true);
        $this->terminal(sprintf(
            '  ZAHLUNG  DHS=%s | USD=%s (→%s AED) | VISA=%s | TOTAL=%s%s',
            $row['dhs_aed'] ?? '0.00',
            $row['usd'] ?? '0.00',
            $row['usd_to_aed'] ?? '0.00',
            $row['visa_aed'] ?? '0.00',
            $row['paid_total_aed'] ?? '0.00',
            $paymentOk ? '' : ' ⚠ TOTAL stimmt nicht',
        ));

        $this->terminal('  TEXT     '.($row['treatment_text'] ?? '-'));

        if ($diag !== null && ($diag['job']['lines'] ?? []) !== []) {
            foreach ($diag['job']['lines'] as $line) {
                $this->terminal(sprintf(
                    '  JOB      %s ×%d @ %s AED = %s AED → Income Spalte %s',
                    $line['code'],
                    $line['quantity'],
                    $line['unit_cost_aed'],
                    $line['line_total_aed'],
                    $line['export_column'] ?? '—',
                ));
            }
            $this->terminal(sprintf('  JOB SUM  %s AED', $diag['job']['total_aed'] ?? '0.00'));
        } else {
            $this->terminal('  JOB      — (kein Lab-JOB für Income H–P)');
        }

        if ($diag !== null && ($diag['treatments_ignored'] ?? []) !== []) {
            $parts = [];
            foreach ($diag['treatments_ignored'] as $t) {
                $parts[] = ($t['code'] ?? '?').'×'.($t['quantity'] ?? 0);
            }
            $this->terminal('  IGNORED  '.implode(', ', $parts).' (SxP/CF/RCT — kein JOB)');
        }

        if ($flags !== []) {
            $this->terminal('  FLAGS    '.implode(', ', $flags));
        }

        foreach ($row['issues'] ?? [] as $issue) {
            $this->terminalIssue($issue, 'ROW');
        }
    }

    /**
     * Print a single issue line to the import terminal.
     *
     * @param  array<string, mixed>  $issue  Issue with severity and message.
     * @param  string  $prefix  Label prefix (e.g. `ROW`, `RECON`).
     */
    private function terminalIssue(array $issue, string $prefix): void
    {
        $this->terminal(sprintf(
            '[%s %s] %s',
            $prefix,
            strtoupper((string) ($issue['severity'] ?? 'warning')),
            $issue['message'] ?? ($issue['code'] ?? 'issue'),
        ));
    }

    /**
     * Count errors, warnings, and info items across all log sections.
     *
     * @return array{error: int, warning: int, info: int}
     */
    private function buildIssueSummary(): array
    {
        $summary = ['error' => 0, 'warning' => 0, 'info' => 0];

        foreach ($this->document['imported_rows'] as $row) {
            foreach ($row['issues'] ?? [] as $issue) {
                if (in_array($issue['code'] ?? '', ['lab_not_persisted', 'ignored_treatment_noted'], true)) {
                    continue;
                }

                $severity = (string) ($issue['severity'] ?? 'warning');
                if (array_key_exists($severity, $summary)) {
                    $summary[$severity]++;
                }
            }
        }

        $summary['error'] += count($this->document['unresolved_rows']);
        $summary['warning'] += count($this->document['skipped_rows']);

        foreach ($this->document['reconciliation_issues'] as $issue) {
            $severity = (string) ($issue['severity'] ?? 'warning');
            if (array_key_exists($severity, $summary)) {
                $summary[$severity]++;
            }
        }

        return $summary;
    }

    /**
     * Collapse whitespace and truncate treatment text for terminal display.
     *
     * @param  string  $text  Raw treatment text.
     * @param  int  $maxLength  Maximum character length before ellipsis.
     * @return string Truncated single-line text, or `-` when empty.
     */
    private function truncateTreatmentText(string $text, int $maxLength = 80): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ($text === '') {
            return '-';
        }

        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength - 3).'...';
        }

        return $text;
    }

    /**
     * Write a line to the import terminal log channel.
     *
     * @param  string  $message  Human-readable log line.
     */
    private function terminal(string $message): void
    {
        Log::channel('import_terminal')->info($message);
    }

    /**
     * Print a skipped-row summary line to the import terminal.
     *
     * @param  array<string, mixed>  $event  Parser skip event with reason and payment fields.
     */
    private function terminalSkippedRow(array $event): void
    {
        $this->terminal(sprintf(
            '[SKIP %s] %s | Tag %s | Excel-Zeile %s | DHS=%s USD=%s VISA=%s | G=%s',
            strtoupper((string) ($event['reason'] ?? 'unknown')),
            $event['doctor_label'] ?? '?',
            $event['sheet_day'] ?? '?',
            $event['excel_row'] ?? '?',
            $event['dhs_aed'] ?? '-',
            $event['usd'] ?? '-',
            $event['visa_aed'] ?? '-',
            $event['g_cell'] ?? ($event['treatment_text'] ?? ''),
        ));
    }

    /**
     * Find an imported-row entry by its composite sheet-day/doctor/Excel-row key.
     *
     * @param  string  $key  Key from {@see rowKey()}.
     * @return array<string, mixed>|null Matching row entry, or null if not found.
     */
    private function findImportedRow(string $key): ?array
    {
        foreach ($this->document['imported_rows'] as $row) {
            $existingKey = $this->rowKey(
                (int) ($row['sheet_day'] ?? 0),
                (string) ($row['doctor_label'] ?? $row['doctor_code'] ?? ''),
                (int) ($row['excel_row'] ?? 0),
            );

            if ($existingKey === $key) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Detect special-case flags from treatment text and column G content.
     *
     * @param  string|null  $treatmentText  Combined patient treatment text.
     * @param  mixed  $gCell  Raw value of Excel column G.
     * @return array<int, string> Flag labels (e.g. `transfer`, `cash`).
     */
    private function detectFlags(?string $treatmentText, mixed $gCell): array
    {
        $flags = [];
        $haystack = strtoupper(trim((string) $treatmentText.' '.(string) $gCell));

        if ($haystack === '') {
            return $flags;
        }

        if (str_contains($haystack, 'TRANSFER')) {
            $flags[] = 'transfer';
        }

        if (str_contains($haystack, 'PAID BAL') || str_contains($haystack, 'PAID BALANCE')) {
            $flags[] = 'paid_balance';
        }

        if (str_contains($haystack, 'CASH')) {
            $flags[] = 'cash';
        }

        if (str_contains($haystack, 'IN DR JACK ACC') || str_contains($haystack, 'TO DR JACK')) {
            $flags[] = 'internal_account';
        }

        return $flags;
    }

    /**
     * Build a stable lookup key for a sheet row within the extraction log.
     *
     * @param  int  $sheetDay  Day-of-month sheet number (1–31).
     * @param  string  $doctor  Doctor label or code from Excel.
     * @param  int  $excelRow  1-based Excel row index.
     * @return string Composite key `day|DOCTOR|row`.
     */
    private function rowKey(int $sheetDay, string $doctor, int $excelRow): string
    {
        return $sheetDay.'|'.strtoupper(trim($doctor)).'|'.$excelRow;
    }

    /**
     * Merge new data into an existing imported-row entry, if the key matches.
     *
     * @param  string  $key  Row key from {@see rowKey()}.
     * @param  array<string, mixed>  $data  Fields to merge into the existing entry.
     * @return bool True when an existing row was updated; false when no match was found.
     */
    private function upsertImportedRow(string $key, array $data): bool
    {
        foreach ($this->document['imported_rows'] as $index => $row) {
            $existingKey = $this->rowKey(
                (int) ($row['sheet_day'] ?? 0),
                (string) ($row['doctor_label'] ?? $row['doctor_code'] ?? ''),
                (int) ($row['excel_row'] ?? 0),
            );

            if ($existingKey !== $key) {
                continue;
            }

            $this->document['imported_rows'][$index] = array_merge($row, $data);

            return true;
        }

        return false;
    }

    /**
     * Aggregate per-doctor totals from imported, skipped, and unresolved rows.
     *
     * @return array<string, array<string, mixed>> Doctor code → totals (paid, job, counts).
     */
    private function buildDoctorTotals(): array
    {
        $totals = [];

        foreach ($this->document['imported_rows'] as $row) {
            $doctorCode = (string) ($row['doctor_code'] ?? 'UNKNOWN');

            if (! array_key_exists($doctorCode, $totals)) {
                $totals[$doctorCode] = [
                    'doctor_label' => $row['doctor_label'] ?? $doctorCode,
                    'day_count' => 0,
                    'paid_total_aed' => '0.00',
                    'lab_total_aed' => '0.00',
                    'skipped_rows_on_sheet' => 0,
                    'issue_count' => 0,
                ];
            }

            $totals[$doctorCode]['day_count']++;
            $totals[$doctorCode]['paid_total_aed'] = bcadd(
                $totals[$doctorCode]['paid_total_aed'],
                (string) ($row['paid_total_aed'] ?? '0'),
                2,
            );
            $totals[$doctorCode]['lab_total_aed'] = bcadd(
                $totals[$doctorCode]['lab_total_aed'],
                (string) ($row['lab_total_aed'] ?? '0'),
                2,
            );
            $totals[$doctorCode]['issue_count'] += count($row['issues'] ?? []);
        }

        foreach ($this->document['skipped_rows'] as $row) {
            $doctorCode = (string) ($row['doctor_code'] ?? $row['doctor_label'] ?? 'UNKNOWN');

            if (! array_key_exists($doctorCode, $totals)) {
                $totals[$doctorCode] = [
                    'doctor_label' => $row['doctor_label'] ?? $doctorCode,
                    'day_count' => 0,
                    'paid_total_aed' => '0.00',
                    'lab_total_aed' => '0.00',
                    'skipped_rows_on_sheet' => 0,
                ];
            }

            $totals[$doctorCode]['skipped_rows_on_sheet']++;
        }

        foreach ($this->document['unresolved_rows'] as $row) {
            $doctorCode = (string) ($row['doctor_label'] ?? 'UNKNOWN');

            if (! array_key_exists($doctorCode, $totals)) {
                $totals[$doctorCode] = [
                    'doctor_label' => $row['doctor_label'] ?? $doctorCode,
                    'day_count' => 0,
                    'paid_total_aed' => '0.00',
                    'lab_total_aed' => '0.00',
                    'skipped_rows_on_sheet' => 0,
                    'unresolved_rows' => 0,
                ];
            }

            $totals[$doctorCode]['unresolved_rows'] = ($totals[$doctorCode]['unresolved_rows'] ?? 0) + 1;
        }

        ksort($totals);

        return $totals;
    }
}
