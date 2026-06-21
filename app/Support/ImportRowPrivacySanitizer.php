<?php

namespace App\Support;

/**
 * Removes patient identifiers from parsed Excel row data before persistence.
 */
class ImportRowPrivacySanitizer
{
    /** @var array<int, string> */
    private const PII_KEYS = [
        'patient_name',
        'mrn',
        'file_number',
    ];

    /**
     * @param  array<string, mixed>  $parsedRow  Parsed Excel row dictionary.
     * @return array<string, mixed> Copy with patient identifier keys removed.
     */
    public function sanitize(array $parsedRow): array
    {
        $sanitized = $parsedRow;

        foreach (self::PII_KEYS as $key) {
            unset($sanitized[$key]);
        }

        if (isset($sanitized['raw_cells']) && is_array($sanitized['raw_cells'])) {
            $sanitized['raw_cells'] = $this->sanitizeRawCells($sanitized['raw_cells'], $parsedRow);
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $rawCells  Column letter → cell value map.
     * @param  array<string, mixed>  $parsedRow  Original row (for column letter lookup).
     * @return array<string, mixed> Raw cells with PII column values redacted.
     */
    private function sanitizeRawCells(array $rawCells, array $parsedRow): array
    {
        $piiColumns = [];

        foreach (['patient_name', 'mrn', 'file_number'] as $field) {
            if (isset($parsedRow['_column_map'][$field])) {
                $piiColumns[] = strtoupper((string) $parsedRow['_column_map'][$field]);
            }
        }

        if ($piiColumns === []) {
            return $rawCells;
        }

        foreach ($piiColumns as $column) {
            if (array_key_exists($column, $rawCells)) {
                $rawCells[$column] = '[REDACTED]';
            }
        }

        return $rawCells;
    }
}
