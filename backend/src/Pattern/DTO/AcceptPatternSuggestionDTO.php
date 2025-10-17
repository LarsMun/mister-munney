<?php

namespace App\Pattern\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AcceptPatternSuggestionDTO
{
    public ?string $descriptionPattern = null;

    public ?string $notesPattern = null;

    #[Assert\NotBlank]
    public string $categoryName;

    public ?int $categoryId = null;

    public ?string $categoryColor = null;
}
