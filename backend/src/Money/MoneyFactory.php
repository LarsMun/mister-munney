<?php

namespace App\Money;

use Money\Money;
use Money\Currency;

class MoneyFactory
{
    private const string CURRENCY = 'EUR';

    public function fromFloat(float $amount): Money
    {
        return new Money((string) round($amount * 100), new Currency(self::CURRENCY));
    }

    public function zero(): Money
    {
        return new Money(0, new Currency(self::CURRENCY));
    }

    public function fromString(string $amount): Money
    {
        // Remove currency symbols and whitespace
        $cleaned = preg_replace('/[€$\s]/', '', $amount);

        // Handle comma as decimal separator (European format)
        if (preg_match('/^[\d.]+,\d{2}$/', $cleaned)) {
            // Format like "1.250,75" - comma is decimal, dots are thousands
            $cleaned = str_replace(['.', ','], ['', '.'], $cleaned);
        }

        // Ensure it's a valid decimal number
        if (!is_numeric($cleaned)) {
            throw new \InvalidArgumentException("Invalid amount format: $amount");
        }

        return new Money(bcmul($cleaned, '100', 0), new Currency(self::CURRENCY));
    }

    public function fromCents(int $amount): Money
    {
        return new Money((string) $amount, new Currency(self::CURRENCY));
    }

    public function toFloat(Money $money): float
    {
        return (float) bcdiv($money->getAmount(), '100', 2);
    }

    public function toString(Money $money): string
    {
        return bcdiv($money->getAmount(), '100', 2);
    }

    public function toCents(Money $money): int
    {
        return (int) $money->getAmount();
    }
}