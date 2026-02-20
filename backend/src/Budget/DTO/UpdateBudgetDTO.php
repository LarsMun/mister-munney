<?php

namespace App\Budget\DTO;

use App\Enum\BudgetStatus;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateBudgetDTO
{
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Naam moet minimaal 1 karakter zijn',
        maxMessage: 'Naam mag maximaal 255 karakters zijn'
    )]
    public ?string $name = null;

    #[Assert\Choice(choices: ['EXPENSE', 'INCOME', 'PROJECT'], message: 'Budget type moet EXPENSE, INCOME of PROJECT zijn')]
    public ?string $budgetType = null;

    #[Assert\Length(max: 255, maxMessage: 'Icoon mag maximaal 255 karakters zijn')]
    public ?string $icon = null;

    public ?bool $isActive = null;
}