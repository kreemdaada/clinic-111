<?php

namespace App\Contracts\Currency;

use App\Domain\Currency\Money;

/**
 * Future currency conversion contract (ADR-034).
 *
 * Not implemented in Milestone 14 — conversion stays outside the accounting engine.
 */
interface CurrencyConversionService
{
    public function convert(Money $amount, string $toCurrency, ?\DateTimeInterface $at = null): Money;
}
