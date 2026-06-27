<?php

namespace App\Services\Import;

use App\Models\Clinic;
use App\Support\ClinicCurrencySupport;

/**
 * Converts stored AED-normalized extraction log amounts into clinic-currency display values.
 */
class ExtractionLogPresentationService
{
    /**
     * @param  array<string, array<string, mixed>>  $doctorTotals
     * @return array<string, array<string, mixed>>
     */
    public function presentDoctorTotals(array $doctorTotals, Clinic $clinic): array
    {
        $presented = [];

        foreach ($doctorTotals as $code => $totals) {
            $presented[$code] = array_merge($totals, [
                'paid_total' => $this->displayStoredAmount((string) ($totals['paid_total_aed'] ?? '0.00'), $clinic),
                'lab_total' => $this->displayStoredAmount((string) ($totals['lab_total_aed'] ?? '0.00'), $clinic),
            ]);
        }

        return $presented;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function presentImportedRow(array $row, Clinic $clinic): array
    {
        $clinicCurrency = ClinicCurrencySupport::baseCurrency($clinic);
        $foreignCurrency = ClinicCurrencySupport::foreignCashCurrency($clinicCurrency);
        $legacy = ClinicCurrencySupport::usesLegacyPaymentLayout($clinic);

        $foreignInClinic = $legacy
            ? (string) ($row['usd_to_aed'] ?? '0.00')
            : ClinicCurrencySupport::foreignCashInClinicCurrency(
                (string) ($row['usd'] ?? '0.00'),
                $clinicCurrency,
            );

        $presented = array_merge($row, [
            'display_paid_total' => $this->displayStoredAmount((string) ($row['paid_total_aed'] ?? '0.00'), $clinic),
            'display_lab_total' => $this->displayStoredAmount((string) ($row['lab_total_aed'] ?? '0.00'), $clinic),
            'display_primary_cash' => (string) ($row['dhs_aed'] ?? '0.00'),
            'display_visa' => (string) ($row['visa_aed'] ?? '0.00'),
            'display_foreign_cash' => (string) ($row['usd'] ?? '0.00'),
            'display_foreign_in_clinic' => $foreignInClinic,
            'display_foreign_currency' => $foreignCurrency ?? 'USD',
        ]);

        if (is_array($presented['diagnostics']['job']['lines'] ?? null)) {
            $presented['diagnostics']['job']['lines'] = array_map(
                fn (array $line) => $this->presentJobLine($line, $clinic),
                $presented['diagnostics']['job']['lines'],
            );
        }

        if (isset($presented['diagnostics']['job']['total_aed'])) {
            $presented['diagnostics']['job']['display_total'] = $this->displayStoredAmount(
                (string) $presented['diagnostics']['job']['total_aed'],
                $clinic,
            );
        }

        return $presented;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public function presentJobLine(array $line, Clinic $clinic): array
    {
        return array_merge($line, [
            'display_unit_cost' => $this->displayStoredAmount((string) ($line['unit_cost_aed'] ?? '0.00'), $clinic),
            'display_line_total' => $this->displayStoredAmount((string) ($line['line_total_aed'] ?? '0.00'), $clinic),
        ]);
    }

    private function displayStoredAmount(string $storedAedAmount, Clinic $clinic): string
    {
        if (ClinicCurrencySupport::usesLegacyPaymentLayout($clinic)) {
            return bcadd($storedAedAmount, '0', 2);
        }

        return ClinicCurrencySupport::fromStoredAedEquivalent(
            $storedAedAmount,
            ClinicCurrencySupport::baseCurrency($clinic),
        );
    }
}
