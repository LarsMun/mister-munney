<?php

namespace App\Pattern\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;
use App\Enum\TransactionType;

#[OA\Schema(schema: 'CreatePatternDTO')]
class CreatePatternDTO
{
    #[Assert\NotNull(message: 'Account ID is verplicht')]
    #[Assert\Positive(message: 'Account ID moet een positief getal zijn')]
    #[OA\Property(
        description: 'ID van het account',
        type: 'integer',
        maximum: 2147483647,
        minimum: 1,
        example: 1
    )]
    public int $accountId;

    #[Assert\Date(message: 'Startdatum moet een geldige datum zijn (YYYY-MM-DD)')]
    #[Assert\LessThanOrEqual(propertyPath: 'endDate', message: 'Startdatum moet vóór of gelijk zijn aan einddatum')]
    #[OA\Property(
        description: 'Transactiedatum vanaf (optioneel)',
        type: 'string',
        format: 'date',
        example: '2024-01-01'
    )]
    public ?string $startDate = null;

    #[Assert\Date(message: 'Einddatum moet een geldige datum zijn (YYYY-MM-DD)')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDate', message: 'Einddatum moet na of gelijk zijn aan startdatum')]
    #[OA\Property(
        description: 'Transactiedatum tot en met (optioneel)',
        type: 'string',
        format: 'date',
        example: '2024-12-31'
    )]
    public ?string $endDate = null;

    #[Assert\PositiveOrZero(message: 'Minimumbedrag moet 0 of groter zijn')]
    #[OA\Property(description: 'Minimaal bedrag', type: 'number', example: 10.00)]
    public ?float $minAmount = null;

    #[Assert\PositiveOrZero(message: 'Maximumbedrag moet 0 of groter zijn')]
    #[OA\Property(description: 'Maximaal bedrag', type: 'number', example: 1000.00)]
    public ?float $maxAmount = null;

    #[Assert\Choice(choices: ['credit', 'debit', 'CREDIT', 'DEBIT'], message: 'Transactietype moet "credit" of "debit" zijn')]
    #[OA\Property(description: 'Type transactie', type: 'string', example: 'debit')]
    public ?string $transactionType = null;

    #[Assert\Length(max: 255, maxMessage: 'Omschrijving mag maximaal 255 karakters zijn')]
    #[OA\Property(description: 'Zoekterm in omschrijving', type: 'string', example: 'Albert Heijn')]
    public ?string $description = null;

    #[Assert\Choice(choices: ['exact', 'like', 'EXACT', 'LIKE'], message: 'Match type moet "exact" of "like" zijn')]
    #[OA\Property(description: 'Matching type voor description', type: 'string', example: 'LIKE')]
    public ?string $matchTypeDescription = null;

    #[Assert\Length(max: 255, maxMessage: 'Notities mag maximaal 255 karakters zijn')]
    #[OA\Property(description: 'Zoekterm in notities', type: 'string', example: 'Boodschappen')]
    public ?string $notes = null;

    #[Assert\Choice(choices: ['exact', 'like', 'EXACT', 'LIKE'], message: 'Match type moet "exact" of "like" zijn')]
    #[OA\Property(description: 'Matching type voor notes', type: 'string', example: 'LIKE')]
    public ?string $matchTypeNotes = null;

    #[Assert\Length(max: 100, maxMessage: 'Tag mag maximaal 100 karakters zijn')]
    #[OA\Property(description: 'Zoekterm in tag', type: 'string', example: 'Supermarkt')]
    public ?string $tag = null;

    #[OA\Property(
        description: 'Overschrijf bestaande categorieën',
        type: 'boolean',
        example: false
    )]
    public bool $strict = false;

    #[Assert\Positive(message: 'Categorie ID moet een positief getal zijn')]
    #[OA\Property(
        description: 'ID van de toe te wijzen categorie',
        type: 'integer',
        maximum: 2147483647,
        minimum: 1,
        example: 4
    )]
    public ?int $categoryId = null;
}