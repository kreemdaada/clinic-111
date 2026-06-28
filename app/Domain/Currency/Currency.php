<?php

namespace App\Domain\Currency;

/**
 * Immutable ISO 4217 currency metadata (ADR-034).
 */
final readonly class Currency
{
    public function __construct(
        public string $code,
        public string $name,
        public string $symbol,
        public int $precision,
        public string $format,
    ) {}

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    /**
     * @return array{code: string, name: string, symbol: string, precision: int}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'precision' => $this->precision,
        ];
    }
}
