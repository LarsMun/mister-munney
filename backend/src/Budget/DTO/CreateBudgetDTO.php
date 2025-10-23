<?php

namespace App\Budget\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateBudgetDTO
{
    #[Assert\NotBlank(message: 'Naam is verplicht')]
    #[Assert\Length(min: 1, max: 255, maxMessage: 'Naam mag maximaal 255 karakters zijn')]
    public string $name;

    #[Assert\NotBlank(message: 'AccountId is verplicht')]
    #[Assert\Positive(message: 'Bedrag moet een positief getal zijn')]
    public int $accountId;

    #[Assert\NotBlank(message: 'Budget type is verplicht')]
    #[Assert\Choice(choices: ['EXPENSE', 'INCOME'], message: 'Budget type moet EXPENSE of INCOME zijn')]
    public string $budgetType = 'EXPENSE';

    #[Assert\NotBlank(message: 'Bedrag is verplicht')]
    #[Assert\Positive(message: 'Bedrag moet een positief getal zijn')]
    public float $monthlyAmount;

    #[Assert\NotBlank(message: 'Startdatum is verplicht')]
    #[Assert\Regex(pattern: '/^\d{4}-\d{2}$/', message: 'Datum moet jjjj-mm zijn')]
    public string $effectiveFromMonth;

    #[Assert\Length(max: 500)]
    public ?string $changeReason = null;

    #[Assert\Length(max: 255)]
    public ?string $icon = null;

    public array $categoryIds = [];
}