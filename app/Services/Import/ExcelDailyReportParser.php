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
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $filePath, ?Carbon $reportMonth = null): array
    {
        return $this->parseWithDiagnostics($filePath, $reportMonth)['rows'];
    }

    /**
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

    private function loadSpreadsheet(string $filePath): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($filePath);

        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        return $reader->load($filePath);
    }

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

    private function isDaySheetName(string $sheetName): bool
    {
        $normalizedName = trim($sheetName);

        if (in_array(strtoupper($normalizedName), self::SKIPPED_SHEET_NAMES, true)) {
            return false;
        }

        return ctype_digit($normalizedName) && (int) $normalizedName >= 1 && (int) $normalizedName <= 31;
    }

    private function isClinic111Sheet(Worksheet $worksheet): bool
    {
        $headerValues = $this->readRowValues($worksheet, self::CLINIC_HEADER_ROW);

        return in_array('DATE', $headerValues, true)
            && in_array('NAME', $headerValues, true)
            && in_array('TREATMENT', $headerValues, true);
    }

    /**
     * @return array<int, array<string, mixed>>
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
     * @return array<int, array<string, mixed>>
     */
    private function parseClinic111Sheet(Worksheet $worksheet, string $sheetName, ?Carbon $reportMonth): array
    {
        $parsedRows = [];
        $currentDoctor = null;
        $columnMap = null;
        $sectionPatientTreatments = [];
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
                $sectionAnchorDate = null;
                $sectionMaxFileNumber = 0;

                continue;
            }

            $doctorLabel = $this->extractDoctorLabelFromRow($worksheet, $rowIndex);

            if ($doctorLabel !== null) {
                $currentDoctor = $doctorLabel;
                $columnMap = null;
                $sectionPatientTreatments = [];
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
                $sectionAnchorDate = null;
                $sectionMaxFileNumber = 0;

                continue;
            }

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
            $sectionAnchorDate = null;
            $sectionMaxFileNumber = 0;
        }

        return $parsedRows;
    }

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
     * @param  array<string, mixed>  $rowData
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
     * @param  array<string, mixed>  $rowData
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
     * @param  array<string, mixed>  $rowData
     */
    private function hasPaymentValues(array $rowData): bool
    {
        foreach (['dhs_amount', 'usd_amount', 'visa_amount', 'rubl_amount'] as $field) {
            if ($this->hasNumericValue($rowData[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

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
     * @param  array<string, mixed>  $rowData
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

    private function extractDoctorLabelFromRow(Worksheet $worksheet, int $rowIndex): ?string
    {
        $doctorColumn = $this->readCellValue($worksheet, 'G', $rowIndex);

        if ($this->looksLikeDoctorSectionLabel($doctorColumn)) {
            return $doctorColumn;
        }

        return null;
    }

    private function looksLikeDoctorSectionLabel(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return (bool) preg_match('/^DR\.?\s+[A-Za-z]/i', trim($value));
    }

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

    private function isClinicHeaderRow(Worksheet $worksheet, int $rowIndex): bool
    {
        $rowValues = $this->readRowValues($worksheet, $rowIndex);

        return in_array('DATE', $rowValues, true) && in_array('NAME', $rowValues, true);
    }

    /**
     * @param  array<string, mixed>  $rowData
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
     * @param  array<string, mixed>  $rowData
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

        $paymentFields = ['dhs_amount', 'usd_amount', 'visa_amount', 'rubl_amount'];

        foreach ($paymentFields as $field) {
            if ($this->hasNumericValue($rowData[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function extractDoctorLabelFromSheet(Worksheet $worksheet): ?string
    {
        return $this->extractDoctorLabelFromRow($worksheet, self::CLINIC_DOCTOR_ROW);
    }

    private function readCellValue(Worksheet $worksheet, string $columnLetter, int $rowIndex): ?string
    {
        $value = trim((string) $worksheet->getCell($columnLetter.$rowIndex)->getCalculatedValue());

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, string>
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

                if (in_array($header, ['RUBL', 'RUB', 'RUBLES', 'RUBLE'], true)
                    || str_contains($header, 'RUBL')) {
                    $columnMap['rubl_amount'] = $columnLetter;
                }

                if ($header === 'VISA') {
                    $columnMap['visa_amount'] = $columnLetter;
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
     * @return array<int, array<string, mixed>>
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
     * @return array<string, string>
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
     * @param  array<string, string>  $columnMap
     * @return array<string, mixed>
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
     * @return array<int, string>
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

    private function sanitizeCellValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim(strip_tags($value));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $rowData
     */
    private function isClinicEmptyRow(array $rowData): bool
    {
        if (! empty($rowData['patient_name'])) {
            return false;
        }

        if (! empty($rowData['treatment_text'])) {
            return false;
        }

        $paymentFields = ['dhs_amount', 'usd_amount', 'visa_amount'];

        foreach ($paymentFields as $field) {
            if ($this->hasNumericValue($rowData[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $rowData
     */
    private function isGenericEmptyRow(array $rowData): bool
    {
        $significantFields = ['doctor', 'patient_name', 'treatment_text', 'dhs_amount', 'usd_amount', 'visa_amount'];

        foreach ($significantFields as $field) {
            if (! empty($rowData[$field])) {
                return false;
            }
        }

        return true;
    }

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
