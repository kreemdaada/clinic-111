<?php

namespace Tests\Unit;

use App\Domain\Currency\Money;
use InvalidArgumentException;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_money_addition_preserves_currency(): void
    {
        $left = Money::of('10.50', 'EUR');
        $right = Money::of('2.25', 'EUR');

        $total = $left->add($right);

        $this->assertSame('12.75', $total->amount);
        $this->assertSame('EUR', $total->currency->code);
    }

    public function test_money_rejects_cross_currency_math(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('10.00', 'EUR')->add(Money::of('10.00', 'USD'));
    }

    public function test_money_normalizes_precision_using_bcmath(): void
    {
        $money = Money::of('10.005', 'EUR');

        $this->assertSame('10.01', $money->amount);
    }

    public function test_money_equality(): void
    {
        $this->assertTrue(Money::of('100.00', 'AED')->equals(Money::of('100.00', 'AED')));
        $this->assertFalse(Money::of('100.00', 'AED')->equals(Money::of('100.01', 'AED')));
    }
}
