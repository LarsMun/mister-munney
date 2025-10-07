<?php

namespace App\Pattern\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;
use App\Enum\TransactionType;

#[OA\Schema(schema: 'CreatePatternDTO')]

class CreatePatternDTO
{
    #[Assert\NotNull]
    #[Assert\Positive]
    #[OA\Property(
        description: 'ID van het account',
        type: 'integer',
        maximum: 2147483647,
        minimum: 1,
        example: 1
    )]
    public int $accountId;

    #[Assert\Date]
    #[Assert\LessThanOrEqual(propertyPath: 'endDate')]
    #[OA\Property(
        description: 'Transactiedatum vanaf (optioneel)',
        type: 'string',
        format: 'date',
        example: '2024-01-01'
    )]
    public ?string $startDate = null;

    #[Assert\Date]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDate')]
    #[OA\Property(
        description: 'Transactiedatum tot en met (optioneel)',
        type: 'string',
        format: 'date',
        example: '2024-12-31'
    )]
    public ?string $endDate = null;

    #[Assert\PositiveOrZero]
    #[OA\Property(description: 'Minimaal bedrag', type: 'number', example: 10.00)]
    public ?float $minAmount = null;

    #[Assert\PositiveOrZero]
    #[OA\Property(description: 'Maximaal bedrag', type: 'number', example: 1000.00)]
    public ?float $maxAmount = null;

    #[OA\Property(description: 'Type transactie', type: 'string', example: 'debit')]
    public ?string $transactionType = null;

    #[OA\Property(description: 'Zoekterm in omschrijving', type: 'string', example: 'Albert Heijn')]
    public ?string $description = null;

    #[Assert\Choice(['exact', 'like', 'EXACT', 'LIKE'])]
    #[OA\Property(description: 'Matching type voor description', type: 'string', example: 'LIKE')]
    public ?string $matchTypeDescription = null;

    #[OA\Property(description: 'Zoekterm in notities', type: 'string', example: 'Boodschappen')]
    public ?string $notes = null;

    #[Assert\Choice(['exact', 'like', 'EXACT', 'LIKE'])]
    #[OA\Property(description: 'Matching type voor notes', type: 'string', example: 'LIKE')]
    public ?string $matchTypeNotes = null;

    #[OA\Property(description: 'Zoekterm in tag', type: 'string', example: 'Supermarkt')]
    public ?string $tag = null;

    #[OA\Property(
        description: 'Overschrijf bestaande categorieën of spaarrekeningen',
        type: 'boolean',
        example: false
    )]
    public bool $strict = false;

    #[Assert\Positive]
    #[OA\Property(
        description: 'ID van de toe te wijzen categorie',
        type: 'integer',
        maximum: 2147483647,
        minimum: 1,
        example: 4
    )]
    public ?int $categoryId = null;

    #[Assert\Positive]
    #[OA\Property(
        description: 'ID van de toe te wijzen spaarrekening',
        type: 'integer',
        maximum: 2147483647,
        minimum: 1,
        example: 2
    )]
    public ?int $savingsAccountId = null;
}