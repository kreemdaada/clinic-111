<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads worksheet rows into normalized cell dictionaries for import parsing.
 */
final class SpreadsheetRowReader
{
    /**
     * @return array<int, string>
     */
    public function readRowValues(Worksheet $worksheet, int $rowIndex): array
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
     * @param  array<string, string>  $columnMap  Field name → column letter.
     * @return array<string, mixed>
     */
    public function extractRow(Worksheet $worksheet, int $rowIndex, array $columnMap): array
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

    private function sanitizeCellValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim(strip_tags($value));
        }

        return $value;
    }
}
