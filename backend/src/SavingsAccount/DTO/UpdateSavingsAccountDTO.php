<?php

namespace App\SavingsAccount\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

class UpdateSavingsAccountDTO
{
    #[Assert\NotBlank(allowNull: true)]
    #[OA\Property(type: 'string', example: 'Buffer')]
    public ?string $name = null;

    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(1000000)]
    #[OA\Property(type: 'number', example: 1000.00)]
    public ?float $targetAmount = null;

    #[Assert\NotBlank(allowNull: true)]
    #[OA\Property(type: 'string', example: '#FFCC00')]
    public ?string $color = null;
}