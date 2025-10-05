<?php

namespace App\Budget\DTO;

use App\Enum\BudgetStatus;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateBudgetDTO
{
    #[Assert\Length(min: 1, max: 255)]
    public ?string $name = null;
}