<?php

namespace App\Services\Import;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Services\Accounting\IncomeReconciliationService;
use App\Services\Accounting\LabJobCalculationService;
use App\Services\Accounting\PaymentCalculationService;
use App\Services\Accounting\TreatmentParserService;
use App\Services\Import\ImportActivityLogger;
use App\Support\DoctorLabelNormalizer;
use App\Support\ReportMonthResolver;
use App\Support\MoneyCalculator;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DailyReportImportService
{
    public function __construct(
        private readonly ExcelDailyReportParser $excelParser,
        private readonly PaymentCalculationService $paymentCalculationService,
        private readonly TreatmentParserService $treatmentParserService,
        private readonly LabJobCalculationService $labJobCalculationService,
        private readonly IncomeReconciliationService $incomeReconciliationService,
        private readonly ImportActivityLogger $importActivityLogger,
        private readonly ImportExtractionLogService $importExtractionLogService,
    ) {}

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

        return DB::transaction(function () use ($uploadedFile, $storedPath, $monthAnchor, $resolvedReportDate) {

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
    }

    public function processParsedReport(DailyReport $dailyReport): void
    {
        if ($dailyReport->isApproved()) {
            throw new RuntimeException('Approved reports are read-only.');
        }

        $dailyReport->load('dailyWorkRows');

        foreach ($dailyReport->dailyWorkRows as $dailyWorkRow) {
            $this->treatmentParserService->parseAndPersist($dailyWorkRow);
        }

        $dailyReport->update(['status' => ReportStatus::Parsed]);

        $this->labJobCalculationService->calculateForReport($dailyReport);

        $dailyReport->load('dailyWorkRows.doctor');

        foreach ($dailyReport->dailyWorkRows as $dailyWorkRow) {
            $this->importExtractionLogService->recordCalculatedRow($dailyWorkRow);
        }

        $reconciliationIssues = $this->incomeReconciliationService->validateReport($dailyReport);

        $this->importExtractionLogService->recordReconciliationIssues($reconciliationIssues);

        if ($reconciliationIssues !== []) {
            $this->importActivityLogger->logReconciliation($dailyReport, $reconciliationIssues);
        }

        $dailyReport->update(['status' => ReportStatus::Calculated]);
    }

    /**
     * @param  array<string, mixed>  $parsedRow
     */
    private function createWorkRowFromParsedData(DailyReport $dailyReport, array $parsedRow, Carbon $monthAnchor, Doctor $doctor): DailyWorkRow
    {
        $dhsAmount = $this->toDecimalString($this->getParsedRowValue($parsedRow, 'dhs_amount', 0));
        $usdAmount = $this->toDecimalString($this->getParsedRowValue($parsedRow, 'usd_amount', 0));
        $visaAmount = $this->toDecimalString($this->getParsedRowValue($parsedRow, 'visa_amount', 0));
        $rublAmount = $this->toDecimalString($this->getParsedRowValue($parsedRow, 'rubl_amount', 0));

        $paymentTotals = $this->paymentCalculationService->calculateTotalCollectedAed(
            $dhsAmount,
            $usdAmount,
            $visaAmount,
            rublAmount: $rublAmount,
        );

        if (bccomp($rublAmount, '0', 2) > 0) {
            $dhsAmount = MoneyCalculator::add($dhsAmount, $paymentTotals['rubl_to_aed_amount']);
        }

        $dailyWorkRow = DailyWorkRow::query()->create([
            'daily_report_id' => $dailyReport->id,
            'doctor_id' => $doctor->id,
            'work_date' => $this->parseWorkDate(
                $this->getParsedRowValue($parsedRow, 'work_date'),
                $monthAnchor,
                $this->getParsedRowValue($parsedRow, 'sheet_day'),
            ),
            'patient_name' => $this->sanitizeString($this->getParsedRowValue($parsedRow, 'patient_name')),
            'mrn' => $this->sanitizeString($this->getParsedRowValue($parsedRow, 'mrn')),
            'file_number' => $this->sanitizeString($this->getParsedRowValue($parsedRow, 'file_number')),
            'treatment_text' => $this->sanitizeString($this->getParsedRowValue($parsedRow, 'treatment_text')),
            'total_cost' => $this->toDecimalString($this->getParsedRowValue($parsedRow, 'total_cost', 0)),
            'discount_amount' => $this->toDecimalString($this->getParsedRowValue($parsedRow, 'discount_amount', 0)),
            'dhs_amount' => $dhsAmount,
            'usd_amount' => $usdAmount,
            'usd_to_aed_amount' => $paymentTotals['usd_to_aed_amount'],
            'visa_amount' => $visaAmount,
            'paid_total_aed' => $paymentTotals['paid_total_aed'],
            'balance_dhs' => $this->toDecimalString($this->getParsedRowValue($parsedRow, 'balance_dhs', 0)),
            'balance_usd' => $this->toDecimalString($this->getParsedRowValue($parsedRow, 'balance_usd', 0)),
            'crown_count' => (int) $this->getParsedRowValue($parsedRow, 'crown_count', 0),
            'raw_data_json' => $parsedRow,
        ]);

        $this->paymentCalculationService->createPaymentsForWorkRow($dailyWorkRow);

        return $dailyWorkRow;
    }

    /**
     * @param  array<string, mixed>  $parsedRow
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

    private function storeUploadedFile(UploadedFile $uploadedFile): string
    {
        $disk = config('accounting.upload.disk');
        $directory = config('accounting.upload.directory');

        return $uploadedFile->store($directory, $disk);
    }

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

    private function toDecimalString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        $normalized = preg_replace('/[^\d.\-]/', '', (string) $value);

        return number_format((float) $normalized, 2, '.', '');
    }

    private function sanitizeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim(strip_tags((string) $value));
    }

    /**
     * @param  array<string, mixed>  $parsedRow
     */
    private function getParsedRowValue(array $parsedRow, string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, $parsedRow) || $parsedRow[$key] === null) {
            return $default;
        }

        return $parsedRow[$key];
    }
}
