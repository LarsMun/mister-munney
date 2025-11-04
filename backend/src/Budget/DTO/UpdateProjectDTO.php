<?php

namespace App\Budget\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProjectDTO
{
    #[Assert\Length(min: 1, max: 255, minMessage: 'Name must be at least 1 character', maxMessage: 'Name cannot exceed 255 characters')]
    public ?string $name = null;

    public ?string $description = null;

    #[Assert\Positive(message: 'Duration must be positive')]
    public ?int $durationMonths = null;
}
