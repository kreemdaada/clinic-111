<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Maps Clinic 111 Excel header labels to internal field names and column letters.
 *
 * Handles duplicate DHS/USD columns (first = amount, second = balance).
 */
final class Clinic111HeaderMapBuilder
{
    /**
     * @param  Worksheet  $worksheet  Sheet containing the header row.
     * @param  int  $headerRowIndex  1-based row index of the header.
     * @return array<string, string> Field name → column letter.
     */
    public function build(Worksheet $worksheet, int $headerRowIndex): array
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

                if (in_array($header, ['NURSE', 'REMARK', 'REMARKS', 'STAFF', 'TECH', 'TECHNICIAN'], true)) {
                    $columnMap['nurse_alias'] = $columnLetter;
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
}
