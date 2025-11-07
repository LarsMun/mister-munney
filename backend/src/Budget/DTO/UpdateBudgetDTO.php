<?php

namespace App\Budget\DTO;

use App\Enum\BudgetStatus;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateBudgetDTO
{
    #[Assert\Length(min: 1, max: 255)]
    public ?string $name = null;

    #[Assert\Choice(choices: ['EXPENSE', 'INCOME', 'PROJECT'], message: 'Budget type moet EXPENSE, INCOME of PROJECT zijn')]
    public ?string $budgetType = null;

    #[Assert\Length(max: 255)]
    public ?string $icon = null;
}