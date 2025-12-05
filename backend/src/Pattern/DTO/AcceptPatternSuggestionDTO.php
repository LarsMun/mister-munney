<?php

namespace App\Pattern\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AcceptPatternSuggestionDTO
{
    #[Assert\Length(max: 255, maxMessage: 'Omschrijvingspatroon mag maximaal 255 karakters zijn')]
    public ?string $descriptionPattern = null;

    #[Assert\Length(max: 255, maxMessage: 'Notitiespatroon mag maximaal 255 karakters zijn')]
    public ?string $notesPattern = null;

    #[Assert\NotBlank(message: 'Categorienaam is verplicht')]
    #[Assert\Length(
        min: 1,
        max: 100,
        minMessage: 'Categorienaam moet minimaal 1 karakter zijn',
        maxMessage: 'Categorienaam mag maximaal 100 karakters zijn'
    )]
    public string $categoryName;

    #[Assert\Positive(message: 'Categorie ID moet een positief getal zijn')]
    public ?int $categoryId = null;

    #[Assert\Regex(
        pattern: '/^#[0-9A-Fa-f]{6}$/',
        message: 'Kleur moet een geldige hex code zijn (bijv. #FF9900)'
    )]
    public ?string $categoryColor = null;
}
