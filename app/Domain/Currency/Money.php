<?php

namespace App\Domain\Currency;

use App\Support\MoneyCalculator;
use InvalidArgumentException;

/**
 * Amount + currency pair for explicit monetary values (ADR-034).
 */
final readonly class Money
{
    public function __construct(
        public string $amount,
        public Currency $currency,
    ) {
        $this->assertValidAmount($amount);
    }

    public static function of(string $amount, Currency|string $currency): self
    {
        $resolved = $currency instanceof Currency
            ? $currency
            : CurrencyCatalog::resolve($currency);

        return new self(self::normalizeAmount($amount, $resolved->precision), $resolved);
    }

    public static function zero(Currency|string $currency): self
    {
        $resolved = $currency instanceof Currency
            ? $currency
            : CurrencyCatalog::resolve($currency);

        $zero = $resolved->precision === 0 ? '0' : '0.'.str_repeat('0', $resolved->precision);

        return new self($zero, $resolved);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(
            MoneyCalculator::add($this->amount, $other->amount),
            $this->currency,
        );
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(
            MoneyCalculator::subtract($this->amount, $other->amount),
            $this->currency,
        );
    }

    public function equals(self $other): bool
    {
        return $this->currency->equals($other->currency)
            && bccomp($this->amount, $other->amount, $this->currency->precision) === 0;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return bccomp($this->amount, $other->amount, $this->currency->precision) === 1;
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', $this->currency->precision) === 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency->code} with {$other->currency->code}.",
            );
        }
    }

    private function assertValidAmount(string $amount): void
    {
        if ($amount === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Invalid monetary amount [{$amount}].");
        }
    }

    private static function normalizeAmount(string $amount, int $precision): string
    {
        if ($precision === 0) {
            return MoneyCalculator::roundToPrecision($amount, 0);
        }

        return MoneyCalculator::roundToPrecision($amount, $precision);
    }
}
