<?php

namespace App\Budget\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateBudgetDTO
{
    #[Assert\NotBlank(message: 'Naam is verplicht')]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Naam moet minimaal 1 karakter zijn',
        maxMessage: 'Naam mag maximaal 255 karakters zijn'
    )]
    public string $name;

    #[Assert\NotBlank(message: 'Account ID is verplicht')]
    #[Assert\Positive(message: 'Account ID moet een positief getal zijn')]
    public int $accountId;

    #[Assert\NotBlank(message: 'Budget type is verplicht')]
    #[Assert\Choice(choices: ['EXPENSE', 'INCOME', 'PROJECT'], message: 'Budget type moet EXPENSE, INCOME of PROJECT zijn')]
    public string $budgetType = 'EXPENSE';

    #[Assert\Length(max: 255, maxMessage: 'Icoon mag maximaal 255 karakters zijn')]
    public ?string $icon = null;

    #[Assert\All([
        new Assert\Type(type: 'integer', message: 'Elke categorie ID moet een integer zijn'),
        new Assert\Positive(message: 'Categorie ID moet een positief getal zijn')
    ])]
    public array $categoryIds = [];
}