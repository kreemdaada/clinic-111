<?php

namespace App\Services\Currency;

use App\Domain\Currency\Currency;
use App\Domain\Currency\CurrencyCatalog;
use App\Domain\Currency\Money;

/**
 * Centralized currency display formatting (ADR-034).
 */
class CurrencyFormatter
{
    public function format(string $amount, Currency|string $currency): string
    {
        $resolved = $currency instanceof Currency
            ? $currency
            : CurrencyCatalog::resolve($currency);

        $normalized = Money::of($amount, $resolved)->amount;

        return match ($resolved->format) {
            'code_amount' => $resolved->code.' '.$normalized,
            default => $resolved->symbol.$normalized,
        };
    }

    public function formatMoney(Money $money): string
    {
        return $this->format($money->amount, $money->currency);
    }
}
