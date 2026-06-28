<?php

namespace App\Contracts\Currency;

/**
 * Future exchange-rate lookup contract (ADR-034).
 *
 * Not implemented in Milestone 14 — extension point for Milestone 15+.
 */
interface ExchangeRateProvider
{
    /**
     * @return string Rate as decimal string: one unit of $fromCurrency in $toCurrency.
     */
    public function rate(string $fromCurrency, string $toCurrency, ?\DateTimeInterface $at = null): string;
}
