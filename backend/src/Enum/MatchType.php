<?php

namespace App\Enum;

enum MatchType: string
{
    case EXACT = 'exact';
    case LIKE = 'like';

    public static function fromInput(string $input): self
    {
        return match(strtolower($input)) {
            'like' => self::LIKE,
            'exact' => self::EXACT,
            default => throw new \InvalidArgumentException("Ongeldige matchType: $input"),
        };
    }
}