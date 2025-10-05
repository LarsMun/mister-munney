<?php

namespace App\Budget\DTO;

use App\Entity\Budget;
use Symfony\Component\Validator\Constraints as Assert;

class CreateSimpleBudgetVersionDTO
{
    #[Assert\NotBlank]
    public Budget $budget;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public float $monthlyAmount;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4}-\d{2}$/', message: 'Effective from must be in YYYY-MM format')]
    public string $effectiveFromMonth;
}