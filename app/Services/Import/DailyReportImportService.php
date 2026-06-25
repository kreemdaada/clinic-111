<?php

namespace App\Services\Import;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\DailyReport;
use App\Models\DailyReportImportWarning;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Support\ReportMonthResolver;
use App\Services\Accounting\IncomeReconciliationService;
use App\Services\Accounting\LabJobCalculationService;
use App\Services\Accounting\PaymentCalculationService;
use App\Services\Import\ImportActivityLogger;
use App\Support\DoctorLabelNormalizer;
use App\Support\ImportRowPrivacySanitizer;
use App\Support\MoneyCalculator;
use App\Support\PatientReferenceHasher;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Orchestrates the full daily-report import pipeline: parse Excel, persist rows,
 * calculate treatments/lab jobs, reconcile totals, and write extraction logs.
 */
class DailyReportImportService
{
    /**
     * @param  ExcelDailyReportParser  $excelParser  Parses uploaded Excel workbooks.
     * @param  PaymentCalculationService  $paymentCalculationService  Computes AED totals and payment rows.
     * @param  LabJobCalculationService  $labJobCalculationService  Calculates lab job costs.
     * @param  IncomeReconciliationService  $incomeReconciliationService  Validates totals before export.
     * @param  ImportActivityLogger  $importActivityLogger  Audit and file logging.
     * @param  ImportExtractionLogService  $importExtractionLogService  Structured extraction log writer.
     * @param  TreatmentImportValidationService  $treatmentImportValidationService  Validates treatments and emits warnings.
     * @param  PatientReferenceHasher  $patientReferenceHasher  HMAC hash for patient references.
     * @param  ImportRowPrivacySanitizer  $importRowPrivacySanitizer  Strips PII from raw_data_json.
     */
    public function __construct(
        private readonly ExcelDailyReportParser $excelParser,
        private readonly PaymentCalculationService $paymentCalculationService,
        private readonly LabJobCalculationService $labJobCalculationService,
        private readonly IncomeReconciliationService $incomeReconciliationService,
        private readonly ImportActivityLogger $importActivityLogger,
        private readonly ImportExtractionLogService $importExtractionLogService,
        private readonly TreatmentImportValidationService $treatmentImportValidationService,
        private readonly PatientReferenceHasher $patientReferenceHasher,
        private readonly ImportRowPrivacySanitizer $importRowPrivacySanitizer,
    ) {}

    /**
     * Import an uploaded Excel daily report end-to-end inside a database transaction.
     *
     * @param  UploadedFile  $uploadedFile  Excel file from the HTTP upload.
     * @return DailyReport Fresh report with work rows, payments, and work items loaded.
     *
     * @throws RuntimeException When an approved report already exists for the month.
     */
    public function import(UploadedFile $uploadedFile): DailyReport
    {
        $monthAnchor = ReportMonthResolver::requireFromFilename($uploadedFile->getClientOriginalName());
        $resolvedReportDate = $monthAnchor->toDateString();

        if (DailyReport::query()
            ->where('report_date', $resolvedReportDate)
            ->where('status', ReportStatus::Approved)
            ->exists()) {
            throw new RuntimeException('An approved report already exists for this month.');
        }

        $storedPath = $this->storeUploadedFile($uploadedFile);

        $dailyReport = DB::transaction(function () use ($uploadedFile, $storedPath, $monthAnchor, $resolvedReportDate) {

            $dailyReport = DailyReport::query()->create([
                'report_date' => $resolvedReportDate,
                'source_type' => ReportSourceType::ExcelUpload,
                'source_file_name' => $uploadedFile->getClientOriginalName(),
                'status' => ReportStatus::Uploaded,
            ]);

            try {
                $storedAbsolutePath = Storage::path($storedPath);
                $parseResult = $this->excelParser->parseWithDiagnostics($storedAbsolutePath, $monthAnchor);
                $parsedRows = $parseResult['rows'];

                $this->importExtractionLogService->startReport($dailyReport, $storedAbsolutePath);
                $this->importExtractionLogService->recordParserEvents($parseResult['events']);

                foreach ($parsedRows as $parsedRow) {
                    $doctor = $this->resolveDoctorFromRow($parsedRow);

                    if ($doctor === null) {
                        $this->importExtractionLogService->recordUnresolvedDoctorRow($parsedRow);

                        continue;
                    }

                    $workRow = $this->createWorkRowFromParsedData($dailyReport, $parsedRow, $monthAnchor, $doctor);
                    $this->importExtractionLogService->recordPersistedRow($workRow, $parsedRow);
                }

                $dailyReport->update(['status' => ReportStatus::Parsed]);

                $this->processParsedReport($dailyReport);

                $extractionLogPath = $this->importExtractionLogService->finalize($dailyReport);

                $rowCount = $dailyReport->dailyWorkRows()->count();

                $this->importActivityLogger->logSuccess($dailyReport, $rowCount, $extractionLogPath);

                return $dailyReport->fresh([
                    'dailyWorkRows.doctor',
                    'dailyWorkRows.payments',
                    'dailyWorkRows.workItems.treatment',
                    'dailyWorkRows.workItems.labJob',
                    'importWarnings',
                ]);
            } catch (\Throwable $exception) {
                $dailyReport->update(['status' => ReportStatus::Failed]);

                $this->importActivityLogger->logFailure(
                    $uploadedFile->getClientOriginalName(),
                    $exception->getMessage(),
                );

                throw $exception;
            }
        });

        $this->deleteUploadedFileIfConfigured($storedPath);

        return $dailyReport;
    }

    /**
     * Run treatment parsing, lab calculation, and reconciliation on a parsed report.
     *
     * @param  DailyReport  $dailyReport  Report in Uploaded or Parsed status.
     *
     * @throws RuntimeException When the report is already approved.
     */
    public function processParsedReport(DailyReport $dailyReport): void
    {
        if ($dailyReport->isApproved()) {
            throw new RuntimeException('Approved reports are read-only.');
        }

        $dailyReport->load('dailyWorkRows.doctor');

        $allWarnings = [];

        foreach ($dailyReport->dailyWorkRows as $dailyWorkRow) {
            $result = $this->treatmentImportValidationService->validateAndPersist($dailyWorkRow);
            $allWarnings = array_merge($allWarnings, $result->warnings);
        }

        $dailyReport->update(['status' => ReportStatus::Parsed]);

        $this->labJobCalculationService->calculateForReport($dailyReport);

        $dailyReport->load('dailyWorkRows.doctor');

        foreach ($dailyReport->dailyWorkRows as $dailyWorkRow) {
            $dailyWorkRow->load(['workItems.treatment', 'workItems.labJob']);
            $allWarnings = array_merge(
                $allWarnings,
                $this->treatmentImportValidationService->collectLabPriceWarnings($dailyWorkRow),
            );
            $this->importExtractionLogService->recordCalculatedRow($dailyWorkRow);
        }

        $this->persistImportWarnings($dailyReport, $allWarnings);

        $reconciliationIssues = $this->incomeReconciliationService->validateReport($dailyReport);

        $this->importExtractionLogService->recordReconciliationIssues($reconciliationIssues);

        if ($reconciliationIssues !== []) {
            $this->importActivityLogger->logReconciliation($dailyReport, $reconciliationIssues);
        }

        $dailyReport->update([
            'status' => $allWarnings !== [] ? ReportStatus::NeedsReview : ReportStatus::Calculated,
        ]);
    }

    /**
     * Create a DailyWorkRow and associated Payment records from a parsed Excel row.
     *
     * @param  DailyReport  $dailyReport  Parent report.
     * @param  array<string, mixed>  $parsedRow  Row dictionary from the Excel parser.
     * @param  Carbon  $monthAnchor  Month anchor for work-date resolution.
     * @param  Doctor  $doctor  Resolved doctor for this row.
     * @return DailyWorkRow Newly created work row with payments.
     */
    private function createWorkRowFromParsedData(DailyReport $dailyReport, array $parsedRow, Carbon $monthAnchor, Doctor $doctor): DailyWorkRow
    {
        $dhsAmount = $this->toDecimalString($this->getParsedRowValue($parsedRow, 'dhs_amount', 0));
        $chequeAmount = $this->toDecimalString($this->getParsedRowValue($parsedRow, 'cheque_amount', 0));
        $tabbyAmount = $this->toDecimalString($this->getParsedRowValue($parsedRow, 'tabby_amount', 0));
        $usdAmount = $this->toDecimalString($this->getParsedRowValue($parsedRow, 'usd_amount', 0));
        $visaAmount = $this->toDecimalString($this->getParsedRowValue($parsedRow, 'visa_amount', 0));
        $rublAmount = $this->toDecimalString($this->getParsedRowValue($parsedRow, 'rubl_amount', 0));

        $paymentTotals = $this->paymentCalculationService->calculateTotalCollectedAed(
            $dhsAmount,
            $usdAmount,
            $visaAmount,
            rublAmount: $rublAmount,
            chequeAmount: $chequeAmount,
            tabbyAmount: $tabbyAmount,
        );

        if (bccomp($rublAmount, '0', 2) > 0) {
            $dhsAmount = MoneyCalculator::add($dhsAmount, $paymentTotals['rubl_to_aed_amount']);
        }

        $patientName = $this->sanitizeString($this->getParsedRowValue($parsedRow, 'patient_name'));
        $mrn = $this->sanitizeString($this->getParsedRowValue($parsedRow, 'mrn'));
        $fileNumber = $this->sanitizeString($this->getParsedRowValue($parsedRow, 'file_number'));
        $excelRowNumber = (int) ($parsedRow['raw_row_number'] ?? $parsedRow['excel_row'] ?? 0);

        $dailyWorkRow = DailyWorkRow::query()->create([
            'daily_report_id' => $dailyReport->id,
            'doctor_id' => $doctor->id,
            'work_date' => $this->parseWorkDate(
                $this->getParsedRowValue($parsedRow, 'work_date'),
                $monthAnchor,
                $this->getParsedRowValue($parsedRow, 'sheet_day'),
            ),
            'patient_reference_hash' => $this->patientReferenceHasher->hash($patientName, $mrn, $fileNumber),
            'excel_row_number' => $excelRowNumber > 0 ? $excelRowNumber : null,
            'treatment_text' => $this->sanitizeString($this->getParsedRowValue($parsedRow, 'treatment_text')),
            'total_cost' => $this->toDecimalString($this->getParsedRowValue($parsedRow, 'total_cost', 0)),
            'discount_amount' => $this->toDecimalString($this->getParsedRowValue($parsedRow, 'discount_amount', 0)),
            'dhs_amount' => $dhsAmount,
            'cheque_amount' => $chequeAmount,
            'tabby_amount' => $tabbyAmount,
            'usd_amount' => $usdAmount,
            'usd_to_aed_amount' => $paymentTotals['usd_to_aed_amount'],
            'visa_amount' => $visaAmount,
            'paid_total_aed' => $paymentTotals['paid_total_aed'],
            'balance_dhs' => $this->toDecimalString($this->getParsedRowValue($parsedRow, 'balance_dhs', 0)),
            'balance_usd' => $this->toDecimalString($this->getParsedRowValue($parsedRow, 'balance_usd', 0)),
            'crown_count' => (int) $this->getParsedRowValue($parsedRow, 'crown_count', 0),
            'raw_data_json' => $this->importRowPrivacySanitizer->sanitize($parsedRow),
        ]);

        $this->paymentCalculationService->createPaymentsForWorkRow($dailyWorkRow);

        return $dailyWorkRow;
    }

    /**
     * Match a parsed row's doctor label to an active Doctor record.
     *
     * @param  array<string, mixed>  $parsedRow  Row with a `doctor` field.
     * @return Doctor|null Matched doctor, or null when no match is found.
     */
    private function resolveDoctorFromRow(array $parsedRow): ?Doctor
    {
        $doctorRawValue = $this->getParsedRowValue($parsedRow, 'doctor', '');
        $doctorValue = strtoupper(trim((string) $doctorRawValue));

        if ($doctorValue === '') {
            return null;
        }

        $doctorCodeGuess = DoctorLabelNormalizer::extractCodeGuess($doctorValue);

        $doctor = Doctor::query()
            ->where('is_active', true)
            ->where(function ($query) use ($doctorValue, $doctorCodeGuess) {
                $query
                    ->where('code', $doctorValue)
                    ->orWhere('code', $doctorCodeGuess)
                    ->orWhereRaw('UPPER(name) = ?', [$doctorValue])
                    ->orWhereRaw('UPPER(name) LIKE ?', ['%'.$doctorValue.'%']);

                if ($doctorCodeGuess !== $doctorValue) {
                    $query->orWhereRaw('UPPER(name) LIKE ?', ['%'.$doctorCodeGuess.'%']);
                }
            })
            ->first();

        return $doctor;
    }

    /**
     * Store the uploaded file on the configured accounting upload disk.
     *
     * @param  UploadedFile  $uploadedFile  File from the HTTP request.
     * @return string Stored relative path on the disk.
     */
    private function storeUploadedFile(UploadedFile $uploadedFile): string
    {
        $disk = config('accounting.upload.disk');
        $directory = config('accounting.upload.directory');

        return $uploadedFile->store($directory, $disk);
    }

    /**
     * Resolve a work date from sheet day, cell value, or month anchor fallback.
     *
     * @param  mixed  $value  Raw date cell value from Excel.
     * @param  Carbon|string  $monthAnchor  Target import month.
     * @param  mixed  $sheetDay  Day-of-month from sheet tab name (1–31).
     * @return string ISO date string (Y-m-d).
     */
    private function parseWorkDate(mixed $value, Carbon|string $monthAnchor, mixed $sheetDay = null): string
    {
        $monthStart = Carbon::parse($monthAnchor)->startOfMonth();

        if ($sheetDay !== null && is_numeric($sheetDay)) {
            $day = (int) $sheetDay;

            if ($day >= 1 && $day <= (int) $monthStart->daysInMonth) {
                return $monthStart->copy()->day($day)->toDateString();
            }
        }

        if ($value !== null && $value !== '') {
            try {
                if (is_numeric($value)) {
                    $excelDate = Carbon::createFromTimestampUTC((int) (($value - 25569) * 86400));

                    if ($excelDate->year === (int) $monthStart->year && $excelDate->month === (int) $monthStart->month) {
                        return $excelDate->toDateString();
                    }
                } else {
                    $parsed = Carbon::parse((string) $value);

                    if ($parsed->year === (int) $monthStart->year && $parsed->month === (int) $monthStart->month) {
                        return $parsed->toDateString();
                    }
                }
            } catch (\Throwable) {
                // Fall through to month anchor fallback.
            }
        }

        return $monthStart->toDateString();
    }

    /**
     * Normalize a cell value to a two-decimal string for database storage.
     *
     * @param  mixed  $value  Raw numeric cell value.
     * @return string Amount with 2 decimal places (`0.00` when empty).
     */
    private function toDecimalString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        $normalized = preg_replace('/[^\d.\-]/', '', (string) $value);

        return number_format((float) $normalized, 2, '.', '');
    }

    /**
     * Trim and strip HTML tags from a string cell value.
     *
     * @param  mixed  $value  Raw cell value.
     * @return string|null Sanitized string, or null when empty.
     */
    private function sanitizeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim(strip_tags((string) $value));
    }

    /**
     * Read a field from a parsed row with a default when missing or null.
     *
     * @param  array<string, mixed>  $parsedRow  Parsed Excel row dictionary.
     * @param  string  $key  Field name to read.
     * @param  mixed  $default  Value returned when the key is absent or null.
     * @return mixed Field value or default.
     */
    private function getParsedRowValue(array $parsedRow, string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, $parsedRow) || $parsedRow[$key] === null) {
            return $default;
        }

        return $parsedRow[$key];
    }

    /**
     * @param  array<int, \App\DTOs\ImportParseWarningDto>  $warnings
     */
    private function persistImportWarnings(DailyReport $dailyReport, array $warnings): void
    {
        DailyReportImportWarning::query()
            ->where('daily_report_id', $dailyReport->id)
            ->delete();

        if ($warnings === []) {
            return;
        }

        $dailyReport->load('dailyWorkRows');

        $rowsByExcelRow = $dailyReport->dailyWorkRows->keyBy(
            fn (DailyWorkRow $row) => (int) ($row->excel_row_number ?? 0),
        );

        foreach ($warnings as $warning) {
            $workRow = $rowsByExcelRow->get($warning->excelRow);

            DailyReportImportWarning::query()->create([
                'daily_report_id' => $dailyReport->id,
                'daily_work_row_id' => $workRow?->id,
                'excel_row_number' => $warning->excelRow > 0 ? $warning->excelRow : null,
                'doctor_code' => $warning->doctor,
                'treatment_text' => $warning->treatmentText,
                'warning_code' => $warning->warningCode,
                'message' => $warning->message,
            ]);
        }
    }

    /**
     * Delete the stored upload when configured (default: true after successful import).
     */
    private function deleteUploadedFileIfConfigured(string $storedPath): void
    {
        if (! config('accounting.upload.delete_after_import', true)) {
            return;
        }

        $disk = config('accounting.upload.disk');

        if (Storage::disk($disk)->exists($storedPath)) {
            Storage::disk($disk)->delete($storedPath);
        }
    }
}
