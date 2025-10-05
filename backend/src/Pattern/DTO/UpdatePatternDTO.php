<?php

namespace App\Pattern\DTO;

use App\Enum\TransactionType;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(schema: 'UpdatePatternDTO')]
class UpdatePatternDTO
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
    #[OA\Property(type: 'number', format: 'decimal', example: 10.00)]
    public ?float $minAmount = null;

    #[Assert\PositiveOrZero]
    #[OA\Property(type: 'number', format: 'decimal', example: 100.00)]
    public ?float $maxAmount = null;

    #[OA\Property(description: 'Type transactie', type: 'string', example: 'debit')]
    public ?TransactionType $transactionType = null;

    #[OA\Property(type: 'string', example: 'Albert Heijn')]
    public ?string $description = null;

    #[Assert\Choice(['EXACT', 'LIKE'])]
    #[Assert\NotBlank]
    #[OA\Property(description: 'Matching type voor description', type: 'string', example: 'LIKE')]
    public string $matchTypeDescription;

    #[OA\Property(type: 'string', example: 'Weekboodschappen')]
    public ?string $notes = null;

    #[Assert\Choice(['EXACT', 'LIKE'])]
    #[Assert\NotBlank]
    #[OA\Property(description: 'Matching type voor notes', type: 'string', example: 'LIKE')]
    public string $matchTypeNotes;

    #[OA\Property(type: 'string', example: 'boodschappen')]
    public ?string $tag = null;

    #[OA\Property(
        description: 'Overschrijf bestaande categorieën of spaarrekeningen',
        type: 'boolean',
        example: false
    )]
    public bool $strict = false;

    #[Assert\Positive]
    #[OA\Property(type: 'integer', maximum: 2147483647, minimum: 1, example: 3)]
    public ?int $categoryId = null;

    #[Assert\Positive]
    #[OA\Property(type: 'integer', maximum: 2147483647, minimum: 1, example: 5)]
    public ?int $savingsAccountId = null;
}