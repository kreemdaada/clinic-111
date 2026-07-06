<?php

namespace App\Services\Import;

use App\Support\OpgTreatmentLabelNormalizer;

/**
 * Classifies extracted Clinic 111 Excel rows by type (header, doctor, activity, subtotal, OPG, etc.).
 */
final class Clinic111RowClassifier
{
    /**
     * Check whether a cell value matches the `DR …` doctor section header pattern.
     *
     * @param  string|null  $value  Raw cell text.
     */
    public function looksLikeDoctorSectionLabel(?string $value): bool
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
     */
    public function isSpecialSectionLabel(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $normalized = strtoupper(trim($value));

        if (in_array($normalized, ['CASH', 'CLINIC 111'], true)) {
            return true;
        }

        return str_starts_with($normalized, 'TOTAL');
    }

    /**
     * Check whether a row is the Clinic 111 column header row (DATE + NAME).
     *
     * @param  array<int, string>  $rowValues  Uppercased cell values from the row.
     */
    public function isClinicHeaderRow(array $rowValues): bool
    {
        return in_array('DATE', $rowValues, true) && in_array('NAME', $rowValues, true);
    }

    /**
     * Check whether a parsed row represents a patient line (has a real name).
     *
     * @param  array<string, mixed>  $rowData  Parsed row with patient_name.
     */
    public function hasPatientName(array $rowData): bool
    {
        $name = trim((string) ($rowData['patient_name'] ?? ''));

        if ($name === '' || strtoupper($name) === 'NAME') {
            return false;
        }

        return true;
    }

    /**
     * Nameless row with treatment text in column G — activity line, not a section subtotal.
     *
     * @param  array<string, mixed>  $rowData
     */
    public function hasSectionActivityTreatment(array $rowData): bool
    {
        if ($this->hasPatientName($rowData)) {
            return false;
        }

        $treatmentText = trim((string) ($rowData['treatment_text'] ?? ''));

        if ($treatmentText === '' || strtoupper($treatmentText) === 'TREATMENT') {
            return false;
        }

        if ($this->looksLikeDoctorSectionLabel($treatmentText)) {
            return false;
        }

        $normalized = strtoupper($treatmentText);

        if (str_starts_with($normalized, 'TOTAL') || $normalized === 'CASH') {
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
     */
    public function isSectionSubtotalRow(array $rowData): bool
    {
        if ($this->hasPatientName($rowData)) {
            return false;
        }

        if ($this->hasSectionActivityTreatment($rowData)) {
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
     * Determine the skip-reason code for a payment row that is not a daily subtotal.
     *
     * @param  array<string, mixed>  $rowData  Parsed row with raw_cells and payment fields.
     * @return string Reason code (e.g. `cash_row`, `not_a_daily_subtotal`).
     */
    public function resolveSkippedPaymentReason(array $rowData): string
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
     */
    public function hasPaymentValues(array $rowData): bool
    {
        foreach (['dhs_amount', 'cheque_amount', 'tabby_amount', 'usd_amount', 'visa_amount', 'rubl_amount'] as $field) {
            if ($this->hasNumericValue($rowData[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $rowData
     */
    public function isOpgActivityRow(array $rowData): bool
    {
        $treatmentText = trim((string) ($rowData['treatment_text'] ?? ''));

        if (! OpgTreatmentLabelNormalizer::isOpgTreatmentLabel($treatmentText)) {
            return false;
        }

        if ($this->hasPaymentValues($rowData)) {
            return true;
        }

        return $this->hasPatientName($rowData);
    }

    /**
     * Check whether a cell value represents a non-zero numeric amount.
     *
     * @param  mixed  $value  Raw amount cell value.
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
