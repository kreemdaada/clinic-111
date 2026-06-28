<?php

namespace Tests\Unit;

use App\Services\Currency\CurrencyFormatter;
use Tests\TestCase;

class CurrencyFormatterTest extends TestCase
{
    private CurrencyFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = app(CurrencyFormatter::class);
    }

    public function test_formats_aed_with_code_prefix(): void
    {
        $this->assertSame('AED 1250.00', $this->formatter->format('1250', 'AED'));
    }

    public function test_formats_eur_with_symbol_prefix(): void
    {
        $this->assertSame('€890.00', $this->formatter->format('890', 'EUR'));
    }

    public function test_formats_usd_with_symbol_prefix(): void
    {
        $this->assertSame('$150.00', $this->formatter->format('150', 'USD'));
    }
}
