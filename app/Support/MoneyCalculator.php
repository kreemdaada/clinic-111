<?php

namespace App\Support;

/**
 * Deterministic decimal arithmetic for all accounting calculations.
 *
 * Uses PHP `bcmath` — never float. All results are strings with 2 decimal places
 * unless intermediate precision requires more (see {@see percentage()}).
 */
class MoneyCalculator
{
    /**
     * Multiply a monetary amount by an integer quantity.
     *
     * @param  string|float|int  $amount  Unit price or base amount.
     * @param  int  $quantity  Number of units (crowns, items, etc.).
     * @return string Product rounded to 2 decimal places.
     */
    public static function multiply(string|float|int $amount, int $quantity): string
    {
        return bcmul((string) $amount, (string) $quantity, 2);
    }

    /**
     * Sum two or more decimal amounts.
     *
     * @param  string  ...$amounts  Amounts to add (each cast to string).
     * @return string Total with 2 decimal places.
     */
    public static function add(string ...$amounts): string
    {
        $total = '0.00';

        foreach ($amounts as $amount) {
            $total = bcadd($total, (string) $amount, 2);
        }

        return $total;
    }

    /**
     * Subtract one amount from another.
     *
     * @param  string  $minuend  Value to subtract from.
     * @param  string  $subtrahend  Value to subtract.
     * @return string Difference with 2 decimal places.
     */
    public static function subtract(string $minuend, string $subtrahend): string
    {
        return bcsub($minuend, $subtrahend, 2);
    }

    /**
     * Calculate a percentage of an amount (e.g. doctor commission).
     *
     * Uses 6-decimal intermediate precision, then half-up rounds to 2 decimals.
     *
     * @param  string  $amount  Base amount in AED.
     * @param  string|float|int  $percentage  Percent value (e.g. 35 for 35%).
     * @return string Result with 2 decimal places.
     */
    public static function percentage(string $amount, string|float|int $percentage): string
    {
        $rawValue = bcdiv(
            bcmul($amount, (string) $percentage, 6),
            '100',
            6,
        );

        return self::roundToTwoDecimals($rawValue);
    }

    /**
     * Round a decimal string to 2 places using half-up rounding.
     *
     * Positive amounts add 0.005 before truncating; negative subtract 0.005.
     *
     * @param  string  $amount  Value with up to 6 decimal places.
     * @return string Rounded value with 2 decimal places.
     */
    public static function roundToTwoDecimals(string $amount): string
    {
        if (bccomp($amount, '0', 6) >= 0) {
            return bcadd($amount, '0.005', 2);
        }

        return bcsub($amount, '0.005', 2);
    }

    /**
     * How many AED one unit of the given currency is worth.
     */
    public static function rateToAed(string $currency, ?string $usdToAedRate = null): string
    {
        $code = strtoupper(trim($currency));

        if ($code === 'AED') {
            return '1';
        }

        if ($code === 'USD') {
            return $usdToAedRate ?? (string) config('accounting.usd_exchange_rate', '3.65');
        }

        if ($code === 'RUB') {
            return (string) config('accounting.rub_to_aed_rate', '0.0481');
        }

        $rates = config('accounting.currency_to_aed_rates', []);

        if (isset($rates[$code])) {
            return (string) $rates[$code];
        }

        return '1';
    }

    /**
     * Normalize an amount to AED using configured exchange rates.
     *
     * @param  string  $amount  Original amount.
     * @param  string  $currency  ISO-style code (`AED`, `USD`, `EUR`, …).
     * @param  string|null  $usdToAedRate  Override USD→AED rate when converting USD.
     * @return string Amount in AED with 2 decimal places.
     */
    public static function convertToAed(string $amount, string $currency, ?string $usdToAedRate = null): string
    {
        $rate = self::rateToAed($currency, $usdToAedRate);

        if ($rate === '1') {
            return bcadd((string) $amount, '0', 2);
        }

        return bcmul((string) $amount, $rate, 2);
    }

    /**
     * Convert an AED amount into another currency.
     */
    public static function convertFromAed(string $amount, string $currency, ?string $usdToAedRate = null): string
    {
        $rate = self::rateToAed($currency, $usdToAedRate);

        if ($rate === '1') {
            return bcadd((string) $amount, '0', 2);
        }

        return bcdiv((string) $amount, $rate, 2);
    }

    /**
     * Convert an amount between supported currencies (via AED pivot).
     *
     * @param  string  $amount  Original amount.
     * @param  string  $fromCurrency  Source currency code.
     * @param  string  $toCurrency  Target currency code.
     * @param  string|null  $usdToAedRate  USD→AED rate used for cross conversion.
     * @return string Amount in target currency with 2 decimal places.
     */
    public static function convertBetween(
        string $amount,
        string $fromCurrency,
        string $toCurrency,
        ?string $usdToAedRate = null,
    ): string {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        if ($from === $to) {
            return bcadd((string) $amount, '0', 2);
        }

        $inAed = self::convertToAed((string) $amount, $from, $usdToAedRate);

        return self::convertFromAed($inAed, $to, $usdToAedRate);
    }
}
