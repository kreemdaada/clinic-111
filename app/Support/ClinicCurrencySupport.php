<?php

namespace App\Support;

/**
 * Clinic-scoped currency rules for the daily report editor and payment totals.
 *
 * Clinic 111 (AED) keeps the legacy DHS + USD + VISA layout unchanged.
 * Other clinics use their registered base currency for primary payment fields.
 */
class ClinicCurrencySupport
{
    public static function normalize(string $currency): string
    {
        return strtoupper(trim($currency));
    }

    public static function isLegacyAedClinic(string $clinicCurrency): bool
    {
        return self::normalize($clinicCurrency) === 'AED';
    }

    /**
     * Optional secondary cash field in the editor (foreign currency).
     */
    public static function foreignCashCurrency(string $clinicCurrency): ?string
    {
        $clinic = self::normalize($clinicCurrency);

        if ($clinic === 'AED') {
            return 'USD';
        }

        if ($clinic === 'USD') {
            return 'AED';
        }

        return 'USD';
    }

    /**
     * Convert a foreign-cash amount into the clinic's base currency for preview/save.
     */
    public static function foreignCashInClinicCurrency(
        string $amount,
        string $clinicCurrency,
        string $usdToAedRate = '3.65',
    ): string {
        if (bccomp($amount, '0', 2) <= 0) {
            return '0.00';
        }

        $foreignCurrency = self::foreignCashCurrency($clinicCurrency);

        if ($foreignCurrency === null) {
            return '0.00';
        }

        return MoneyCalculator::convertBetween(
            $amount,
            $foreignCurrency,
            self::normalize($clinicCurrency),
            $usdToAedRate,
        );
    }

    /**
     * Normalize a clinic-currency amount to AED for internal storage (legacy column names).
     */
    public static function toStoredAedEquivalent(
        string $amount,
        string $clinicCurrency,
        string $usdToAedRate = '3.65',
    ): string {
        $clinic = self::normalize($clinicCurrency);

        if ($clinic === 'AED') {
            return bcadd($amount, '0', 2);
        }

        if ($clinic === 'USD') {
            return MoneyCalculator::convertBetween($amount, 'USD', 'AED', $usdToAedRate);
        }

        return bcadd($amount, '0', 2);
    }

    /**
     * Convert a stored AED-normalized amount back to the clinic currency for display.
     */
    public static function fromStoredAedEquivalent(
        string $amount,
        string $clinicCurrency,
        string $usdToAedRate = '3.65',
    ): string {
        $clinic = self::normalize($clinicCurrency);

        if ($clinic === 'AED') {
            return bcadd($amount, '0', 2);
        }

        if ($clinic === 'USD') {
            return MoneyCalculator::convertBetween($amount, 'AED', 'USD', $usdToAedRate);
        }

        return bcadd($amount, '0', 2);
    }
}
