<?php

namespace App\Pattern\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AcceptPatternSuggestionDTO
{
    #[Assert\NotBlank]
    public string $patternString;

    #[Assert\NotBlank]
    public string $categoryName;

    public ?int $categoryId = null;

    public ?string $categoryColor = null;
}
