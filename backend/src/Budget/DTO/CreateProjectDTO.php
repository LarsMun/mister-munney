<?php

namespace App\Budget\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateProjectDTO
{
    #[Assert\NotBlank(message: 'Naam is verplicht')]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Naam moet minimaal 1 karakter zijn',
        maxMessage: 'Naam mag maximaal 255 karakters zijn'
    )]
    public string $name;

    #[Assert\Length(max: 1000, maxMessage: 'Beschrijving mag maximaal 1000 karakters zijn')]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'Account ID is verplicht')]
    #[Assert\Positive(message: 'Account ID moet een positief getal zijn')]
    public int $accountId;

    #[Assert\Positive(message: 'Looptijd moet een positief getal zijn')]
    #[Assert\Range(min: 1, max: 120, notInRangeMessage: 'Looptijd moet tussen 1 en 120 maanden zijn')]
    public int $durationMonths = 2;
}
