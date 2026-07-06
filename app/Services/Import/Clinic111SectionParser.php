<?php

namespace App\Services\Import;

use App\Support\OpgClinicDoctor;
use App\Support\OpgTreatmentLabelNormalizer;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Parses one Clinic 111 day sheet: doctor sections, OPG blocks, subtotals, and diagnostics.
 */
final class Clinic111SectionParser
{
    public function __construct(
        private readonly Clinic111HeaderMapBuilder $clinic111HeaderMapBuilder,
        private readonly StaleSectionPolicy $staleSectionPolicy,
        private readonly Clinic111RowClassifier $clinic111RowClassifier,
        private readonly SpreadsheetRowReader $spreadsheetRowReader,
    ) {}

    /**
     * Parse one Clinic 111 day sheet into doctor daily-subtotal rows.
     *
     * @param  Worksheet  $worksheet  Day sheet (tab name = day of month).
     * @param  string  $sheetName  Sheet tab name.
     * @param  Carbon|null  $reportMonth  Month anchor for stale-section filtering.
     * @param  ImportDiagnosticsRecorder  $diagnosticsRecorder  Shared diagnostics buffer for this import.
     * @return array<int, array<string, mixed>> Extracted subtotal rows for this sheet.
     */
    public function parse(
        Worksheet $worksheet,
        string $sheetName,
        ?Carbon $reportMonth,
        ImportDiagnosticsRecorder $diagnosticsRecorder,
    ): array {
        $parsedRows = [];
        $currentDoctor = null;
        $columnMap = null;
        $sectionPatientTreatments = [];
        $sectionPatientPayments = $this->emptySectionPaymentTotals();
        $sectionAnchorDate = null;
        $sectionMaxFileNumber = 0;
        $sheetDay = (int) trim($sheetName);
        $inOpgSection = false;
        $opgColumnMap = null;

        for ($rowIndex = 1; $rowIndex <= $worksheet->getHighestRow(); $rowIndex++) {
            $columnGValue = $this->readCellValue($worksheet, 'G', $rowIndex);

            if ($this->clinic111RowClassifier->isSpecialSectionLabel($columnGValue)) {
                $this->logSkippedSpecialSectionRow(
                    $worksheet,
                    $rowIndex,
                    $sheetDay,
                    $sheetName,
                    $currentDoctor,
                    $columnGValue,
                    $columnMap,
                    $diagnosticsRecorder,
                );

                $currentDoctor = null;
                $columnMap = null;
                $sectionPatientTreatments = [];
                $sectionPatientPayments = $this->emptySectionPaymentTotals();
                $sectionAnchorDate = null;
                $sectionMaxFileNumber = 0;
                $inOpgSection = false;
                $opgColumnMap = null;

                continue;
            }

            if (! $inOpgSection && OpgTreatmentLabelNormalizer::isBareSectionMarker($columnGValue)) {
                $currentDoctor = null;
                $columnMap = null;
                $sectionPatientTreatments = [];
                $sectionPatientPayments = $this->emptySectionPaymentTotals();
                $sectionAnchorDate = null;
                $sectionMaxFileNumber = 0;
                $inOpgSection = true;
                $opgColumnMap = null;

                continue;
            }

            $doctorLabel = $this->extractDoctorLabelFromRow($worksheet, $rowIndex);

            if ($doctorLabel !== null) {
                $inOpgSection = false;
                $opgColumnMap = null;
                if (
                    $currentDoctor !== null
                    && $columnMap !== null
                    && ! $this->doctorLabelsMatch($currentDoctor, $doctorLabel)
                    && $this->sectionHasAccumulatedData($sectionPatientTreatments, $sectionPatientPayments)
                ) {
                    $hybridRowData = $this->spreadsheetRowReader->extractRow($worksheet, $rowIndex, $columnMap);

                    if (
                        ! $this->clinic111RowClassifier->hasPatientName($hybridRowData)
                        && $this->clinic111RowClassifier->hasPaymentValues($hybridRowData)
                    ) {
                        $emittedRow = $this->emitSectionSubtotal(
                            $currentDoctor,
                            $sectionPatientTreatments,
                            $sectionPatientPayments,
                            $sectionAnchorDate,
                            $sectionMaxFileNumber,
                            $reportMonth,
                            $hybridRowData,
                            $sheetDay,
                            $sheetName,
                            $diagnosticsRecorder,
                        );

                        if ($emittedRow !== null) {
                            $parsedRows[] = $emittedRow;
                        }

                        $sectionPatientTreatments = [];
                        $sectionPatientPayments = $this->emptySectionPaymentTotals();
                        $sectionAnchorDate = null;
                        $sectionMaxFileNumber = 0;
                    }
                }

                $currentDoctor = $doctorLabel;
                $columnMap = null;
                $sectionPatientTreatments = [];
                $sectionPatientPayments = $this->emptySectionPaymentTotals();
                $sectionAnchorDate = null;
                $sectionMaxFileNumber = 0;

                continue;
            }

            if ($this->clinic111RowClassifier->isClinicHeaderRow($this->spreadsheetRowReader->readRowValues($worksheet, $rowIndex))) {
                if ($inOpgSection) {
                    $opgColumnMap = $this->clinic111HeaderMapBuilder->build($worksheet, $rowIndex);
                } else {
                    $columnMap = $this->clinic111HeaderMapBuilder->build($worksheet, $rowIndex);
                }

                continue;
            }

            if ($inOpgSection) {
                if ($opgColumnMap === null) {
                    continue;
                }

                $rowData = $this->spreadsheetRowReader->extractRow($worksheet, $rowIndex, $opgColumnMap);

                if ($this->clinic111RowClassifier->isOpgActivityRow($rowData)) {
                    $emittedRow = $this->emitOpgActivityRow($rowData, $sheetDay, $sheetName, $opgColumnMap, $diagnosticsRecorder);

                    if ($emittedRow !== null) {
                        $parsedRows[] = $emittedRow;
                    }
                }

                continue;
            }

            if ($currentDoctor === null || $columnMap === null) {
                continue;
            }

            $rowData = $this->spreadsheetRowReader->extractRow($worksheet, $rowIndex, $columnMap);

            if ($this->clinic111RowClassifier->hasPatientName($rowData)) {
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

            if ($this->clinic111RowClassifier->hasSectionActivityTreatment($rowData)) {
                if ($sectionAnchorDate === null) {
                    $sectionAnchorDate = $this->parseWorkDateValue($rowData['work_date'] ?? null);
                }

                $treatmentText = trim((string) ($rowData['treatment_text'] ?? ''));

                if ($treatmentText !== '') {
                    $sectionPatientTreatments[] = $treatmentText;
                }

                $this->accumulateSectionPatientPayments($sectionPatientPayments, $rowData);

                continue;
            }

            if (! $this->clinic111RowClassifier->isSectionSubtotalRow($rowData)) {
                if ($this->clinic111RowClassifier->hasPaymentValues($rowData)) {
                    $this->recordExtractionEvent(
                        $diagnosticsRecorder,
                        status: 'skipped',
                        reason: $this->clinic111RowClassifier->resolveSkippedPaymentReason($rowData),
                        sheetDay: $sheetDay,
                        sheetName: $sheetName,
                        doctorLabel: $currentDoctor,
                        rowData: $rowData,
                    );
                }

                continue;
            }

            $emittedRow = $this->emitSectionSubtotal(
                $currentDoctor,
                $sectionPatientTreatments,
                $sectionPatientPayments,
                $sectionAnchorDate,
                $sectionMaxFileNumber,
                $reportMonth,
                $rowData,
                $sheetDay,
                $sheetName,
                $diagnosticsRecorder,
            );

            if ($emittedRow !== null) {
                $parsedRows[] = $emittedRow;
            }

            $sectionPatientTreatments = [];
            $sectionPatientPayments = $this->emptySectionPaymentTotals();
            $sectionAnchorDate = null;
            $sectionMaxFileNumber = 0;
        }

        return $parsedRows;
    }

    /**
     * @param  array<int, string>  $sectionPatientTreatments
     * @param  array<string, float>  $sectionPatientPayments
     * @param  array<string, mixed>  $rowData
     * @return array<string, mixed>|null
     */
    private function emitSectionSubtotal(
        string $currentDoctor,
        array $sectionPatientTreatments,
        array $sectionPatientPayments,
        ?string $sectionAnchorDate,
        int $sectionMaxFileNumber,
        ?Carbon $reportMonth,
        array $rowData,
        int $sheetDay,
        string $sheetName,
        ImportDiagnosticsRecorder $diagnosticsRecorder,
    ): ?array {
        if ($this->staleSectionPolicy->shouldSkip($sectionAnchorDate, $sectionMaxFileNumber, $reportMonth, $rowData, $sheetDay)) {
            $this->recordExtractionEvent(
                $diagnosticsRecorder,
                status: 'skipped',
                reason: 'stale_section',
                sheetDay: $sheetDay,
                sheetName: $sheetName,
                doctorLabel: $currentDoctor,
                rowData: $rowData,
                treatmentText: implode(' | ', $sectionPatientTreatments),
            );

            return null;
        }

        $rowData = $this->applySectionPaymentTotals($rowData, $sectionPatientPayments);
        $rowData['doctor'] = $currentDoctor;
        $rowData['sheet_name'] = $sheetName;
        $rowData['sheet_day'] = $sheetDay;
        $rowData['work_date'] = null;
        $rowData['treatment_text'] = implode(' | ', $sectionPatientTreatments);
        $rowData['is_daily_subtotal'] = true;

        $this->recordExtractionEvent(
            $diagnosticsRecorder,
            status: 'extracted',
            reason: null,
            sheetDay: $sheetDay,
            sheetName: $sheetName,
            doctorLabel: $currentDoctor,
            rowData: $rowData,
            treatmentText: $rowData['treatment_text'],
        );

        return $rowData;
    }

    /**
     * @param  array<int, string>  $sectionPatientTreatments
     * @param  array<string, float>  $sectionPatientPayments
     */
    private function sectionHasAccumulatedData(array $sectionPatientTreatments, array $sectionPatientPayments): bool
    {
        if ($sectionPatientTreatments !== []) {
            return true;
        }

        foreach ($sectionPatientPayments as $amount) {
            if ((float) $amount != 0.0) {
                return true;
            }
        }

        return false;
    }

    private function doctorLabelsMatch(string $currentDoctor, string $nextDoctorLabel): bool
    {
        return strtoupper(trim($currentDoctor)) === strtoupper(trim($nextDoctorLabel));
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
     * @param  array<string, string>|null  $columnMap
     */
    private function logSkippedSpecialSectionRow(
        Worksheet $worksheet,
        int $rowIndex,
        int $sheetDay,
        string $sheetName,
        ?string $doctorLabel,
        ?string $columnGValue,
        ?array $columnMap,
        ImportDiagnosticsRecorder $diagnosticsRecorder,
    ): void {
        if ($columnMap === null) {
            return;
        }

        $rowData = $this->spreadsheetRowReader->extractRow($worksheet, $rowIndex, $columnMap);

        if (! $this->clinic111RowClassifier->hasPaymentValues($rowData)) {
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
            $diagnosticsRecorder,
            status: 'skipped',
            reason: $reason,
            sheetDay: $sheetDay,
            sheetName: $sheetName,
            doctorLabel: $doctorLabel,
            rowData: $rowData,
        );
    }

    private function recordExtractionEvent(
        ImportDiagnosticsRecorder $diagnosticsRecorder,
        string $status,
        ?string $reason,
        int $sheetDay,
        string $sheetName,
        ?string $doctorLabel,
        array $rowData,
        ?string $treatmentText = null,
    ): void {
        $diagnosticsRecorder->record([
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
        ]);
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

        if ($this->clinic111RowClassifier->looksLikeDoctorSectionLabel($doctorColumn)) {
            return $doctorColumn;
        }

        return null;
    }

    /**
     * @param  array<string, string>  $columnMap
     * @return array<string, mixed>|null
     */
    private function emitOpgActivityRow(
        array $rowData,
        int $sheetDay,
        string $sheetName,
        array $columnMap,
        ImportDiagnosticsRecorder $diagnosticsRecorder,
    ): ?array {
        $parsed = OpgTreatmentLabelNormalizer::parse((string) ($rowData['treatment_text'] ?? ''));

        if ($parsed === null) {
            return null;
        }

        $columnNurseAlias = trim((string) ($rowData['nurse_alias'] ?? ''));
        $columnNurseAlias = $columnNurseAlias !== '' ? $columnNurseAlias : null;

        $rowData['doctor'] = OpgClinicDoctor::IMPORT_LABEL;
        $rowData['sheet_name'] = $sheetName;
        $rowData['sheet_day'] = $sheetDay;
        $rowData['is_opg_section'] = true;
        $rowData['opg_treatment_code'] = $parsed['code'];
        $rowData['opg_quantity'] = $parsed['quantity'];
        $rowData['nurse_alias'] = $columnNurseAlias;
        $rowData['opg_treatment_nurse_alias'] = $parsed['nurse_alias'] ?? null;
        $rowData['unmapped_nurse_candidate'] = $columnNurseAlias === null
            ? $this->extractNurseAliasFromUnmappedCells($rowData, $columnMap)
            : null;
        $rowData['treatment_text'] = OpgTreatmentLabelNormalizer::treatmentText($parsed['code'], $parsed['quantity']);
        $rowData['is_daily_subtotal'] = false;

        $this->recordExtractionEvent(
            $diagnosticsRecorder,
            status: 'extracted',
            reason: null,
            sheetDay: $sheetDay,
            sheetName: $sheetName,
            doctorLabel: OpgClinicDoctor::IMPORT_LABEL,
            rowData: $rowData,
            treatmentText: $rowData['treatment_text'],
        );

        return $rowData;
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @param  array<string, string>  $columnMap
     */
    private function extractNurseAliasFromUnmappedCells(array $rowData, array $columnMap): ?string
    {
        $mappedColumns = array_values($columnMap);
        $knownValues = array_filter([
            trim((string) ($rowData['patient_name'] ?? '')),
            trim((string) ($rowData['treatment_text'] ?? '')),
            trim((string) ($rowData['mrn'] ?? '')),
            trim((string) ($rowData['file_number'] ?? '')),
        ]);

        foreach ($rowData['raw_cells'] ?? [] as $column => $value) {
            if (in_array($column, $mappedColumns, true)) {
                continue;
            }

            $text = trim((string) $value);

            if ($text === '' || is_numeric($value)) {
                continue;
            }

            if (in_array($text, $knownValues, true)) {
                continue;
            }

            if (preg_match('/^[A-Za-z][A-Za-z\s.\'-]{1,30}$/u', $text) !== 1) {
                continue;
            }

            return $text;
        }

        return null;
    }

    private function readCellValue(Worksheet $worksheet, string $columnLetter, int $rowIndex): ?string
    {
        $value = trim((string) $worksheet->getCell($columnLetter.$rowIndex)->getCalculatedValue());

        return $value === '' ? null : $value;
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
}
