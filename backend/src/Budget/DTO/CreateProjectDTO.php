<?php

namespace App\Budget\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateProjectDTO
{
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(min: 1, max: 255, minMessage: 'Name must be at least 1 character', maxMessage: 'Name cannot exceed 255 characters')]
    public string $name;

    public ?string $description = null;

    #[Assert\NotBlank(message: 'Account ID is required')]
    #[Assert\Positive(message: 'Account ID must be positive')]
    public int $accountId;

    #[Assert\Positive(message: 'Duration must be positive')]
    public int $durationMonths = 2;
}
