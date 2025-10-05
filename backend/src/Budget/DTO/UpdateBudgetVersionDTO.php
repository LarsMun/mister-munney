<?php

namespace App\Budget\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateBudgetVersionDTO
{
    #[Assert\Positive(message: 'Monthly amount must be greater than 0')]
    public ?float $monthlyAmount = null;

    #[Assert\Regex(pattern: '/^\d{4}-\d{2}$/', message: 'Effective from must be in YYYY-MM format')]
    public ?string $effectiveFromMonth = null;

    #[Assert\Regex(pattern: '/^\d{4}-\d{2}$/', message: 'Effective until must be in YYYY-MM format')]
    public ?string $effectiveUntilMonth = null;

    #[Assert\Length(max: 500)]
    public ?string $changeReason = null;
}