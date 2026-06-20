<?php

namespace App\Support;

class MoneyCalculator
{
    public static function multiply(string|float|int $amount, int $quantity): string
    {
        return bcmul((string) $amount, (string) $quantity, 2);
    }

    public static function add(string ...$amounts): string
    {
        $total = '0.00';

        foreach ($amounts as $amount) {
            $total = bcadd($total, (string) $amount, 2);
        }

        return $total;
    }

    public static function subtract(string $minuend, string $subtrahend): string
    {
        return bcsub($minuend, $subtrahend, 2);
    }

    public static function percentage(string $amount, string|float|int $percentage): string
    {
        $rawValue = bcdiv(
            bcmul($amount, (string) $percentage, 6),
            '100',
            6,
        );

        return self::roundToTwoDecimals($rawValue);
    }

    public static function roundToTwoDecimals(string $amount): string
    {
        if (bccomp($amount, '0', 6) >= 0) {
            return bcadd($amount, '0.005', 2);
        }

        return bcsub($amount, '0.005', 2);
    }

    public static function convertToAed(string $amount, string $currency, string $exchangeRate = '3.65'): string
    {
        if (strtoupper($currency) === 'AED') {
            return bcadd((string) $amount, '0', 2);
        }

        return bcmul((string) $amount, $exchangeRate, 2);
    }
}
