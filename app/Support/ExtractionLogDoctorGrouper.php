<?php

namespace App\Support;

/**
 * Normalizes extraction log rows into one summary row per known doctor.
 *
 * Unknown Excel labels (e.g. "Dr. Anas") are grouped for error display — never duplicated as "DR Jack" + "JACK".
 */
final class ExtractionLogDoctorGrouper
{
    /**
     * @param  array<string, mixed>  $log  Extraction log document.
     * @return array<string, array<string, mixed>> Canonical doctor code → totals.
     */
    public static function knownDoctorTotals(array $log): array
    {
        $totals = [];

        foreach ($log['imported_rows'] ?? [] as $row) {
            $resolved = DoctorCodeResolver::resolve(
                isset($row['doctor_code']) ? (string) $row['doctor_code'] : null,
                isset($row['doctor_label']) ? (string) $row['doctor_label'] : null,
            );

            if (! $resolved['is_known']) {
                continue;
            }

            $code = $resolved['code'];
            self::ensureBucket($totals, $code, $resolved['label']);

            $totals[$code]['day_count']++;
            $totals[$code]['paid_total_aed'] = bcadd(
                $totals[$code]['paid_total_aed'],
                (string) ($row['paid_total_aed'] ?? '0'),
                2,
            );
            $totals[$code]['lab_total_aed'] = bcadd(
                $totals[$code]['lab_total_aed'],
                (string) ($row['lab_total_aed'] ?? '0'),
                2,
            );
            $totals[$code]['issue_count'] += count($row['issues'] ?? []);
        }

        foreach ($log['skipped_rows'] ?? [] as $row) {
            $resolved = DoctorCodeResolver::resolve(
                isset($row['doctor_code']) ? (string) $row['doctor_code'] : null,
                isset($row['doctor_label']) ? (string) $row['doctor_label'] : null,
            );

            if (! $resolved['is_known']) {
                continue;
            }

            $code = $resolved['code'];
            self::ensureBucket($totals, $code, $resolved['label']);
            $totals[$code]['skipped_rows_on_sheet']++;
        }

        foreach ($log['unresolved_rows'] ?? [] as $row) {
            $resolved = DoctorCodeResolver::resolve(
                null,
                isset($row['doctor_label']) ? (string) $row['doctor_label'] : null,
            );

            if (! $resolved['is_known']) {
                continue;
            }

            $code = $resolved['code'];
            self::ensureBucket($totals, $code, $resolved['label']);
            $totals[$code]['unresolved_rows'] = ($totals[$code]['unresolved_rows'] ?? 0) + 1;
        }

        uksort($totals, fn (string $a, string $b): int => array_search($a, DoctorCodeResolver::KNOWN_CODES, true)
            <=> array_search($b, DoctorCodeResolver::KNOWN_CODES, true));

        return $totals;
    }

    /**
     * @param  array<string, mixed>  $log  Extraction log document.
     * @return array<int, array<string, mixed>> Unknown doctor label → error rows.
     */
    public static function unknownDoctorErrors(array $log): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];

        foreach ($log['unresolved_rows'] ?? [] as $row) {
            self::appendUnknownRow($groups, $row, 'unresolved_doctor');
        }

        foreach ($log['skipped_rows'] ?? [] as $row) {
            $resolved = DoctorCodeResolver::resolve(
                isset($row['doctor_code']) ? (string) $row['doctor_code'] : null,
                isset($row['doctor_label']) ? (string) $row['doctor_label'] : null,
            );

            if ($resolved['is_known']) {
                continue;
            }

            self::appendUnknownRow($groups, $row, 'skipped_row');
        }

        foreach ($log['imported_rows'] ?? [] as $row) {
            $resolved = DoctorCodeResolver::resolve(
                isset($row['doctor_code']) ? (string) $row['doctor_code'] : null,
                isset($row['doctor_label']) ? (string) $row['doctor_label'] : null,
            );

            if ($resolved['is_known']) {
                continue;
            }

            self::appendUnknownRow($groups, $row, 'imported_row');
        }

        $errors = array_values($groups);

        usort($errors, fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']));

        return $errors;
    }

    /**
     * @param  array<string, array<string, mixed>>  $totals
     */
    private static function ensureBucket(array &$totals, string $code, string $label): void
    {
        if (! array_key_exists($code, $totals)) {
            $totals[$code] = [
                'doctor_label' => $label,
                'day_count' => 0,
                'paid_total_aed' => '0.00',
                'lab_total_aed' => '0.00',
                'skipped_rows_on_sheet' => 0,
                'unresolved_rows' => 0,
                'issue_count' => 0,
            ];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, mixed>  $row
     */
    private static function appendUnknownRow(array &$groups, array $row, string $source): void
    {
        $label = trim((string) ($row['doctor_label'] ?? $row['doctor'] ?? 'Unknown'));
        $key = strtoupper(preg_replace('/\s+/', ' ', $label) ?? $label);

        if (! array_key_exists($key, $groups)) {
            $groups[$key] = [
                'label' => $label,
                'rows' => [],
            ];
        }

        $groups[$key]['rows'][] = array_merge($row, ['error_source' => $source]);
    }
}
