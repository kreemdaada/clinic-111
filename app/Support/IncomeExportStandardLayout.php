<?php

namespace App\Support;

/**
 * Canonical Server Income Excel column layout for doctors without a custom DB profile.
 *
 * Matches the compact percentage-doctor layout (same structure as the Riyadh reference sheet).
 * Clinic 111 doctors use their own rows in `doctor_income_export_profiles` from the seeder.
 */
final class IncomeExportStandardLayout
{
    /**
     * @return array<string, string>
     */
    public static function compactPaymentColumns(): array
    {
        return [
            'dhs' => 'B',
            'usd' => 'C',
            'usd_to_aed' => 'D',
            'visa' => 'E',
            'total' => 'F',
            'job' => 'G',
        ];
    }

    /**
     * Standard treatment code → Excel column map for percentage doctors.
     *
     * @return array<string, string>
     */
    public static function standardTreatmentColumns(): array
    {
        return [
            'MC' => 'H',
            'ZIR' => 'I',
            'IMPL-CR' => 'J',
            'IMPL-ZIR' => 'K',
            'VENEER' => 'L',
            'IMPL' => 'M',
            'POST' => 'N',
            'ABT' => 'O',
            'REMOV' => 'P',
            'REPEAR' => 'Q',
            'PARTIAL' => 'R',
            'BLEACHING' => 'S',
        ];
    }

    /**
     * Row-1 header text for a treatment column (Original Income template naming).
     */
    public static function treatmentHeaderLabel(string $treatmentCode): string
    {
        return match (strtoupper(trim($treatmentCode))) {
            'MC' => 'M/C-CR',
            'ZIR' => 'ZIR-CR',
            'REMOV' => 'REMOVABLE',
            'REPEAR' => 'REPEAR',
            default => strtoupper(trim($treatmentCode)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultProfileConfig(string $sheetName): array
    {
        return [
            'sheet_name' => $sheetName,
            'layout' => 'standard',
            'first_day_row' => 3,
            'write_payment_headers' => true,
            'summary_shows_net_total' => true,
            'payment_columns' => self::compactPaymentColumns(),
            'treatment_columns' => self::standardTreatmentColumns(),
        ];
    }
}
