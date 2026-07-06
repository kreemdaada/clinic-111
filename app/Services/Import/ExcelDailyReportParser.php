<?php

namespace App\Services\Import;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

    private const SKIPPED_SHEET_NAMES = ['SCHEDULE', 'SHEET1'];

    public function __construct(
        private readonly ImportDiagnosticsRecorder $diagnosticsRecorder,
        private readonly Clinic111SectionParser $clinic111SectionParser,
        private readonly SpreadsheetRowReader $spreadsheetRowReader,
    ) {}

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
        $this->diagnosticsRecorder->reset();

        $spreadsheet = $this->loadSpreadsheet($filePath);

        if ($this->isClinic111Workbook($spreadsheet)) {
            $rows = $this->parseClinic111Workbook($spreadsheet, $reportMonth);

            return [
                'rows' => $rows,
                'events' => $this->diagnosticsRecorder->all(),
            ];
        }

        return [
            'rows' => $this->parseGenericSheet($spreadsheet->getActiveSheet()),
            'events' => $this->diagnosticsRecorder->all(),
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
        $headerValues = $this->spreadsheetRowReader->readRowValues($worksheet, self::CLINIC_HEADER_ROW);

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

            $sheetRows = $this->clinic111SectionParser->parse($worksheet, $sheetName, $reportMonth, $this->diagnosticsRecorder);
            $parsedRows = array_merge($parsedRows, $sheetRows);
        }

        return $parsedRows;
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
            $rowData = $this->spreadsheetRowReader->extractRow($worksheet, $rowIndex, $columnMap);

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
            $rowValues = $this->spreadsheetRowReader->readRowValues($worksheet, $rowIndex);

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
}
