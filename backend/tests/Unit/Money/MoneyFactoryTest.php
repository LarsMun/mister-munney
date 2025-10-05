<?php

namespace App\Tests\Unit\Money;

use App\Money\MoneyFactory;
use Money\Money;
use PHPUnit\Framework\TestCase;

class MoneyFactoryTest extends TestCase
{
    private MoneyFactory $moneyFactory;

    protected function setUp(): void
    {
        $this->moneyFactory = new MoneyFactory();
    }

    public function testFromFloatCreatesCorrectMoney(): void
    {
        // Test positive amount
        $money = $this->moneyFactory->fromFloat(25.50);
        $this->assertEquals(Money::EUR(2550), $money);

        // Test negative amount
        $money = $this->moneyFactory->fromFloat(-18.75);
        $this->assertEquals(Money::EUR(-1875), $money);

        // Test zero
        $money = $this->moneyFactory->fromFloat(0.00);
        $this->assertEquals(Money::EUR(0), $money);
    }

    public function testToFloatConvertsCorrectly(): void
    {
        // Test positive amount
        $money = Money::EUR(2550);
        $float = $this->moneyFactory->toFloat($money);
        $this->assertEquals(25.50, $float);

        // Test negative amount
        $money = Money::EUR(-1875);
        $float = $this->moneyFactory->toFloat($money);
        $this->assertEquals(-18.75, $float);

        // Test zero
        $money = Money::EUR(0);
        $float = $this->moneyFactory->toFloat($money);
        $this->assertEquals(0.00, $float);
    }

    public function testZeroReturnsZeroMoney(): void
    {
        $money = $this->moneyFactory->zero();
        $this->assertEquals(Money::EUR(0), $money);
    }

    public function testFromStringHandlesVariousFormats(): void
    {
        // Test with euro sign
        $money = $this->moneyFactory->fromString('€25.50');
        $this->assertEquals(Money::EUR(2550), $money);

        // Test without euro sign
        $money = $this->moneyFactory->fromString('18.75');
        $this->assertEquals(Money::EUR(1875), $money);

        // Test with comma separator
        $money = $this->moneyFactory->fromString('1.250,75');
        $this->assertEquals(Money::EUR(125075), $money);
    }
}