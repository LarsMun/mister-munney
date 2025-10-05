<?php

namespace App\Budget\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateBudgetVersionDTO
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    public float $monthlyAmount;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4}-\d{2}$/', message: 'Effective from must be in YYYY-MM format')]
    public string $effectiveFromMonth;

    #[Assert\Regex(pattern: '/^\d{4}-\d{2}$/', message: 'Effective until must be in YYYY-MM format')]
    public ?string $effectiveUntilMonth = null;

    #[Assert\Length(max: 500)]
    public ?string $changeReason = null;
}