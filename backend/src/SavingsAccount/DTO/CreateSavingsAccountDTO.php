<?php

namespace App\SavingsAccount\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

class CreateSavingsAccountDTO
{
    #[Assert\NotBlank]
    #[OA\Property(type: 'string', example: 'Vakantie')]
    public ?string $name = null;

    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(1000000)]
    #[OA\Property(type: 'number', example: 1500.00, nullable: true)]
    public ?float $targetAmount = null;

    #[Assert\NotBlank]
    #[OA\Property(type: 'string', example: '#FFCC00')]
    public ?string $color = null;
}