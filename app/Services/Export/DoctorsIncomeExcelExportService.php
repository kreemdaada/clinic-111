<?php

namespace App\Services\Export;

use App\Enums\CommissionType;
use App\Exceptions\IncomeExportBlockedException;
use App\Models\Clinic;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Services\Accounting\Concerns\ScopesAccountingQueries;
use App\Services\Accounting\IncomeReconciliationService;
use App\Services\Accounting\LabJobCalculationService;
use App\Services\Accounting\PaymentCalculationService;
use App\Services\Accounting\WaelFixedFeeCalculator;
use App\Services\Configuration\CurrentClinicResolver;
use App\Support\ClinicCurrencySupport;
use App\Support\DoctorLabelNormalizer;
use App\Support\IncomeExportStandardLayout;
use App\Support\MoneyCalculator;
use App\Support\OpgTreatmentCodes;
use App\Support\ReportMonthResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Fills the Original Income Excel template (daily subtotals, JOB/lab costs, doctor sheets).
 *
 * Export layout per doctor comes from {@see DoctorIncomeExportProfileService} (database).
 * JOB amounts come from {@see LabJobCalculationService} + lab_prices.
 */
class DoctorsIncomeExcelExportService
{
    use ScopesAccountingQueries;

    private const TEMPLATE_PATH = 'templates/original_income_template.xlsx';

    /**
     * OPG / nurse commission columns appended after standard treatment count columns.
     *
     * @var array<string, string>
     */
    private const NURSE_COMMISSION_COLUMNS = [
        'opg_normal_value' => 'T',
        'opg_3d_value' => 'U',
        'nurse_commission' => 'V',
    ];

    /**
     * @param  IncomeReconciliationService  $incomeReconciliationService  Pre-export validation.
     * @param  DoctorIncomeExportProfileService  $exportProfileService  DB-driven sheet/column layout.
     * @param  string  $defaultUsdExchangeRate  USD→AED rate for Wael fixed-fee conversion.
     */
    public function __construct(
        private readonly IncomeReconciliationService $incomeReconciliationService,
        private readonly DoctorIncomeExportProfileService $exportProfileService,
        private readonly WaelFixedFeeCalculator $waelFixedFeeCalculator,
        private readonly CurrentClinicResolver $currentClinicResolver,
        private readonly PaymentCalculationService $paymentCalculationService,
        private readonly DoctorIncomeExportProfileProvisioner $exportProfileProvisioner,
        private readonly string $defaultUsdExchangeRate = '3.65',
    ) {}

    /**
     * Export the Original Income Excel for a single daily report's month.
     *
     * Blocks export when reconciliation reports errors.
     *
     * @param  DailyReport  $dailyReport  Parsed/calculated report to export.
     * @return string Absolute path to the saved `.xlsx` file.
     *
     * @throws RuntimeException When reconciliation fails or the template is missing.
     */
    public function exportForReport(DailyReport $dailyReport): string
    {
        $this->assertSameClinic($dailyReport);

        $reconciliationIssues = $this->incomeReconciliationService->validateReport($dailyReport);

        if ($this->incomeReconciliationService->hasErrors($reconciliationIssues)) {
            throw new IncomeExportBlockedException($reconciliationIssues);
        }

        $monthStart = ReportMonthResolver::parseFromFilename($dailyReport->source_file_name)
            ?? Carbon::parse($dailyReport->report_date)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        return $this->exportForMonth($monthStart, $monthEnd, $dailyReport);
    }

    /**
     * Fill the Original Income template for all active doctors in a month.
     *
     * @param  Carbon  $monthStart  First day of the target month.
     * @param  Carbon|null  $monthEnd  Last day of the month (defaults to month end).
     * @param  DailyReport|null  $dailyReport  When set, limits rows to this report only.
     * @return string Absolute path to the saved `.xlsx` file.
     *
     * @throws RuntimeException When the template file is not found.
     */
    public function exportForMonth(Carbon $monthStart, ?Carbon $monthEnd = null, ?DailyReport $dailyReport = null): string
    {
        if ($monthEnd === null) {
            $monthEnd = $monthStart->copy()->endOfMonth();
        }

        $templatePath = resource_path(self::TEMPLATE_PATH);

        if (! is_file($templatePath)) {
            throw new RuntimeException('Income Excel template not found at '.self::TEMPLATE_PATH);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $clinic = $this->currentClinicResolver->resolve();
        $clinicCurrency = ClinicCurrencySupport::baseCurrency($clinic);

        $workRowsQuery = $this->forCurrentClinic(DailyWorkRow::class)
            ->with(['doctor', 'workItems.treatment', 'workItems.labJob', 'workItems.nurseCommission']);

        if ($dailyReport !== null) {
            $this->assertSameClinic($dailyReport);
            $workRowsQuery->where('daily_report_id', $dailyReport->id);
        } else {
            $workRowsQuery->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
        }

        $workRows = $workRowsQuery->get();

        $rowsByDoctor = $workRows->groupBy('doctor_id');
        $exportedSheetNames = [];

        foreach ($this->forCurrentClinic(Doctor::class)->where('is_active', true)->get() as $doctor) {
            $doctorRows = $rowsByDoctor->get($doctor->id, collect());

            if ($doctorRows->isEmpty()) {
                continue;
            }

            $profile = $this->resolveExportProfile($doctor);

            if ($profile === null) {
                $this->exportProfileProvisioner->ensureForDoctor($doctor);
                $this->exportProfileService->forgetCachedProfiles();
                $profile = $this->resolveExportProfile($doctor);
            }

            if ($profile === null) {
                continue;
            }

            $sheetName = $profile['sheet_name'];
            $sheet = $this->ensureDoctorSheet($spreadsheet, $sheetName);

            if ($profile['layout'] === 'wael') {
                $this->fillWaelSheet($sheet, $doctor, $doctorRows, $monthStart, $monthEnd);
            } else {
                $this->fillStandardDoctorSheet(
                    $sheet,
                    $profile,
                    $doctor,
                    $doctorRows,
                    $monthStart,
                    $monthEnd,
                    $clinic,
                );
            }

            $exportedSheetNames[] = $sheetName;
        }

        $this->removeSheetsExcept($spreadsheet, $exportedSheetNames);

        $fileName = $this->buildFileName($monthStart);
        $relativePath = 'exports/'.$fileName;
        $absolutePath = Storage::path($relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($absolutePath);

        return $absolutePath;
    }

    /**
     * Generate an export and return an HTTP download response.
     *
     * @param  DailyReport  $dailyReport  Report to export.
     * @return BinaryFileResponse Spreadsheet download response.
     *
     * @throws RuntimeException When export is blocked by reconciliation errors.
     */
    public function downloadResponse(DailyReport $dailyReport): BinaryFileResponse
    {
        $absolutePath = $this->exportForReport($dailyReport);
        $downloadName = $this->downloadFileName($dailyReport);

        return response()->download($absolutePath, $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(false);
    }

    /**
     * Human-readable filename for the Server Income Excel download.
     */
    public function downloadFileName(DailyReport $dailyReport): string
    {
        $monthStart = ReportMonthResolver::parseFromFilename($dailyReport->source_file_name)
            ?? Carbon::parse($dailyReport->report_date)->startOfMonth();

        return $this->buildFileName($monthStart);
    }

    /**
     * Fill a standard-layout doctor sheet with daily payments, JOB totals, and treatment counts.
     *
     * @param  Worksheet  $sheet  Target worksheet tab.
     * @param  array<string, mixed>  $profile  Export profile from {@see DOCTOR_EXPORT_PROFILES}.
     * @param  Doctor  $doctor  Doctor whose data to write.
     * @param  Collection<int, DailyWorkRow>  $doctorRows  Work rows for this doctor.
     * @param  Carbon  $monthStart  First day of the export month.
     * @param  Carbon  $monthEnd  Last day of the export month.
     */
    private function fillStandardDoctorSheet(
        Worksheet $sheet,
        array $profile,
        Doctor $doctor,
        Collection $doctorRows,
        Carbon $monthStart,
        Carbon $monthEnd,
        Clinic $clinic,
    ): void {
        $clinicCurrency = ClinicCurrencySupport::baseCurrency($clinic);
        $daysInMonth = (int) $monthStart->daysInMonth;
        $firstDayRow = (int) $profile['first_day_row'];
        $lastDayRow = $firstDayRow + $daysInMonth - 1;
        $totalRow = $lastDayRow + 1;
        $clearToColumn = $this->resolveClearToColumn($profile);

        $this->clearDataArea($sheet, $firstDayRow, $totalRow + 12, $clearToColumn);
        $this->writeStandardHeaders($sheet, $profile, $clinic);

        $paymentsOnly = ($profile['treatment_columns'] ?? []) === [];
        /** @var array<string, string> $treatmentColumns */
        $treatmentColumns = $profile['treatment_columns'] ?? [];
        $dailyData = $this->aggregateStandardDailyData(
            $doctorRows,
            $monthStart,
            $clinic,
            $paymentsOnly,
            array_keys($treatmentColumns),
        );

        if ($this->shouldRemapJackTreatmentCounts($doctor)) {
            foreach ($dailyData as $dateKey => $dayData) {
                $dailyData[$dateKey]['treatments'] = $this->remapJackIncomeTreatmentCounts($dayData['treatments']);
            }
        }
        /** @var array<string, string> $paymentColumns */
        $paymentColumns = $profile['payment_columns'];

        $columnTotals = [
            'dhs' => '0.00',
            'cheque' => '0.00',
            'tabby' => '0.00',
            'usd' => '0.00',
            'usd_to_aed' => '0.00',
            'visa' => '0.00',
            'total' => '0.00',
        ];

        /** @var array<string, int> $treatmentTotals */
        $treatmentTotals = [];
        $labCostTotal = '0.00';
        $opgNormalTotal = '0.00';
        $opg3dTotal = '0.00';
        $nurseCommissionTotal = '0.00';

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $row = $firstDayRow + $day - 1;
            $date = $monthStart->copy()->day($day);
            $dateKey = $date->toDateString();

            $sheet->setCellValue('A'.$row, Date::PHPToExcel($date));

            if (! array_key_exists($dateKey, $dailyData)) {
                $this->writeStandardPaymentCells($sheet, $paymentColumns, $row, null);

                continue;
            }

            $dayData = $dailyData[$dateKey];

            $this->writeStandardPaymentCells($sheet, $paymentColumns, $row, $dayData);

            if (! $paymentsOnly) {
                $this->setNumericCell($sheet, $paymentColumns['job'].$row, $dayData['job']);
                $labCostTotal = MoneyCalculator::add($labCostTotal, $dayData['job']);

                foreach ($dayData['treatments'] as $code => $quantity) {
                    if (! array_key_exists($code, $treatmentColumns)) {
                        continue;
                    }

                    $column = $treatmentColumns[$code];
                    $sheet->setCellValue($column.$row, $quantity);

                    if (! array_key_exists($code, $treatmentTotals)) {
                        $treatmentTotals[$code] = 0;
                    }

                    $treatmentTotals[$code] += $quantity;
                }

                $this->writeNurseCommissionColumns($sheet, $row, $dayData);
                $opgNormalTotal = MoneyCalculator::add($opgNormalTotal, $dayData['opg_normal_value'] ?? '0.00');
                $opg3dTotal = MoneyCalculator::add($opg3dTotal, $dayData['opg_3d_value'] ?? '0.00');
                $nurseCommissionTotal = MoneyCalculator::add($nurseCommissionTotal, $dayData['nurse_commission'] ?? '0.00');
            }

            foreach ($columnTotals as $key => $value) {
                $columnTotals[$key] = MoneyCalculator::add($value, $dayData[$key]);
            }
        }

        $this->applyDayColumnDateFormat($sheet, $firstDayRow, $lastDayRow);

        $sheet->setCellValue('A'.$totalRow, 'TOTAL');
        $this->writeStandardPaymentCells($sheet, $paymentColumns, $totalRow, $columnTotals);

        if (! $paymentsOnly) {
            $this->setNumericCell($sheet, $paymentColumns['job'].$totalRow, $labCostTotal);

            foreach ($treatmentTotals as $code => $quantity) {
                if (! array_key_exists($code, $treatmentColumns)) {
                    continue;
                }

                $sheet->setCellValue($treatmentColumns[$code].$totalRow, $quantity);
            }

            $this->writeNurseCommissionColumns($sheet, $totalRow, [
                'opg_normal_value' => $opgNormalTotal,
                'opg_3d_value' => $opg3dTotal,
                'nurse_commission' => $nurseCommissionTotal,
            ]);
        }

        $summaryStart = $totalRow + 2;
        $this->writeOriginalIncomeSummaryBlock(
            $sheet,
            $profile,
            $summaryStart,
            $columnTotals,
            $labCostTotal,
            $doctor,
            $clinic,
        );
    }

    /**
     * Writes the bottom summary block (cash, card, TOTAL, LAB COST-, commission).
     *
     * @param  array<string, mixed>  $profile
     * @param  array{dhs: string, usd: string, usd_to_aed: string, visa: string, total: string}  $columnTotals
     */
    private function writeOriginalIncomeSummaryBlock(
        Worksheet $sheet,
        array $profile,
        int $summaryStart,
        array $columnTotals,
        string $labCostTotal,
        Doctor $doctor,
        Clinic $clinic,
    ): void {
        $clinicCurrency = ClinicCurrencySupport::baseCurrency($clinic);
        $isLegacyAed = ClinicCurrencySupport::usesLegacyPaymentLayout($clinic);
        $foreignCurrency = ClinicCurrencySupport::foreignCashCurrency($clinicCurrency);
        $primaryLabel = $isLegacyAed ? 'DHS' : $clinicCurrency;
        $foreignLabel = $isLegacyAed
            ? 'USD'
            : ($foreignCurrency ?? 'FOREIGN');

        $summaryRows = [
            ['label' => $primaryLabel, 'value' => $columnTotals['dhs']],
            ['label' => $foreignLabel, 'value' => $columnTotals['usd_to_aed']],
            ['label' => 'VISA', 'value' => $columnTotals['visa']],
            ['label' => 'INURANCE', 'value' => null],
            ['label' => 'TOTAL', 'value' => $columnTotals['total']],
            ['label' => 'LAB COST-', 'value' => $labCostTotal],
        ];

        foreach ($summaryRows as $index => $summaryRow) {
            $rowNumber = $summaryStart + $index;
            $sheet->setCellValue('A'.$rowNumber, $summaryRow['label']);

            if ($summaryRow['value'] === null) {
                $sheet->setCellValue('B'.$rowNumber, null);

                continue;
            }

            $this->setNumericCell($sheet, 'B'.$rowNumber, $summaryRow['value']);
        }

        if ($doctor->commission_type !== CommissionType::Percentage) {
            return;
        }

        $commissionPercentage = '0';
        if ($doctor->commission_percentage !== null) {
            $commissionPercentage = (string) $doctor->commission_percentage;
        }

        $commissionLabel = $this->formatCommissionLabel($commissionPercentage);
        $netTotal = MoneyCalculator::subtract($columnTotals['total'], $labCostTotal);
        $doctorIncome = MoneyCalculator::percentage($netTotal, $commissionPercentage);

        if ($profile['summary_shows_net_total']) {
            $sheet->setCellValue('A'.($summaryStart + 6), 'NET TOTAL');
            $this->setNumericCell($sheet, 'B'.($summaryStart + 6), $netTotal);
            $sheet->setCellValue('A'.($summaryStart + 7), $commissionLabel);
            $this->setNumericCell($sheet, 'B'.($summaryStart + 7), $doctorIncome);

            return;
        }

        $sheet->setCellValue('A'.($summaryStart + 7), $commissionLabel);
        $this->setNumericCell($sheet, 'B'.($summaryStart + 7), $doctorIncome);
    }

    /**
     * @param  array<string, string>  $paymentColumns
     * @param  array<string, mixed>|null  $amounts
     */
    private function writeStandardPaymentCells(
        Worksheet $sheet,
        array $paymentColumns,
        int $row,
        ?array $amounts,
    ): void {
        $defaults = [
            'dhs' => '0.00',
            'cheque' => '0.00',
            'tabby' => '0.00',
            'usd' => '0.00',
            'usd_to_aed' => '0.00',
            'visa' => '0.00',
            'total' => '0.00',
            'job' => '0.00',
        ];

        foreach (['dhs', 'cheque', 'tabby', 'usd', 'usd_to_aed', 'visa', 'total', 'job'] as $key) {
            if (! array_key_exists($key, $paymentColumns)) {
                continue;
            }

            $value = $amounts[$key] ?? $defaults[$key];
            $this->setNumericCell($sheet, $paymentColumns[$key].$row, $value);
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function resolveClearToColumn(array $profile): string
    {
        /** @var array<string, string> $treatmentColumns */
        $treatmentColumns = $profile['treatment_columns'] ?? [];

        if ($treatmentColumns === []) {
            return 'T';
        }

        $maxIndex = 0;

        foreach ($treatmentColumns as $column) {
            $maxIndex = max($maxIndex, Coordinate::columnIndexFromString($column));
        }

        foreach (self::NURSE_COMMISSION_COLUMNS as $column) {
            $maxIndex = max($maxIndex, Coordinate::columnIndexFromString($column));
        }

        return Coordinate::stringFromColumnIndex($maxIndex);
    }

    private function formatCommissionLabel(string $commissionPercentage): string
    {
        if (bccomp($commissionPercentage, '0', 2) <= 0) {
            return '0%';
        }

        $normalized = rtrim(rtrim($commissionPercentage, '0'), '.');

        return $normalized.'%';
    }

    /**
     * Fill the Wael-specific sheet layout with payments and fixed-fee surgery columns.
     *
     * @param  Worksheet  $sheet  Wael worksheet tab.
     * @param  Doctor  $doctor  Doctor record with fixed fees loaded.
     * @param  Collection<int, DailyWorkRow>  $doctorRows  Work rows for this doctor.
     * @param  Carbon  $monthStart  First day of the export month.
     * @param  Carbon  $monthEnd  Last day of the export month.
     */
    private function fillWaelSheet(
        Worksheet $sheet,
        Doctor $doctor,
        Collection $doctorRows,
        Carbon $monthStart,
        Carbon $monthEnd,
    ): void {
        $daysInMonth = (int) $monthStart->daysInMonth;
        $firstDayRow = 5;
        $lastDayRow = 4 + $daysInMonth;
        $totalRow = $lastDayRow + 1;

        $this->clearDataArea($sheet, $firstDayRow, $totalRow + 15, 'S');

        $doctor->loadMissing('doctorFixedFees.treatment');
        $fixedFeesByCode = [];

        foreach ($doctor->doctorFixedFees as $fixedFee) {
            $fixedFeesByCode[$fixedFee->treatment->code] = $fixedFee;
        }

        $dailyData = $this->aggregateWaelDailyData($doctorRows, $fixedFeesByCode, $monthStart);

        $totals = [
            'aed' => '0.00',
            'usd' => '0.00',
            'usd_to_aed' => '0.00',
            'visa' => '0.00',
            'daily_total' => '0.00',
            'impl' => '0.00',
            'bg' => '0.00',
            'sinus' => '0.00',
            'surg_cash' => '0.00',
        ];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $row = $firstDayRow + $day - 1;
            $date = $monthStart->copy()->day($day);
            $dateKey = $date->toDateString();

            $sheet->setCellValue('A'.$row, Date::PHPToExcel($date));

            if (! array_key_exists($dateKey, $dailyData)) {
                $this->setNumericCell($sheet, 'D'.$row, 0);
                $this->setNumericCell($sheet, 'G'.$row, 0);

                continue;
            }

            $dayData = $dailyData[$dateKey];

            $this->setNumericCell($sheet, 'B'.$row, $dayData['aed']);
            $this->setNumericCell($sheet, 'C'.$row, $dayData['usd']);
            $this->setNumericCell($sheet, 'D'.$row, $dayData['usd_to_aed']);
            $this->setNumericCell($sheet, 'F'.$row, $dayData['visa']);
            $this->setNumericCell($sheet, 'G'.$row, $dayData['daily_total']);
            $this->setNumericCell($sheet, 'N'.$row, $dayData['surg_cash']);
            $this->setNumericCell($sheet, 'P'.$row, $dayData['impl']);
            $this->setNumericCell($sheet, 'Q'.$row, $dayData['bg']);
            $this->setNumericCell($sheet, 'R'.$row, $dayData['sinus']);

            if ($dayData['remarks'] !== '') {
                $sheet->setCellValue('K'.$row, $dayData['remarks']);
            }

            foreach ($totals as $key => $value) {
                $totals[$key] = MoneyCalculator::add($value, $dayData[$key]);
            }
        }

        $sheet->setCellValue('A'.$totalRow, 'TOTAL');
        $this->setNumericCell($sheet, 'B'.$totalRow, $totals['aed']);
        $this->setNumericCell($sheet, 'C'.$totalRow, $totals['usd']);
        $this->setNumericCell($sheet, 'D'.$totalRow, $totals['usd_to_aed']);
        $this->setNumericCell($sheet, 'F'.$totalRow, $totals['visa']);
        $this->setNumericCell($sheet, 'G'.$totalRow, $totals['daily_total']);
        $this->setNumericCell($sheet, 'H'.$totalRow, 0);
        $this->setNumericCell($sheet, 'N'.$totalRow, $totals['surg_cash']);
        $this->setNumericCell($sheet, 'P'.$totalRow, $totals['impl']);
        $this->setNumericCell($sheet, 'Q'.$totalRow, $totals['bg']);
        $this->setNumericCell($sheet, 'R'.$totalRow, $totals['sinus']);

        $this->writeWaelSummaryBlock($sheet, $totalRow, $totals, $doctor);
    }

    /**
     * @param  array{aed: string, usd: string, usd_to_aed: string, visa: string, daily_total: string, impl: string, bg: string, sinus: string, surg_cash: string}  $totals
     */
    private function writeWaelSummaryBlock(Worksheet $sheet, int $totalRow, array $totals, Doctor $doctor): void
    {
        $summaryStart = $totalRow + 2;
        $grandTotal = MoneyCalculator::add(
            MoneyCalculator::add($totals['daily_total'], $totals['usd_to_aed']),
            $totals['visa'],
        );
        $labCostTotal = '0.00';
        $netTotal = MoneyCalculator::subtract($grandTotal, $labCostTotal);

        $sheet->setCellValue('A'.$summaryStart, 'AED =');
        $this->setNumericCell($sheet, 'B'.$summaryStart, $totals['daily_total']);
        $sheet->setCellValue('A'.($summaryStart + 1), '$ USD =');
        $this->setNumericCell($sheet, 'B'.($summaryStart + 1), $totals['usd_to_aed']);
        $sheet->setCellValue('A'.($summaryStart + 2), 'V.C =');
        $this->setNumericCell($sheet, 'B'.($summaryStart + 2), $totals['visa']);
        $sheet->setCellValue('A'.($summaryStart + 4), 'Total');
        $this->setNumericCell($sheet, 'B'.($summaryStart + 4), $grandTotal);
        $sheet->setCellValue('A'.($summaryStart + 5), 'LAB=');
        $this->setNumericCell($sheet, 'B'.($summaryStart + 5), $labCostTotal);
        $sheet->setCellValue('A'.($summaryStart + 7), 'Net Total');
        $this->setNumericCell($sheet, 'B'.($summaryStart + 7), $netTotal);

        $doctorIncome = MoneyCalculator::percentage($netTotal, '50');
        $sheet->setCellValue('A'.($summaryStart + 8), '35%=');
        $this->setNumericCell($sheet, 'B'.($summaryStart + 8), $doctorIncome);
        $sheet->setCellValue('A'.($summaryStart + 9), 'Surj=');
        $this->setNumericCell($sheet, 'B'.($summaryStart + 9), $totals['surg_cash']);
    }

    /**
     * Aggregate standard-layout daily payment and lab-cost data keyed by date.
     *
     * @param  Collection<int, DailyWorkRow>  $doctorRows  Work rows for one doctor.
     * @param  Carbon  $monthStart  Month anchor for sheet-day date resolution.
     * @param  bool  $paymentsOnly  When true, skip treatment and JOB aggregation.
     * @param  array<int, string>  $treatmentColumnCodes  Treatment codes mapped to Income Excel columns.
     * @return array<string, array<string, mixed>> Date string (Y-m-d) → daily totals.
     */
    private function aggregateStandardDailyData(
        Collection $doctorRows,
        Carbon $monthStart,
        Clinic $clinic,
        bool $paymentsOnly = false,
        array $treatmentColumnCodes = [],
    ): array {
        /** @var array<string, array<string, mixed>> $daily */
        $daily = [];
        $clinicCurrency = ClinicCurrencySupport::baseCurrency($clinic);
        $isLegacyAed = ClinicCurrencySupport::usesLegacyPaymentLayout($clinic);

        foreach ($doctorRows as $workRow) {
            $sheetDay = null;
            if (is_array($workRow->raw_data_json) && array_key_exists('sheet_day', $workRow->raw_data_json)) {
                $sheetDay = $workRow->raw_data_json['sheet_day'];
            }

            $dateKey = ReportMonthResolver::resolveWorkDateForRow(
                $monthStart,
                $workRow->work_date,
                $sheetDay,
            );

            if (! array_key_exists($dateKey, $daily)) {
                $daily[$dateKey] = [
                    'dhs' => '0.00',
                    'cheque' => '0.00',
                    'tabby' => '0.00',
                    'usd' => '0.00',
                    'usd_to_aed' => '0.00',
                    'visa' => '0.00',
                    'total' => '0.00',
                    'job' => '0.00',
                    'treatments' => [],
                    'opg_normal_value' => '0.00',
                    'opg_3d_value' => '0.00',
                    'nurse_commission' => '0.00',
                ];
            }

            if ($isLegacyAed) {
                $daily[$dateKey]['dhs'] = MoneyCalculator::add($daily[$dateKey]['dhs'], (string) $workRow->dhs_amount);
                $daily[$dateKey]['cheque'] = MoneyCalculator::add($daily[$dateKey]['cheque'], (string) $workRow->cheque_amount);
                $daily[$dateKey]['tabby'] = MoneyCalculator::add($daily[$dateKey]['tabby'], (string) $workRow->tabby_amount);
                $daily[$dateKey]['usd'] = MoneyCalculator::add($daily[$dateKey]['usd'], (string) $workRow->usd_amount);
                $daily[$dateKey]['usd_to_aed'] = MoneyCalculator::add($daily[$dateKey]['usd_to_aed'], (string) $workRow->usd_to_aed_amount);
                $daily[$dateKey]['visa'] = MoneyCalculator::add($daily[$dateKey]['visa'], (string) $workRow->visa_amount);
                $daily[$dateKey]['total'] = MoneyCalculator::add($daily[$dateKey]['total'], (string) $workRow->paid_total_aed);
            } else {
                $paymentTotals = $this->paymentCalculationService->calculateTotalCollected(
                    $clinic,
                    (string) $workRow->dhs_amount,
                    (string) $workRow->usd_amount,
                    (string) $workRow->visa_amount,
                    chequeAmount: (string) $workRow->cheque_amount,
                    tabbyAmount: (string) $workRow->tabby_amount,
                );
                $foreignInClinic = ClinicCurrencySupport::foreignCashInClinicCurrency(
                    (string) $workRow->usd_amount,
                    $clinicCurrency,
                    $this->defaultUsdExchangeRate,
                );

                $daily[$dateKey]['dhs'] = MoneyCalculator::add($daily[$dateKey]['dhs'], (string) $workRow->dhs_amount);
                $daily[$dateKey]['cheque'] = MoneyCalculator::add($daily[$dateKey]['cheque'], (string) $workRow->cheque_amount);
                $daily[$dateKey]['tabby'] = MoneyCalculator::add($daily[$dateKey]['tabby'], (string) $workRow->tabby_amount);
                $daily[$dateKey]['usd'] = MoneyCalculator::add($daily[$dateKey]['usd'], (string) $workRow->usd_amount);
                $daily[$dateKey]['usd_to_aed'] = MoneyCalculator::add($daily[$dateKey]['usd_to_aed'], $foreignInClinic);
                $daily[$dateKey]['visa'] = MoneyCalculator::add($daily[$dateKey]['visa'], (string) $workRow->visa_amount);
                $daily[$dateKey]['total'] = MoneyCalculator::add($daily[$dateKey]['total'], $paymentTotals['paid_total']);
            }

            if ($paymentsOnly) {
                continue;
            }

            foreach ($workRow->workItems as $workItem) {
                $code = $workItem->treatment->code;

                if (in_array($code, $treatmentColumnCodes, true)) {
                    if (! array_key_exists($code, $daily[$dateKey]['treatments'])) {
                        $daily[$dateKey]['treatments'][$code] = 0;
                    }

                    $daily[$dateKey]['treatments'][$code] += (int) $workItem->quantity;
                }

                if ($workItem->labJob !== null) {
                    $jobAmount = (string) $workItem->labJob->total_cost_aed;

                    if (! $isLegacyAed) {
                        $jobAmount = ClinicCurrencySupport::fromStoredAedEquivalent(
                            $jobAmount,
                            $clinicCurrency,
                            $this->defaultUsdExchangeRate,
                        );
                    }

                    $daily[$dateKey]['job'] = MoneyCalculator::add(
                        $daily[$dateKey]['job'],
                        $jobAmount,
                    );
                }

                if ($workItem->nurseCommission !== null) {
                    $commission = $workItem->nurseCommission;
                    $commissionAmount = (string) $commission->total_commission_aed;

                    if (! $isLegacyAed) {
                        $commissionAmount = ClinicCurrencySupport::fromStoredAedEquivalent(
                            $commissionAmount,
                            $clinicCurrency,
                            $this->defaultUsdExchangeRate,
                        );
                    }

                    $daily[$dateKey]['nurse_commission'] = MoneyCalculator::add(
                        $daily[$dateKey]['nurse_commission'],
                        $commissionAmount,
                    );

                    $lineValue = MoneyCalculator::multiply(
                        (string) $commission->treatment_price_aed,
                        (int) $commission->quantity,
                    );

                    if (! $isLegacyAed) {
                        $lineValue = ClinicCurrencySupport::fromStoredAedEquivalent(
                            $lineValue,
                            $clinicCurrency,
                            $this->defaultUsdExchangeRate,
                        );
                    }

                    if (OpgTreatmentCodes::isNormal($commission->treatment_code_snapshot)) {
                        $daily[$dateKey]['opg_normal_value'] = MoneyCalculator::add(
                            $daily[$dateKey]['opg_normal_value'],
                            $lineValue,
                        );
                    }

                    if (OpgTreatmentCodes::is3d($commission->treatment_code_snapshot)) {
                        $daily[$dateKey]['opg_3d_value'] = MoneyCalculator::add(
                            $daily[$dateKey]['opg_3d_value'],
                            $lineValue,
                        );
                    }
                }
            }
        }

        return $daily;
    }

    /**
     * Aggregate Wael-layout daily payment and fixed-fee surgery data keyed by date.
     *
     * @param  Collection<int, DailyWorkRow>  $doctorRows  Work rows for Wael.
     * @param  array<string, mixed>  $fixedFeesByCode  Treatment code → fixed fee model.
     * @param  Carbon  $monthStart  Month anchor for sheet-day date resolution.
     * @return array<string, array<string, string>> Date string (Y-m-d) → daily amount fields.
     */
    private function aggregateWaelDailyData(Collection $doctorRows, array $fixedFeesByCode, Carbon $monthStart): array
    {
        /** @var array<string, array<string, string>> $daily */
        $daily = [];

        foreach ($doctorRows as $workRow) {
            $sheetDay = null;
            if (is_array($workRow->raw_data_json) && array_key_exists('sheet_day', $workRow->raw_data_json)) {
                $sheetDay = $workRow->raw_data_json['sheet_day'];
            }

            $dateKey = ReportMonthResolver::resolveWorkDateForRow(
                $monthStart,
                $workRow->work_date,
                $sheetDay,
            );

            if (! array_key_exists($dateKey, $daily)) {
                $daily[$dateKey] = [
                    'aed' => '0.00',
                    'usd' => '0.00',
                    'usd_to_aed' => '0.00',
                    'visa' => '0.00',
                    'daily_total' => '0.00',
                    'impl' => '0.00',
                    'bg' => '0.00',
                    'sinus' => '0.00',
                    'surg_cash' => '0.00',
                    'remarks' => '',
                ];
            }

            $daily[$dateKey]['aed'] = MoneyCalculator::add($daily[$dateKey]['aed'], (string) $workRow->dhs_amount);
            $daily[$dateKey]['usd'] = MoneyCalculator::add($daily[$dateKey]['usd'], (string) $workRow->usd_amount);
            $daily[$dateKey]['usd_to_aed'] = MoneyCalculator::add($daily[$dateKey]['usd_to_aed'], (string) $workRow->usd_to_aed_amount);
            $daily[$dateKey]['visa'] = MoneyCalculator::add($daily[$dateKey]['visa'], (string) $workRow->visa_amount);
            $daily[$dateKey]['daily_total'] = MoneyCalculator::add($daily[$dateKey]['daily_total'], (string) $workRow->paid_total_aed);

            $remarks = trim((string) $workRow->treatment_text);
            if ($remarks !== '') {
                if ($daily[$dateKey]['remarks'] !== '') {
                    $daily[$dateKey]['remarks'] .= ' | ';
                }

                $daily[$dateKey]['remarks'] .= $remarks;
            }

            foreach ($workRow->workItems as $workItem) {
                $code = $workItem->treatment->code;
                $fixedFee = $fixedFeesByCode[$code] ?? null;

                if ($fixedFee === null) {
                    continue;
                }

                $line = $this->waelFixedFeeCalculator->calculateLine(
                    $workRow,
                    $workItem,
                    $fixedFee,
                    $this->defaultUsdExchangeRate,
                );

                if ($line === null) {
                    continue;
                }

                $daily[$dateKey]['surg_cash'] = MoneyCalculator::add(
                    $daily[$dateKey]['surg_cash'],
                    $line['amount_aed'],
                );

                if ($line['export_bucket'] === 'impl') {
                    $daily[$dateKey]['impl'] = MoneyCalculator::add(
                        $daily[$dateKey]['impl'],
                        $line['payout_amount'],
                    );
                }

                if ($line['export_bucket'] === 'bg') {
                    $daily[$dateKey]['bg'] = MoneyCalculator::add(
                        $daily[$dateKey]['bg'],
                        $line['payout_amount'],
                    );
                }

                if ($line['export_bucket'] === 'sinus') {
                    $daily[$dateKey]['sinus'] = MoneyCalculator::add(
                        $daily[$dateKey]['sinus'],
                        $line['payout_amount'],
                    );
                }
            }
        }

        return $daily;
    }

    /**
     * Write row-1 payment and treatment column headers for standard doctor sheets.
     *
     * Only applied when the profile sets `write_payment_headers`.
     *
     * @param  Worksheet  $sheet  Target worksheet.
     * @param  array<string, mixed>  $profile  Export profile with header flags.
     */
    private function writeStandardHeaders(Worksheet $sheet, array $profile, Clinic $clinic): void
    {
        if ($profile['first_day_row'] > 2) {
            $sheet->setCellValue('A'.($profile['first_day_row'] - 1), 'Date');
        }

        if (! ($profile['write_payment_headers'] ?? false)) {
            return;
        }

        $clinicCurrency = ClinicCurrencySupport::baseCurrency($clinic);
        $isLegacyAed = ClinicCurrencySupport::usesLegacyPaymentLayout($clinic);
        $foreignCurrency = ClinicCurrencySupport::foreignCashCurrency($clinicCurrency);
        $primaryLabel = $isLegacyAed ? 'DHS' : $clinicCurrency;
        $foreignLabel = $isLegacyAed
            ? 'USD'
            : ($foreignCurrency ?? 'FOREIGN');
        $foreignConvertedLabel = $isLegacyAed ? 'to AED' : 'in '.$clinicCurrency;

        /** @var array<string, string> $paymentColumns */
        $paymentColumns = $profile['payment_columns'] ?? [];

        $sheet->setCellValue('B1', $primaryLabel);

        if (array_key_exists('cheque', $paymentColumns)) {
            $sheet->setCellValue('C1', 'cheque');
            $sheet->setCellValue('D1', 'Tabby');
            $sheet->setCellValue('E1', $foreignLabel);
            $sheet->setCellValue('F1', $foreignConvertedLabel);
            $sheet->setCellValue('G1', 'visa');
            $sheet->setCellValue('H1', 'DAILY TOTAL');
            $sheet->setCellValue('I1', 'JOB');
            $this->writeTreatmentColumnHeaders($sheet, $profile);
            $this->writeNurseCommissionColumnHeaders($sheet);

            return;
        }

        $sheet->setCellValue('C1', $foreignLabel);
        $sheet->setCellValue('D1', $foreignConvertedLabel);
        $sheet->setCellValue('E1', 'VISA');
        $sheet->setCellValue('F1', 'DAILY TOTAL');
        $sheet->setCellValue('G1', 'JOB');

        $this->writeTreatmentColumnHeaders($sheet, $profile);
        $this->writeNurseCommissionColumnHeaders($sheet);
    }

    private function writeNurseCommissionColumnHeaders(Worksheet $sheet): void
    {
        $sheet->setCellValue(self::NURSE_COMMISSION_COLUMNS['opg_normal_value'].'1', 'OPG-Normal');
        $sheet->setCellValue(self::NURSE_COMMISSION_COLUMNS['opg_3d_value'].'1', 'OPG-3D');
        $sheet->setCellValue(self::NURSE_COMMISSION_COLUMNS['nurse_commission'].'1', 'Nurse Comm.');
    }

    /**
     * @param  array<string, mixed>  $dayData
     */
    private function writeNurseCommissionColumns(Worksheet $sheet, int $row, array $dayData): void
    {
        foreach (self::NURSE_COMMISSION_COLUMNS as $key => $column) {
            $this->setNumericCell($sheet, $column.$row, $dayData[$key] ?? '0.00');
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function writeTreatmentColumnHeaders(Worksheet $sheet, array $profile): void
    {
        foreach ($profile['treatment_columns'] ?? [] as $code => $column) {
            $sheet->setCellValue(
                $column.'1',
                IncomeExportStandardLayout::treatmentHeaderLabel((string) $code),
            );
        }
    }

    /**
     * Clear non-lab income columns (Q–T) in the data area before writing.
     *
     * @param  Worksheet  $sheet  Target worksheet.
     * @param  int  $startRow  First data row (1-based).
     * @param  int  $endRow  Last row to clear (inclusive).
     */
    private function clearNonLabIncomeColumns(Worksheet $sheet, int $startRow, int $endRow): void
    {
        foreach (['Q', 'R', 'S', 'T'] as $column) {
            for ($row = $startRow; $row <= $endRow; $row++) {
                $sheet->setCellValue($column.$row, null);
            }
        }
    }

    private function applyDayColumnDateFormat(Worksheet $sheet, int $firstDayRow, int $lastDayRow): void
    {
        $sheet->getStyle('A'.$firstDayRow.':A'.$lastDayRow)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
    }

    /**
     * Clear all cells in a rectangular data area before filling new values.
     *
     * @param  Worksheet  $sheet  Target worksheet.
     * @param  int  $startRow  First row to clear (1-based).
     * @param  int  $endRow  Last row to clear (inclusive).
     * @param  string  $lastColumn  Last column letter (e.g. `T`).
     */
    private function clearDataArea(Worksheet $sheet, int $startRow, int $endRow, string $lastColumn): void
    {
        for ($row = $startRow; $row <= $endRow; $row++) {
            for ($columnIndex = 1; $columnIndex <= Coordinate::columnIndexFromString($lastColumn); $columnIndex++) {
                $column = Coordinate::stringFromColumnIndex($columnIndex);
                $sheet->setCellValue($column.$row, null);
            }
        }
    }

    /**
     * Write a numeric cell value, coercing zero/empty to literal 0.
     *
     * @param  Worksheet  $sheet  Target worksheet.
     * @param  string  $cellAddress  Cell reference (e.g. `B5`).
     * @param  string|int|float  $value  Amount to write.
     */
    private function setNumericCell(Worksheet $sheet, string $cellAddress, string|int|float $value): void
    {
        if ($value === '' || $value === '0.00' || $value === 0 || $value === '0') {
            $sheet->setCellValue($cellAddress, 0);

            return;
        }

        $sheet->setCellValue($cellAddress, (float) $value);
    }

    /**
     * Resolve the export profile for a doctor by database code.
     *
     * @param  Doctor  $doctor  Doctor whose code is matched against profiles.
     * @return array<string, mixed>|null Profile array, or null when the doctor has no export sheet.
     */
    private function resolveExportProfile(Doctor $doctor): ?array
    {
        return $this->exportProfileService->resolveForDoctor($doctor);
    }

    private function ensureDoctorSheet(Spreadsheet $spreadsheet, string $sheetName): Worksheet
    {
        $existing = $spreadsheet->getSheetByName($sheetName);

        if ($existing !== null) {
            return $existing;
        }

        $spreadsheet->createSheet();
        $sheet = $spreadsheet->getSheet($spreadsheet->getSheetCount() - 1);
        $sheet->setTitle($sheetName);

        return $sheet;
    }

    /**
     * @param  array<int, string>  $sheetNamesToKeep
     */
    private function removeSheetsExcept(Spreadsheet $spreadsheet, array $sheetNamesToKeep): void
    {
        if ($sheetNamesToKeep === []) {
            return;
        }

        $keep = array_flip($sheetNamesToKeep);

        for ($index = $spreadsheet->getSheetCount() - 1; $index >= 0; $index--) {
            $sheet = $spreadsheet->getSheet($index);
            $title = $sheet->getTitle();

            if (! array_key_exists($title, $keep)) {
                $spreadsheet->removeSheetByIndex($index);
            }
        }
    }

    private function shouldRemapJackTreatmentCounts(Doctor $doctor): bool
    {
        return DoctorLabelNormalizer::extractCodeGuess($doctor->code) === 'JACK';
    }

    /**
     * Match Original Income Dr.Jack sheet: IMPL-ZIR counts move between ZIR-CR, IMPL-CR, and IMPL-ZIR columns.
     *
     * @param  array<string, int>  $treatments
     * @return array<string, int>
     */
    private function remapJackIncomeTreatmentCounts(array $treatments): array
    {
        $zir = $treatments['ZIR'] ?? 0;
        $implZir = $treatments['IMPL-ZIR'] ?? 0;
        $mc = $treatments['MC'] ?? 0;

        if ($zir > 0 && $implZir > 0) {
            $treatments['ZIR'] = $zir + $implZir;
            unset($treatments['IMPL-ZIR']);
        } elseif ($mc > 0 && $implZir > 0) {
            $treatments['IMPL-CR'] = ($treatments['IMPL-CR'] ?? 0) + $implZir;
            unset($treatments['IMPL-ZIR']);
        }

        return $treatments;
    }

    /**
     * Build the output filename for a monthly income export.
     *
     * @param  Carbon  $monthStart  First day of the export month.
     * @return string Filename like `Server Income January 2025.xlsx`.
     */
    private function buildFileName(Carbon $monthStart): string
    {
        $monthLabel = $monthStart->format('F Y');

        return "Server Income {$monthLabel}.xlsx";
    }
}
