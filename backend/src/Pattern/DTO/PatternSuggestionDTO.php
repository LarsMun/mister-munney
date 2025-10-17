<?php

namespace App\Pattern\DTO;

class PatternSuggestionDTO
{
    public function __construct(
        public readonly ?string $descriptionPattern,
        public readonly ?string $notesPattern,
        public readonly ?string $suggestedCategoryName,
        public readonly ?int $existingCategoryId,
        public readonly int $matchCount,
        public readonly array $exampleTransactions,
        public readonly float $confidence,
        public readonly string $reasoning,
        public readonly bool $previouslyDiscovered = false
    ) {
    }
}
