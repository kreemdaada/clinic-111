<?php

namespace Tests\Unit;

use App\Domain\Currency\CurrencyCatalog;
use InvalidArgumentException;
use Tests\TestCase;

class CurrencyCatalogTest extends TestCase
{
    public function test_supported_currency_codes_match_milestone_catalog(): void
    {
        $this->assertSame(['AED', 'EUR', 'USD', 'SAR', 'GBP'], CurrencyCatalog::codes());
    }

    public function test_resolve_returns_metadata(): void
    {
        $currency = CurrencyCatalog::resolve('EUR');

        $this->assertSame('EUR', $currency->code);
        $this->assertSame('Euro', $currency->name);
        $this->assertSame('€', $currency->symbol);
        $this->assertSame(2, $currency->precision);
    }

    public function test_unsupported_currency_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CurrencyCatalog::resolve('XYZ');
    }

    public function test_labels_are_human_readable(): void
    {
        $this->assertSame('USD — US Dollar', CurrencyCatalog::labels()['USD']);
    }
}
