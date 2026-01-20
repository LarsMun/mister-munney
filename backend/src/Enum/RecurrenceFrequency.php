<?php

namespace App\Enum;

enum RecurrenceFrequency: string
{
    case WEEKLY = 'weekly';
    case BIWEEKLY = 'biweekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public function getMinDays(): int
    {
        return match ($this) {
            self::WEEKLY => 6,
            self::BIWEEKLY => 13,
            self::MONTHLY => 28,
            self::QUARTERLY => 85,
            self::YEARLY => 360,
        };
    }

    public function getMaxDays(): int
    {
        return match ($this) {
            self::WEEKLY => 8,
            self::BIWEEKLY => 15,
            self::MONTHLY => 31,
            self::QUARTERLY => 95,
            self::YEARLY => 370,
        };
    }

    public function getMinOccurrences(): int
    {
        return match ($this) {
            self::WEEKLY => 6,
            self::BIWEEKLY => 4,
            self::MONTHLY => 3,
            self::QUARTERLY => 2,
            self::YEARLY => 2,
        };
    }

    public function getAverageDays(): int
    {
        return match ($this) {
            self::WEEKLY => 7,
            self::BIWEEKLY => 14,
            self::MONTHLY => 30,
            self::QUARTERLY => 90,
            self::YEARLY => 365,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::WEEKLY => 'Wekelijks',
            self::BIWEEKLY => 'Tweewekelijks',
            self::MONTHLY => 'Maandelijks',
            self::QUARTERLY => 'Per kwartaal',
            self::YEARLY => 'Jaarlijks',
        };
    }
}
