<?php

namespace App\Support;

use App\Models\Clinic;

/**
 * Clinic-scoped currency rules.
 *
 * Each clinic has one fixed base currency (`clinics.currency`), set at registration.
 * Amounts already in that currency are used as-is; other currencies convert via AED rates.
 *
 * Clinic 111 (`CLINIC_111`) keeps the legacy DHS + USD + VISA Excel layout unchanged.
 */
class ClinicCurrencySupport
{
    public static function normalize(string $currency): string
    {
        return strtoupper(trim($currency));
    }

    public static function baseCurrency(Clinic $clinic): string
    {
        return self::normalize($clinic->currency);
    }

    /**
     * Primary cash column label in UI (DHS for Clinic 111, ISO code elsewhere).
     */
    public static function primaryCashLabel(Clinic $clinic): string
    {
        if (self::usesLegacyPaymentLayout($clinic)) {
            return 'DHS';
        }

        return self::baseCurrency($clinic);
    }

    /**
     * Only the original Clinic 111 tenant uses the legacy payment/import layout.
     */
    public static function usesLegacyPaymentLayout(Clinic $clinic): bool
    {
        $legacyCode = strtoupper((string) config('accounting.legacy_clinic_code', 'CLINIC_111'));

        return strtoupper($clinic->code) === $legacyCode;
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
     * Convert an amount into the clinic base currency (no-op when currencies match).
     */
    public static function toClinicCurrency(
        string $amount,
        string $fromCurrency,
        string $clinicBaseCurrency,
        ?string $usdToAedRate = null,
    ): string {
        return MoneyCalculator::convertBetween(
            $amount,
            $fromCurrency,
            self::normalize($clinicBaseCurrency),
            $usdToAedRate,
        );
    }

    /**
     * Convert a foreign-cash amount into the clinic's base currency for preview/save.
     */
    public static function foreignCashInClinicCurrency(
        string $amount,
        string $clinicCurrency,
        ?string $usdToAedRate = null,
    ): string {
        if (bccomp($amount, '0', 2) <= 0) {
            return '0.00';
        }

        $foreignCurrency = self::foreignCashCurrency($clinicCurrency);

        if ($foreignCurrency === null) {
            return '0.00';
        }

        return self::toClinicCurrency($amount, $foreignCurrency, $clinicCurrency, $usdToAedRate);
    }

    /**
     * Normalize a clinic-currency amount to AED for internal storage (legacy column names).
     */
    public static function toStoredAedEquivalent(
        string $amount,
        string $clinicCurrency,
        ?string $usdToAedRate = null,
    ): string {
        return MoneyCalculator::convertToAed($amount, self::normalize($clinicCurrency), $usdToAedRate);
    }

    /**
     * Convert a stored AED-normalized amount back to the clinic currency for display.
     */
    public static function fromStoredAedEquivalent(
        string $amount,
        string $clinicCurrency,
        ?string $usdToAedRate = null,
    ): string {
        return MoneyCalculator::convertFromAed($amount, self::normalize($clinicCurrency), $usdToAedRate);
    }
}
