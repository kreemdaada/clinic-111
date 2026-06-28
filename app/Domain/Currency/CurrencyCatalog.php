<?php

namespace App\Domain\Currency;

use InvalidArgumentException;

/**
 * Registry of supported clinic base currencies (ADR-034).
 */
final class CurrencyCatalog
{
    /**
     * @return array<string, Currency>
     */
    public static function all(): array
    {
        $currencies = [];

        foreach (config('currencies.supported', []) as $code => $definition) {
            $currencies[strtoupper($code)] = self::fromDefinition(strtoupper($code), $definition);
        }

        return $currencies;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array<string, string> ISO code => display label
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::all() as $currency) {
            $labels[$currency->code] = "{$currency->code} — {$currency->name}";
        }

        return $labels;
    }

    public static function isSupported(string $code): bool
    {
        $normalized = strtoupper(trim($code));

        return isset(config('currencies.supported')[$normalized]);
    }

    public static function resolve(string $code): Currency
    {
        $normalized = strtoupper(trim($code));
        $definition = config("currencies.supported.{$normalized}");

        if (! is_array($definition)) {
            throw new InvalidArgumentException("Unsupported currency [{$code}].");
        }

        return self::fromDefinition($normalized, $definition);
    }

    public static function tryResolve(?string $code): ?Currency
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        if (! self::isSupported($code)) {
            return null;
        }

        return self::resolve($code);
    }

    /**
     * @param  array{name: string, symbol: string, precision: int, format: string}  $definition
     */
    private static function fromDefinition(string $code, array $definition): Currency
    {
        return new Currency(
            code: $code,
            name: $definition['name'],
            symbol: $definition['symbol'],
            precision: (int) $definition['precision'],
            format: $definition['format'],
        );
    }
}
