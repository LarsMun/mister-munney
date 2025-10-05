<?php

namespace App\Enum;

enum TransactionType: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';

    public static function fromCsvValue(string $value): self
    {
        return match (strtolower($value)) {
            'af' => self::DEBIT,
            'bij' => self::CREDIT,
            default => throw new \InvalidArgumentException("Ongeldige transaction_type: $value"),
        };
    }
}