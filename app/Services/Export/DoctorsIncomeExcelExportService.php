<?php

namespace App\Services\Export;

use App\Enums\CommissionType;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Services\Accounting\IncomeReconciliationService;
use App\Services\Accounting\WaelFixedFeeCalculator;
use App\Support\MoneyCalculator;
use App\Support\ReportMonthResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Fills the Original Income Excel template (daily subtotals, JOB/lab costs, doctor sheets).
 *
 * Export layout per doctor comes from {@see DoctorIncomeExportProfileService} (database).
 * JOB amounts come from {@see \App\Services\Accounting\LabJobCalculationService} + lab_prices.
 */
class DoctorsIncomeExcelExportService
{
    private const TEMPLATE_PATH = 'templates/original_income_template.xlsx';

    /**
     * @param  IncomeReconciliationService  $incomeReconciliationService  Pre-export validation.
     * @param  DoctorIncomeExportProfileService  $exportProfileService  DB-driven sheet/column layout.
     * @param  string  $defaultUsdExchangeRate  USD→AED rate for Wael fixed-fee conversion.
     */
    public function __construct(
        private readonly IncomeReconciliationService $incomeReconciliationService,
        private readonly DoctorIncomeExportProfileService $exportProfileService,
        private readonly WaelFixedFeeCalculator $waelFixedFeeCalculator,
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
        $reconciliationIssues = $this->incomeReconciliationService->validateReport($dailyReport);

        if ($this->incomeReconciliationService->hasErrors($reconciliationIssues)) {
            throw new RuntimeException(
                'Income export blocked: database reconciliation failed. Check import logs for details.',
            );
        }

        $monthStart = ReportMonthResolver::requireFromFilename($dailyReport->source_file_name);
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
            throw new RuntimeException('Income Excel template not found at ' . self::TEMPLATE_PATH);
        }

        $spreadsheet = IOFactory::load($templatePath);

        $workRowsQuery = DailyWorkRow::query()
            ->with(['doctor', 'workItems.treatment', 'workItems.labJob']);

        if ($dailyReport !== null) {
            $workRowsQuery->where('daily_report_id', $dailyReport->id);
        } else {
            $workRowsQuery->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
        }

        $workRows = $workRowsQuery->get();

        $rowsByDoctor = $workRows->groupBy('doctor_id');

        foreach (Doctor::query()->where('is_active', true)->get() as $doctor) {
            $profile = $this->resolveExportProfile($doctor);

            if ($profile === null) {
                continue;
            }

            $sheetName = $profile['sheet_name'];
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if ($sheet === null) {
                continue;
            }

            $doctorRows = $rowsByDoctor->get($doctor->id, collect());

            if ($profile['layout'] === 'wael') {
                $this->fillWaelSheet($sheet, $doctor, $doctorRows, $monthStart, $monthEnd);
            } else {
                $this->fillStandardDoctorSheet($sheet, $profile, $doctor, $doctorRows, $monthStart, $monthEnd);
            }
        }

        $fileName = $this->buildFileName($monthStart);
        $relativePath = 'exports/' . $fileName;
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
        $monthStart = ReportMonthResolver::requireFromFilename($dailyReport->source_file_name);

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
    ): void {
        $daysInMonth = (int) $monthStart->daysInMonth;
        $firstDayRow = (int) $profile['first_day_row'];
        $lastDayRow = $firstDayRow + $daysInMonth - 1;
        $totalRow = $lastDayRow + 1;

        $this->clearDataArea($sheet, $firstDayRow, $totalRow + 12, 'T');
        $this->clearNonLabIncomeColumns($sheet, $firstDayRow, $totalRow);
        $this->writeStandardHeaders($sheet, $profile);

        $paymentsOnly = ($profile['treatment_columns'] ?? []) === [];
        /** @var array<string, string> $treatmentColumns */
        $treatmentColumns = $profile['treatment_columns'] ?? [];
        $dailyData = $this->aggregateStandardDailyData(
            $doctorRows,
            $monthStart,
            $paymentsOnly,
            array_keys($treatmentColumns),
        );
        /** @var array<string, string> $paymentColumns */
        $paymentColumns = $profile['payment_columns'];

        $columnTotals = [
            'dhs' => '0.00',
            'usd' => '0.00',
            'usd_to_aed' => '0.00',
            'visa' => '0.00',
            'total' => '0.00',
        ];

        /** @var array<string, int> $treatmentTotals */
        $treatmentTotals = [];
        $labCostTotal = '0.00';

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $row = $firstDayRow + $day - 1;
            $date = $monthStart->copy()->day($day);
            $dateKey = $date->toDateString();

            $sheet->setCellValue('A' . $row, Date::PHPToExcel($date));

            if (! array_key_exists($dateKey, $dailyData)) {
                $sheet->setCellValue($paymentColumns['usd_to_aed'] . $row, 0);
                $sheet->setCellValue($paymentColumns['total'] . $row, 0);

                continue;
            }

            $dayData = $dailyData[$dateKey];

            $this->setNumericCell($sheet, $paymentColumns['dhs'] . $row, $dayData['dhs']);
            $this->setNumericCell($sheet, $paymentColumns['usd'] . $row, $dayData['usd']);
            $this->setNumericCell($sheet, $paymentColumns['usd_to_aed'] . $row, $dayData['usd_to_aed']);
            $this->setNumericCell($sheet, $paymentColumns['visa'] . $row, $dayData['visa']);
            $this->setNumericCell($sheet, $paymentColumns['total'] . $row, $dayData['total']);

            if (! $paymentsOnly) {
                $this->setNumericCell($sheet, $paymentColumns['job'] . $row, $dayData['job']);
                $labCostTotal = MoneyCalculator::add($labCostTotal, $dayData['job']);

                foreach ($dayData['treatments'] as $code => $quantity) {
                    if (! array_key_exists($code, $treatmentColumns)) {
                        continue;
                    }

                    $column = $treatmentColumns[$code];
                    $sheet->setCellValue($column . $row, $quantity);

                    if (! array_key_exists($code, $treatmentTotals)) {
                        $treatmentTotals[$code] = 0;
                    }

                    $treatmentTotals[$code] += $quantity;
                }
            }

            foreach ($columnTotals as $key => $value) {
                $columnTotals[$key] = MoneyCalculator::add($value, $dayData[$key]);
            }
        }

        $sheet->setCellValue('A' . $totalRow, 'TOTAL');
        $this->setNumericCell($sheet, $paymentColumns['dhs'] . $totalRow, $columnTotals['dhs']);
        $this->setNumericCell($sheet, $paymentColumns['usd'] . $totalRow, $columnTotals['usd']);
        $this->setNumericCell($sheet, $paymentColumns['usd_to_aed'] . $totalRow, $columnTotals['usd_to_aed']);
        $this->setNumericCell($sheet, $paymentColumns['visa'] . $totalRow, $columnTotals['visa']);
        $this->setNumericCell($sheet, $paymentColumns['total'] . $totalRow, $columnTotals['total']);

        if (! $paymentsOnly) {
            $this->setNumericCell($sheet, $paymentColumns['job'] . $totalRow, $labCostTotal);

            foreach ($treatmentTotals as $code => $quantity) {
                if (! array_key_exists($code, $treatmentColumns)) {
                    continue;
                }

                $sheet->setCellValue($treatmentColumns[$code] . $totalRow, $quantity);
            }
        }

        $summaryStart = $totalRow + 2;
        $this->writeOriginalIncomeSummaryBlock(
            $sheet,
            $profile,
            $summaryStart,
            $columnTotals,
            $labCostTotal,
            $doctor,
        );
    }

    /**
     * Writes the bottom summary block (DHS, USD, VISA, INURANCE, TOTAL, LAB COST-, commission).
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
    ): void {
        $summaryRows = [
            ['label' => 'DHS', 'value' => $columnTotals['dhs']],
            ['label' => 'USD', 'value' => $columnTotals['usd_to_aed']],
            ['label' => 'VISA', 'value' => $columnTotals['visa']],
            ['label' => 'INURANCE', 'value' => null],
            ['label' => 'TOTAL', 'value' => $columnTotals['total']],
            ['label' => 'LAB COST-', 'value' => $labCostTotal],
        ];

        foreach ($summaryRows as $index => $summaryRow) {
            $rowNumber = $summaryStart + $index;
            $sheet->setCellValue('A' . $rowNumber, $summaryRow['label']);

            if ($summaryRow['value'] === null) {
                $sheet->setCellValue('B' . $rowNumber, null);

                continue;
            }

            $this->setNumericCell($sheet, 'B' . $rowNumber, $summaryRow['value']);
        }

        if ($doctor->commission_type !== CommissionType::Percentage) {
            return;
        }

        $commissionPercentage = '0';
        if ($doctor->commission_percentage !== null) {
            $commissionPercentage = (string) $doctor->commission_percentage;
        }

        $netTotal = MoneyCalculator::subtract($columnTotals['total'], $labCostTotal);
        $doctorIncome = MoneyCalculator::percentage($netTotal, $commissionPercentage);
        $commissionLabel = bcdiv($commissionPercentage, '100', 2);

        if ($profile['summary_shows_net_total']) {
            $sheet->setCellValue('A' . ($summaryStart + 6), 'NET TOTAL');
            $this->setNumericCell($sheet, 'B' . ($summaryStart + 6), $netTotal);
            $sheet->setCellValue('A' . ($summaryStart + 7), $commissionLabel);
            $this->setNumericCell($sheet, 'B' . ($summaryStart + 7), $doctorIncome);

            return;
        }

        $this->setNumericCell($sheet, 'B' . ($summaryStart + 7), $doctorIncome);
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

            $sheet->setCellValue('M' . $row, Date::PHPToExcel($date));

            if (! array_key_exists($dateKey, $dailyData)) {
                $sheet->setCellValue('D' . $row, 0);
                $sheet->setCellValue('G' . $row, 0);

                continue;
            }

            $dayData = $dailyData[$dateKey];

            $this->setNumericCell($sheet, 'B' . $row, $dayData['aed']);
            $this->setNumericCell($sheet, 'C' . $row, $dayData['usd']);
            $this->setNumericCell($sheet, 'D' . $row, $dayData['usd_to_aed']);
            $this->setNumericCell($sheet, 'F' . $row, $dayData['visa']);
            $this->setNumericCell($sheet, 'G' . $row, $dayData['daily_total']);
            $this->setNumericCell($sheet, 'N' . $row, $dayData['surg_cash']);
            $this->setNumericCell($sheet, 'P' . $row, $dayData['impl']);
            $this->setNumericCell($sheet, 'Q' . $row, $dayData['bg']);
            $this->setNumericCell($sheet, 'R' . $row, $dayData['sinus']);

            foreach ($totals as $key => $value) {
                $totals[$key] = MoneyCalculator::add($value, $dayData[$key]);
            }
        }

        $sheet->setCellValue('A' . $totalRow, 'TOTAL');
        $this->setNumericCell($sheet, 'B' . $totalRow, $totals['aed']);
        $this->setNumericCell($sheet, 'C' . $totalRow, $totals['usd']);
        $this->setNumericCell($sheet, 'D' . $totalRow, $totals['usd_to_aed']);
        $this->setNumericCell($sheet, 'F' . $totalRow, $totals['visa']);
        $this->setNumericCell($sheet, 'G' . $totalRow, $totals['daily_total']);
        $this->setNumericCell($sheet, 'H' . $totalRow, 0);
        $this->setNumericCell($sheet, 'N' . $totalRow, $totals['surg_cash']);
        $this->setNumericCell($sheet, 'P' . $totalRow, $totals['impl']);
        $this->setNumericCell($sheet, 'Q' . $totalRow, $totals['bg']);
        $this->setNumericCell($sheet, 'R' . $totalRow, $totals['sinus']);

        $summaryBase = $totalRow + 2;
        $this->setNumericCell($sheet, 'B' . $summaryBase, $totals['aed']);
        $this->setNumericCell($sheet, 'B' . ($summaryBase + 1), $totals['usd_to_aed']);
        $this->setNumericCell($sheet, 'B' . ($summaryBase + 2), $totals['visa']);
        $this->setNumericCell($sheet, 'B' . ($summaryBase + 3), $totals['daily_total']);
        $this->setNumericCell($sheet, 'B' . ($summaryBase + 4), 0);
        $this->setNumericCell($sheet, 'B' . ($summaryBase + 5), $totals['daily_total']);
        $this->setNumericCell($sheet, 'B' . ($summaryBase + 8), $totals['surg_cash']);
        $this->setNumericCell($sheet, 'B' . ($summaryBase + 10), $totals['surg_cash']);
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
        bool $paymentsOnly = false,
        array $treatmentColumnCodes = [],
    ): array {
        /** @var array<string, array<string, mixed>> $daily */
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
                    'dhs' => '0.00',
                    'usd' => '0.00',
                    'usd_to_aed' => '0.00',
                    'visa' => '0.00',
                    'total' => '0.00',
                    'job' => '0.00',
                    'treatments' => [],
                ];
            }

            $daily[$dateKey]['dhs'] = MoneyCalculator::add($daily[$dateKey]['dhs'], (string) $workRow->dhs_amount);
            $daily[$dateKey]['usd'] = MoneyCalculator::add($daily[$dateKey]['usd'], (string) $workRow->usd_amount);
            $daily[$dateKey]['usd_to_aed'] = MoneyCalculator::add($daily[$dateKey]['usd_to_aed'], (string) $workRow->usd_to_aed_amount);
            $daily[$dateKey]['visa'] = MoneyCalculator::add($daily[$dateKey]['visa'], (string) $workRow->visa_amount);
            $daily[$dateKey]['total'] = MoneyCalculator::add($daily[$dateKey]['total'], (string) $workRow->paid_total_aed);

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
                    $daily[$dateKey]['job'] = MoneyCalculator::add(
                        $daily[$dateKey]['job'],
                        (string) $workItem->labJob->total_cost_aed,
                    );
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
                ];
            }

            $daily[$dateKey]['aed'] = MoneyCalculator::add($daily[$dateKey]['aed'], (string) $workRow->dhs_amount);
            $daily[$dateKey]['usd'] = MoneyCalculator::add($daily[$dateKey]['usd'], (string) $workRow->usd_amount);
            $daily[$dateKey]['usd_to_aed'] = MoneyCalculator::add($daily[$dateKey]['usd_to_aed'], (string) $workRow->usd_to_aed_amount);
            $daily[$dateKey]['visa'] = MoneyCalculator::add($daily[$dateKey]['visa'], (string) $workRow->visa_amount);
            $daily[$dateKey]['daily_total'] = MoneyCalculator::add($daily[$dateKey]['daily_total'], (string) $workRow->paid_total_aed);

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
    private function writeStandardHeaders(Worksheet $sheet, array $profile): void
    {
        if (! ($profile['write_payment_headers'] ?? false)) {
            return;
        }

        $sheet->setCellValue('B1', 'DHS');
        $sheet->setCellValue('C1', 'USD');
        $sheet->setCellValue('D1', 'to AED');
        $sheet->setCellValue('E1', 'VISA');
        $sheet->setCellValue('F1', 'DAILY TOTAL');
        $sheet->setCellValue('G1', 'JOB');
        $sheet->setCellValue('H1', 'M/C-CR');
        $sheet->setCellValue('I1', 'ZIR-CR');
        $sheet->setCellValue('J1', 'IMPL-CR');
        $sheet->setCellValue('K1', 'IMPL-ZIR');
        $sheet->setCellValue('L1', 'VENEER');
        $sheet->setCellValue('M1', 'REIMPL');
        $sheet->setCellValue('N1', 'IMPL');
        $sheet->setCellValue('O1', 'REPEAR');
        $sheet->setCellValue('P1', 'POST');
        $sheet->setCellValue('Q1', 'ABT');
        $sheet->setCellValue('R1', 'REMOVABLE');
        $sheet->setCellValue('S1', 'PARTIAL');
        $sheet->setCellValue('T1', 'BLEACHING');
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
                $sheet->setCellValue($column . $row, null);
            }
        }
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
            for ($columnIndex = 1; $columnIndex <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastColumn); $columnIndex++) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
                $sheet->setCellValue($column . $row, null);
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
