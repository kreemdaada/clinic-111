<?php

namespace App\Services\Import;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Parses Clinic 111 daily/monthly Excel workbooks.
 *
 * Workbook layout:
 * - Sheets "1".."31" = one calendar day each
 * - Row 2: doctor label (e.g. "DR Jack" in column G)
 * - Row 3: headers (Date, Name, MRN, File, Total Cost, Disc., Treatment, Dhs, $/Euro, Visa, crown, …)
 * - Row 4+: patient rows (date in column A only on first row of the day)
 */
class ExcelDailyReportParser
{
    private const CLINIC_HEADER_ROW = 3;

    private const CLINIC_DOCTOR_ROW = 2;

    private const SKIPPED_SHEET_NAMES = ['SCHEDULE', 'SHEET1'];

    /** @var array<int, array<string, mixed>> */
    private array $extractionEvents = [];

    /**
     * Parse an Excel workbook and return extracted daily subtotal rows only.
     *
     * @param  string  $filePath  Absolute path to the `.xlsx` file.
     * @param  Carbon|null  $reportMonth  Month anchor for stale-section filtering.
     * @return array<int, array<string, mixed>> Parsed row dictionaries.
     */
    public function parse(string $filePath, ?Carbon $reportMonth = null): array
    {
        return $this->parseWithDiagnostics($filePath, $reportMonth)['rows'];
    }

    /**
     * Parse an Excel workbook and return rows plus diagnostic extraction events.
     *
     * Detects Clinic 111 layout (day sheets 1–31) or falls back to generic parsing.
     *
     * @param  string  $filePath  Absolute path to the `.xlsx` file.
     * @param  Carbon|null  $reportMonth  Month anchor for stale-section filtering.
     * @return array{rows: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>}
     */
    public function parseWithDiagnostics(string $filePath, ?Carbon $reportMonth = null): array
    {
        $this->extractionEvents = [];

        $spreadsheet = $this->loadSpreadsheet($filePath);

        if ($this->isClinic111Workbook($spreadsheet)) {
            $rows = $this->parseClinic111Workbook($spreadsheet, $reportMonth);

            return [
                'rows' => $rows,
                'events' => $this->extractionEvents,
            ];
        }

        return [
            'rows' => $this->parseGenericSheet($spreadsheet->getActiveSheet()),
            'events' => $this->extractionEvents,
        ];
    }

    /**
     * Load a spreadsheet from disk with data-only reading when supported.
     *
     * @param  string  $filePath  Absolute path to the Excel file.
     * @return Spreadsheet Loaded PhpSpreadsheet instance.
     */
    private function loadSpreadsheet(string $filePath): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($filePath);

        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        return $reader->load($filePath);
    }

    /**
     * Detect whether the workbook uses the Clinic 111 day-sheet layout.
     *
     * @param  Spreadsheet  $spreadsheet  Loaded workbook.
     * @return bool True when at least one day sheet has DATE/NAME/TREATMENT headers.
     */
    private function isClinic111Workbook(Spreadsheet $spreadsheet): bool
    {
        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            if ($this->isDaySheetName($sheetName)) {
                $worksheet = $spreadsheet->getSheetByName($sheetName);

                if ($worksheet !== null && $this->isClinic111Sheet($worksheet)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check whether a sheet name represents a calendar day (1–31).
     *
     * @param  string  $sheetName  Worksheet tab name.
     * @return bool True for numeric day names, excluding SCHEDULE/SHEET1.
     */
    private function isDaySheetName(string $sheetName): bool
    {
        $normalizedName = trim($sheetName);

        if (in_array(strtoupper($normalizedName), self::SKIPPED_SHEET_NAMES, true)) {
            return false;
        }

        return ctype_digit($normalizedName) && (int) $normalizedName >= 1 && (int) $normalizedName <= 31;
    }

    /**
     * Verify that a worksheet has the expected Clinic 111 header row.
     *
     * @param  Worksheet  $worksheet  Sheet to inspect.
     * @return bool True when row 3 contains DATE, NAME, and TREATMENT.
     */
    private function isClinic111Sheet(Worksheet $worksheet): bool
    {
        $headerValues = $this->readRowValues($worksheet, self::CLINIC_HEADER_ROW);

        return in_array('DATE', $headerValues, true)
            && in_array('NAME', $headerValues, true)
            && in_array('TREATMENT', $headerValues, true);
    }

    /**
     * Parse all day sheets in a Clinic 111 workbook into daily subtotal rows.
     *
     * @param  Spreadsheet  $spreadsheet  Loaded workbook.
     * @param  Carbon|null  $reportMonth  Month anchor for stale-section filtering.
     * @return array<int, array<string, mixed>> Merged rows from every valid day sheet.
     */
    private function parseClinic111Workbook(Spreadsheet $spreadsheet, ?Carbon $reportMonth): array
    {
        $parsedRows = [];

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            if (! $this->isDaySheetName($sheetName)) {
                continue;
            }

            $worksheet = $spreadsheet->getSheetByName($sheetName);

            if ($worksheet === null || ! $this->isClinic111Sheet($worksheet)) {
                continue;
            }

            $sheetRows = $this->parseClinic111Sheet($worksheet, $sheetName, $reportMonth);
            $parsedRows = array_merge($parsedRows, $sheetRows);
        }

        return $parsedRows;
    }

    /**
     * Parse one Clinic 111 day sheet into doctor daily-subtotal rows.
     *
     * Walks doctor sections, accumulates patient treatment text, and emits subtotal rows.
     *
     * @param  Worksheet  $worksheet  Day sheet (tab name = day of month).
     * @param  string  $sheetName  Sheet tab name.
     * @param  Carbon|null  $reportMonth  Month anchor for stale-section filtering.
     * @return array<int, array<string, mixed>> Extracted subtotal rows for this sheet.
     */
    private function parseClinic111Sheet(Worksheet $worksheet, string $sheetName, ?Carbon $reportMonth): array
    {
        $parsedRows = [];
        $currentDoctor = null;
        $columnMap = null;
        $sectionPatientTreatments = [];
        $sectionPatientPayments = $this->emptySectionPaymentTotals();
        $sectionAnchorDate = null;
        $sectionMaxFileNumber = 0;
        $sheetDay = (int) trim($sheetName);

        for ($rowIndex = 1; $rowIndex <= $worksheet->getHighestRow(); $rowIndex++) {
            $columnGValue = $this->readCellValue($worksheet, 'G', $rowIndex);

            if ($this->isSpecialSectionLabel($columnGValue)) {
                $this->logSkippedSpecialSectionRow(
                    $worksheet,
                    $rowIndex,
                    $sheetDay,
                    $sheetName,
                    $currentDoctor,
                    $columnGValue,
                    $columnMap,
                );

                $currentDoctor = null;
                $columnMap = null;
                $sectionPatientTreatments = [];
                $sectionPatientPayments = $this->emptySectionPaymentTotals();
                $sectionAnchorDate = null;
                $sectionMaxFileNumber = 0;

                continue;
            }

            $doctorLabel = $this->extractDoctorLabelFromRow($worksheet, $rowIndex);

            if ($doctorLabel !== null) {
                $currentDoctor = $doctorLabel;
                $columnMap = null;
                $sectionPatientTreatments = [];
                $sectionPatientPayments = $this->emptySectionPaymentTotals();
                $sectionAnchorDate = null;
                $sectionMaxFileNumber = 0;

                continue;
            }

            if ($this->isClinicHeaderRow($worksheet, $rowIndex)) {
                $columnMap = $this->buildClinicColumnMap($worksheet, $rowIndex);

                continue;
            }

            if ($currentDoctor === null || $columnMap === null) {
                continue;
            }

            $rowData = $this->extractRow($worksheet, $rowIndex, $columnMap);

            if ($this->hasPatientName($rowData)) {
                if ($sectionAnchorDate === null) {
                    $sectionAnchorDate = $this->parseWorkDateValue($rowData['work_date'] ?? null);
                }

                $sectionMaxFileNumber = max(
                    $sectionMaxFileNumber,
                    $this->extractFileSequenceNumber($rowData['file_number'] ?? null),
                );

                $treatmentText = trim((string) ($rowData['treatment_text'] ?? ''));

                if ($treatmentText !== '') {
                    $sectionPatientTreatments[] = $treatmentText;
                }

                $this->accumulateSectionPatientPayments($sectionPatientPayments, $rowData);

                continue;
            }

            if (! $this->isSectionSubtotalRow($rowData)) {
                if ($this->hasPaymentValues($rowData)) {
                    $this->recordExtractionEvent(
                        status: 'skipped',
                        reason: $this->resolveSkippedPaymentReason($rowData),
                        sheetDay: $sheetDay,
                        sheetName: $sheetName,
                        doctorLabel: $currentDoctor,
                        rowData: $rowData,
                    );
                }

                continue;
            }

            if ($this->shouldSkipStaleSection($sectionAnchorDate, $sectionMaxFileNumber, $reportMonth, $rowData)) {
                $this->recordExtractionEvent(
                    status: 'skipped',
                    reason: 'stale_section',
                    sheetDay: $sheetDay,
                    sheetName: $sheetName,
                    doctorLabel: $currentDoctor,
                    rowData: $rowData,
                    treatmentText: implode(' | ', $sectionPatientTreatments),
                );

                $sectionPatientTreatments = [];
                $sectionPatientPayments = $this->emptySectionPaymentTotals();
                $sectionAnchorDate = null;
                $sectionMaxFileNumber = 0;

                continue;
            }

            $rowData = $this->applySectionPaymentTotals($rowData, $sectionPatientPayments);

            $rowData['doctor'] = $currentDoctor;
            $rowData['sheet_name'] = $sheetName;
            $rowData['sheet_day'] = $sheetDay;
            $rowData['work_date'] = null;
            $rowData['treatment_text'] = implode(' | ', $sectionPatientTreatments);
            $rowData['is_daily_subtotal'] = true;

            $this->recordExtractionEvent(
                status: 'extracted',
                reason: null,
                sheetDay: $sheetDay,
                sheetName: $sheetName,
                doctorLabel: $currentDoctor,
                rowData: $rowData,
                treatmentText: $rowData['treatment_text'],
            );

            $parsedRows[] = $rowData;
            $sectionPatientTreatments = [];
            $sectionPatientPayments = $this->emptySectionPaymentTotals();
            $sectionAnchorDate = null;
            $sectionMaxFileNumber = 0;
        }

        return $parsedRows;
    }

    /**
     * @return array<string, float>
     */
    private function emptySectionPaymentTotals(): array
    {
        return [
            'dhs_amount' => 0.0,
            'cheque_amount' => 0.0,
            'tabby_amount' => 0.0,
            'usd_amount' => 0.0,
            'visa_amount' => 0.0,
            'rubl_amount' => 0.0,
        ];
    }

    /**
     * Sum patient-row payments for a doctor section (used instead of subtotal SUM cells).
     *
     * Cheque and Tabby on "paid balance" rows are balance settlements, not new collections.
     *
     * @param  array<string, float>  $totals
     * @param  array<string, mixed>  $rowData
     */
    private function accumulateSectionPatientPayments(array &$totals, array $rowData): void
    {
        $excludeDeferred = $this->isBalanceSettlementTreatment(
            trim((string) ($rowData['treatment_text'] ?? '')),
        );

        foreach (['dhs_amount', 'usd_amount', 'visa_amount', 'rubl_amount'] as $field) {
            $totals[$field] += (float) ($rowData[$field] ?? 0);
        }

        if (! $excludeDeferred) {
            $totals['cheque_amount'] += (float) ($rowData['cheque_amount'] ?? 0);
            $totals['tabby_amount'] += (float) ($rowData['tabby_amount'] ?? 0);
        }
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @param  array<string, float>  $totals
     * @return array<string, mixed>
     */
    private function applySectionPaymentTotals(array $rowData, array $totals): array
    {
        foreach ($totals as $field => $amount) {
            $rowData[$field] = $amount;
        }

        return $rowData;
    }

    private function isBalanceSettlementTreatment(string $treatmentText): bool
    {
        if ($treatmentText === '') {
            return false;
        }

        return (bool) preg_match('/paid\s*bal/i', $treatmentText);
    }

    /**
     * Record a skip event when a special section label (CASH, TOTAL, OPG) ends a doctor block.
     *
     * @param  Worksheet  $worksheet  Current day sheet.
     * @param  int  $rowIndex  1-based Excel row index.
     * @param  int  $sheetDay  Day-of-month from the sheet name.
     * @param  string  $sheetName  Sheet tab name.
     * @param  string|null  $doctorLabel  Active doctor label, if any.
     * @param  string|null  $columnGValue  Raw column-G cell value.
     * @param  array<string, string>|null  $columnMap  Active column mapping for the section.
     */
    private function logSkippedSpecialSectionRow(
        Worksheet $worksheet,
        int $rowIndex,
        int $sheetDay,
        string $sheetName,
        ?string $doctorLabel,
        ?string $columnGValue,
        ?array $columnMap,
    ): void {
        if ($columnMap === null) {
            return;
        }

        $rowData = $this->extractRow($worksheet, $rowIndex, $columnMap);

        if (! $this->hasPaymentValues($rowData)) {
            return;
        }

        $normalizedLabel = strtoupper(trim((string) $columnGValue));
        $reason = 'special_section';

        if ($normalizedLabel === 'CASH') {
            $reason = 'cash_row';
        } elseif (str_starts_with($normalizedLabel, 'TOTAL')) {
            $reason = 'grand_total_row';
        }

        $this->recordExtractionEvent(
            status: 'skipped',
            reason: $reason,
            sheetDay: $sheetDay,
            sheetName: $sheetName,
            doctorLabel: $doctorLabel,
            rowData: $rowData,
        );
    }

    /**
     * Append a structured extraction event to the diagnostics buffer.
     *
     * @param  string  $status  `extracted` or `skipped`.
     * @param  string|null  $reason  Skip reason code, null for extracted rows.
     * @param  int  $sheetDay  Day-of-month from the sheet name.
     * @param  string  $sheetName  Sheet tab name.
     * @param  string|null  $doctorLabel  Doctor label for the section.
     * @param  array<string, mixed>  $rowData  Parsed row data with payment fields.
     * @param  string|null  $treatmentText  Combined treatment text override.
     */
    private function recordExtractionEvent(
        string $status,
        ?string $reason,
        int $sheetDay,
        string $sheetName,
        ?string $doctorLabel,
        array $rowData,
        ?string $treatmentText = null,
    ): void {
        $this->extractionEvents[] = [
            'status' => $status,
            'reason' => $reason,
            'sheet_day' => $sheetDay,
            'sheet_name' => $sheetName,
            'excel_row' => (int) ($rowData['raw_row_number'] ?? 0),
            'doctor_label' => $doctorLabel,
            'dhs_aed' => $this->normalizeAmountForLog($rowData['dhs_amount'] ?? null),
            'usd' => $this->normalizeAmountForLog($rowData['usd_amount'] ?? null),
            'visa_aed' => $this->normalizeAmountForLog($rowData['visa_amount'] ?? null),
            'treatment_text' => $treatmentText ?? ($rowData['treatment_text'] ?? null),
            'g_cell' => $rowData['raw_cells']['G'] ?? null,
            'total_cost' => $this->normalizeAmountForLog($rowData['total_cost'] ?? null),
        ];
    }

    /**
     * Determine the skip-reason code for a payment row that is not a daily subtotal.
     *
     * @param  array<string, mixed>  $rowData  Parsed row with raw_cells and payment fields.
     * @return string Reason code (e.g. `cash_row`, `not_a_daily_subtotal`).
     */
    private function resolveSkippedPaymentReason(array $rowData): string
    {
        $treatmentCell = strtoupper(trim((string) (($rowData['raw_cells']['G'] ?? ''))));

        if ($treatmentCell === 'CASH') {
            return 'cash_row';
        }

        if (str_starts_with($treatmentCell, 'TOTAL')) {
            return 'grand_total_row';
        }

        if (str_contains($treatmentCell, 'TRANSFER')) {
            return 'transfer_row';
        }

        return 'not_a_daily_subtotal';
    }

    /**
     * Check whether a row contains any non-zero payment amount.
     *
     * @param  array<string, mixed>  $rowData  Parsed row with amount fields.
     * @return bool True when DHS, USD, VISA, or RUBL has a numeric value.
     */
    private function hasPaymentValues(array $rowData): bool
    {
        foreach (['dhs_amount', 'cheque_amount', 'tabby_amount', 'usd_amount', 'visa_amount', 'rubl_amount'] as $field) {
            if ($this->hasNumericValue($rowData[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize a cell value to a two-decimal string for extraction events.
     *
     * @param  mixed  $value  Raw amount from Excel.
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
     * Decide whether a doctor section should be skipped as stale carry-over data.
     *
     * Skips sections anchored in a prior year with low file numbers or high totals.
     *
     * @param  string|null  $sectionAnchorDate  First patient date in the section (Y-m-d).
     * @param  int  $sectionMaxFileNumber  Highest NF file number seen in the section.
     * @param  Carbon|null  $reportMonth  Target import month.
     * @param  array<string, mixed>  $rowData  Subtotal row with total_cost.
     * @return bool True when the section should be skipped.
     */
    private function shouldSkipStaleSection(?string $sectionAnchorDate, int $sectionMaxFileNumber, ?Carbon $reportMonth, array $rowData): bool
    {
        if ($reportMonth === null || $sectionAnchorDate === null) {
            return false;
        }

        try {
            $anchorDate = Carbon::parse($sectionAnchorDate);
        } catch (\Throwable) {
            return false;
        }

        if ($anchorDate->year >= $reportMonth->year) {
            return false;
        }

        if ($sectionMaxFileNumber >= 6200) {
            return false;
        }

        if ($sectionMaxFileNumber > 0) {
            return true;
        }

        $totalCost = (float) preg_replace('/[^\d.\-]/', '', (string) ($rowData['total_cost'] ?? 0));

        return $totalCost >= 10000;
    }

    /**
     * Extract the numeric sequence from an NF-style file number (e.g. `NF 6123`).
     *
     * @param  mixed  $fileNumber  Raw file number cell value.
     * @return int Parsed sequence number, or 0 when not matched.
     */
    private function extractFileSequenceNumber(mixed $fileNumber): int
    {
        if ($fileNumber === null || $fileNumber === '') {
            return 0;
        }

        if (preg_match('/NF\s*(\d+)/i', (string) $fileNumber, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * Read column G on a row and return it when it looks like a doctor section label.
     *
     * @param  Worksheet  $worksheet  Sheet being scanned.
     * @param  int  $rowIndex  1-based Excel row index.
     * @return string|null Doctor label (e.g. `DR Jack`), or null.
     */
    private function extractDoctorLabelFromRow(Worksheet $worksheet, int $rowIndex): ?string
    {
        $doctorColumn = $this->readCellValue($worksheet, 'G', $rowIndex);

        if ($this->looksLikeDoctorSectionLabel($doctorColumn)) {
            return $doctorColumn;
        }

        return null;
    }

    /**
     * Check whether a cell value matches the `DR …` doctor section header pattern.
     *
     * @param  string|null  $value  Raw cell text.
     * @return bool True for values like `DR Jack` or `DR. Riyadh`.
     */
    private function looksLikeDoctorSectionLabel(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return (bool) preg_match('/^DR\.?\s+[A-Za-z]/i', trim($value));
    }

    /**
     * Detect special section boundary labels that terminate a doctor block.
     *
     * @param  string|null  $value  Raw column-G cell text.
     * @return bool True for OPG, CASH, CLINIC 111, or TOTAL-prefixed labels.
     */
    private function isSpecialSectionLabel(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $normalized = strtoupper(trim($value));

        if (in_array($normalized, ['OPG', 'CASH', 'CLINIC 111'], true)) {
            return true;
        }

        return str_starts_with($normalized, 'TOTAL');
    }

    /**
     * Check whether a row is the Clinic 111 column header row (DATE + NAME).
     *
     * @param  Worksheet  $worksheet  Sheet being scanned.
     * @param  int  $rowIndex  1-based Excel row index.
     * @return bool True when the row contains DATE and NAME headers.
     */
    private function isClinicHeaderRow(Worksheet $worksheet, int $rowIndex): bool
    {
        $rowValues = $this->readRowValues($worksheet, $rowIndex);

        return in_array('DATE', $rowValues, true) && in_array('NAME', $rowValues, true);
    }

    /**
     * Check whether a parsed row represents a patient line (has a real name).
     *
     * @param  array<string, mixed>  $rowData  Parsed row with patient_name.
     * @return bool True when patient_name is non-empty and not the header placeholder.
     */
    private function hasPatientName(array $rowData): bool
    {
        $name = trim((string) ($rowData['patient_name'] ?? ''));

        if ($name === '' || strtoupper($name) === 'NAME') {
            return false;
        }

        return true;
    }

    /**
     * Check whether a row is a doctor daily subtotal (payments without a patient name).
     *
     * Excludes CASH and TOTAL summary rows.
     *
     * @param  array<string, mixed>  $rowData  Parsed row with payment and name fields.
     * @return bool True when the row has payment values and no patient name.
     */
    private function isSectionSubtotalRow(array $rowData): bool
    {
        if ($this->hasPatientName($rowData)) {
            return false;
        }

        $treatmentCell = strtoupper(trim((string) (($rowData['raw_cells']['G'] ?? ''))));

        if ($treatmentCell !== '' && (
            str_starts_with($treatmentCell, 'TOTAL')
            || $treatmentCell === 'CASH'
        )) {
            return false;
        }

        $paymentFields = ['dhs_amount', 'cheque_amount', 'tabby_amount', 'usd_amount', 'visa_amount', 'rubl_amount'];

        foreach ($paymentFields as $field) {
            if ($this->hasNumericValue($rowData[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read the doctor label from the fixed doctor row (row 2) of a Clinic 111 sheet.
     *
     * @param  Worksheet  $worksheet  Day sheet.
     * @return string|null Doctor label from column G, or null.
     */
    private function extractDoctorLabelFromSheet(Worksheet $worksheet): ?string
    {
        return $this->extractDoctorLabelFromRow($worksheet, self::CLINIC_DOCTOR_ROW);
    }

    /**
     * Read a single cell value as trimmed text.
     *
     * @param  Worksheet  $worksheet  Sheet to read from.
     * @param  string  $columnLetter  Excel column letter (e.g. `G`).
     * @param  int  $rowIndex  1-based row index.
     * @return string|null Trimmed cell value, or null when empty.
     */
    private function readCellValue(Worksheet $worksheet, string $columnLetter, int $rowIndex): ?string
    {
        $value = trim((string) $worksheet->getCell($columnLetter . $rowIndex)->getCalculatedValue());

        return $value === '' ? null : $value;
    }

    /**
     * Map Clinic 111 header labels to internal field names and column letters.
     *
     * Handles duplicate DHS/USD columns (first = amount, second = balance).
     *
     * @param  Worksheet  $worksheet  Sheet containing the header row.
     * @param  int  $headerRowIndex  1-based row index of the header (default row 3).
     * @return array<string, string> Field name → column letter.
     */
    private function buildClinicColumnMap(Worksheet $worksheet, int $headerRowIndex = self::CLINIC_HEADER_ROW): array
    {
        $columnMap = [];
        $dhsColumns = [];
        $usdColumns = [];

        foreach ($worksheet->getRowIterator($headerRowIndex, $headerRowIndex) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $header = strtoupper(trim((string) $cell->getCalculatedValue()));
                $columnLetter = $cell->getColumn();

                if ($header === 'DATE' || $header === '+') {
                    $columnMap['work_date'] = $columnLetter;
                }

                if ($header === 'NAME') {
                    $columnMap['patient_name'] = $columnLetter;
                }

                if ($header === 'MRN') {
                    $columnMap['mrn'] = $columnLetter;
                }

                if ($header === 'FILE') {
                    $columnMap['file_number'] = $columnLetter;
                }

                if ($header === 'TOTAL COST') {
                    $columnMap['total_cost'] = $columnLetter;
                }

                if ($header === 'DISC.' || $header === 'DISC' || $header === 'DISCOUNT') {
                    $columnMap['discount_amount'] = $columnLetter;
                }

                if ($header === 'TREATMENT') {
                    $columnMap['treatment_text'] = $columnLetter;
                }

                if ($header === 'DHS') {
                    $dhsColumns[] = $columnLetter;
                }

                if (in_array($header, ['$/EURO', 'USD', '$', 'US DOLLAR'], true)) {
                    $usdColumns[] = $columnLetter;
                }

                if (
                    in_array($header, ['RUBL', 'RUB', 'RUBLES', 'RUBLE'], true)
                    || str_contains($header, 'RUBL')
                ) {
                    $columnMap['rubl_amount'] = $columnLetter;
                }

                if ($header === 'VISA') {
                    $columnMap['visa_amount'] = $columnLetter;
                }

                if (in_array($header, ['CHEQUE', 'CHQ', 'CHECK'], true)) {
                    $columnMap['cheque_amount'] = $columnLetter;
                }

                if ($header === 'TABBY') {
                    $columnMap['tabby_amount'] = $columnLetter;
                }

                if ($header === 'CROWN') {
                    $columnMap['crown_count'] = $columnLetter;
                }
            }
        }

        if (isset($dhsColumns[0])) {
            $columnMap['dhs_amount'] = $dhsColumns[0];
        }

        if (isset($dhsColumns[1])) {
            $columnMap['balance_dhs'] = $dhsColumns[1];
        }

        if (isset($usdColumns[0])) {
            $columnMap['usd_amount'] = $usdColumns[0];
        }

        if (isset($usdColumns[1])) {
            $columnMap['balance_usd'] = $usdColumns[1];
        }

        return $columnMap;
    }

    /**
     * Parse a non-Clinic-111 sheet using generic header detection and column aliases.
     *
     * @param  Worksheet  $worksheet  Active or fallback sheet.
     * @return array<int, array<string, mixed>> Parsed data rows below the header.
     */
    private function parseGenericSheet(Worksheet $worksheet): array
    {
        $headerRowIndex = $this->detectGenericHeaderRow($worksheet);

        if ($headerRowIndex === null) {
            return [];
        }

        $columnMap = $this->buildGenericColumnMap($worksheet, $headerRowIndex);
        $rows = [];

        for ($rowIndex = $headerRowIndex + 1; $rowIndex <= $worksheet->getHighestRow(); $rowIndex++) {
            $rowData = $this->extractRow($worksheet, $rowIndex, $columnMap);

            if ($this->isGenericEmptyRow($rowData)) {
                continue;
            }

            $rows[] = $rowData;
        }

        return $rows;
    }

    /**
     * Find the header row in a generic workbook (looks for DOCTOR/DR in first 20 rows).
     *
     * @param  Worksheet  $worksheet  Sheet to scan.
     * @return int|null 1-based header row index, or null when not found (defaults to 1).
     */
    private function detectGenericHeaderRow(Worksheet $worksheet): ?int
    {
        for ($rowIndex = 1; $rowIndex <= min(20, $worksheet->getHighestRow()); $rowIndex++) {
            $rowValues = $this->readRowValues($worksheet, $rowIndex);

            if (in_array('DOCTOR', $rowValues, true) || in_array('DR', $rowValues, true)) {
                return $rowIndex;
            }
        }

        return 1;
    }

    /**
     * Map generic header labels to internal field names using alias lists.
     *
     * @param  Worksheet  $worksheet  Sheet containing the header row.
     * @param  int  $headerRowIndex  1-based row index of the header.
     * @return array<string, string> Field name → column letter.
     */
    private function buildGenericColumnMap(Worksheet $worksheet, int $headerRowIndex): array
    {
        $aliases = [
            'doctor' => ['DOCTOR', 'DR', 'DOCTOR NAME', 'DR NAME'],
            'work_date' => ['DATE', 'WORK DATE', 'REPORT DATE'],
            'patient_name' => ['PATIENT', 'PATIENT NAME', 'NAME'],
            'mrn' => ['MRN', 'MEDICAL RECORD'],
            'file_number' => ['FILE', 'FILE NO', 'FILE NUMBER', 'FILENO'],
            'treatment_text' => ['TREATMENT', 'TREATMENTS', 'WORK', 'PROCEDURE', 'TREATMENT TEXT'],
            'total_cost' => ['TOTAL COST', 'COST', 'TREATMENT VALUE'],
            'discount_amount' => ['DISCOUNT', 'DISC', 'DISC.'],
            'dhs_amount' => ['DHS', 'AED', 'CASH DHS'],
            'cheque_amount' => ['CHEQUE', 'CHQ', 'CHECK'],
            'tabby_amount' => ['TABBY'],
            'usd_amount' => ['USD', 'CASH USD', '$/EURO', '$'],
            'visa_amount' => ['VISA', 'CARD', 'CREDIT CARD'],
            'balance_dhs' => ['BALANCE DHS', 'BAL DHS'],
            'balance_usd' => ['BALANCE USD', 'BAL USD'],
            'crown_count' => ['CROWN', 'CROWNS', 'CROWN COUNT'],
        ];

        $columnMap = [];

        foreach ($worksheet->getRowIterator($headerRowIndex, $headerRowIndex) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $header = strtoupper(trim((string) $cell->getCalculatedValue()));
                $columnLetter = $cell->getColumn();

                foreach ($aliases as $field => $fieldAliases) {
                    if (in_array($header, $fieldAliases, true) && ! isset($columnMap[$field])) {
                        $columnMap[$field] = $columnLetter;
                    }
                }
            }
        }

        return $columnMap;
    }

    /**
     * Read one Excel row into a field dictionary using the column map.
     *
     * Includes raw cell values and the 1-based Excel row number.
     *
     * @param  Worksheet  $worksheet  Sheet to read from.
     * @param  int  $rowIndex  1-based row index.
     * @param  array<string, string>  $columnMap  Field name → column letter.
     * @return array<string, mixed> Parsed row with mapped fields and raw_cells.
     */
    private function extractRow(Worksheet $worksheet, int $rowIndex, array $columnMap): array
    {
        $rowData = ['raw_row_number' => $rowIndex];
        $rawCells = [];

        foreach ($worksheet->getRowIterator($rowIndex, $rowIndex) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $rawCells[$cell->getColumn()] = $this->sanitizeCellValue($cell->getCalculatedValue());
            }
        }

        $rowData['raw_cells'] = $rawCells;

        foreach ($columnMap as $field => $columnLetter) {
            if (array_key_exists($columnLetter, $rawCells)) {
                $rowData[$field] = $rawCells[$columnLetter];
            } else {
                $rowData[$field] = null;
            }
        }

        return $rowData;
    }

    /**
     * Read all cell values on a row as uppercased trimmed strings.
     *
     * @param  Worksheet  $worksheet  Sheet to read from.
     * @param  int  $rowIndex  1-based row index.
     * @return array<int, string> Ordered list of cell values on the row.
     */
    private function readRowValues(Worksheet $worksheet, int $rowIndex): array
    {
        $rowValues = [];

        foreach ($worksheet->getRowIterator($rowIndex, $rowIndex) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $rowValues[] = strtoupper(trim((string) $cell->getCalculatedValue()));
            }
        }

        return $rowValues;
    }

    /**
     * Check whether a work-date cell has a non-empty value.
     *
     * @param  mixed  $value  Raw date cell value.
     * @return bool True when the value is present and non-blank.
     */
    private function hasWorkDateValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        return true;
    }

    /**
     * Parse a work-date cell into a Y-m-d string.
     *
     * Handles Excel serial numbers and common date string formats.
     *
     * @param  mixed  $value  Raw date cell value.
     * @return string|null ISO date string, or null when unparseable.
     */
    private function parseWorkDateValue(mixed $value): ?string
    {
        if (is_numeric($value)) {
            return Carbon::createFromTimestampUTC((int) (($value - 25569) * 86400))->toDateString();
        }

        $stringValue = strtoupper(trim((string) $value));

        if (in_array($stringValue, ['DATE', '+', ''], true)) {
            return null;
        }

        try {
            $cleanValue = str_replace(' ', '', (string) $value);

            return Carbon::parse($cleanValue)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Trim and strip HTML tags from a cell value for safe storage.
     *
     * @param  mixed  $value  Raw cell value.
     * @return mixed Sanitized string, or original non-string value.
     */
    private function sanitizeCellValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim(strip_tags($value));
        }

        return $value;
    }

    /**
     * Check whether a Clinic 111 row is empty (no name, treatment, or payments).
     *
     * @param  array<string, mixed>  $rowData  Parsed row dictionary.
     * @return bool True when all significant fields are blank/zero.
     */
    private function isClinicEmptyRow(array $rowData): bool
    {
        if (! empty($rowData['patient_name'])) {
            return false;
        }

        if (! empty($rowData['treatment_text'])) {
            return false;
        }

        $paymentFields = ['dhs_amount', 'cheque_amount', 'tabby_amount', 'usd_amount', 'visa_amount'];

        foreach ($paymentFields as $field) {
            if ($this->hasNumericValue($rowData[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether a generic-format row has no significant data.
     *
     * @param  array<string, mixed>  $rowData  Parsed row dictionary.
     * @return bool True when doctor, patient, treatment, and payments are all empty.
     */
    private function isGenericEmptyRow(array $rowData): bool
    {
        $significantFields = ['doctor', 'patient_name', 'treatment_text', 'dhs_amount', 'cheque_amount', 'tabby_amount', 'usd_amount', 'visa_amount'];

        foreach ($significantFields as $field) {
            if (! empty($rowData[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether a cell value represents a non-zero numeric amount.
     *
     * @param  mixed  $value  Raw amount cell value.
     * @return bool True when the normalized value is a non-zero number.
     */
    private function hasNumericValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $normalized = preg_replace('/[^\d.\-]/', '', (string) $value);

        if ($normalized === '' || $normalized === '-') {
            return false;
        }

        return (float) $normalized != 0.0;
    }
}
