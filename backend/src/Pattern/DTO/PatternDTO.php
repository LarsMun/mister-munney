<?php

namespace App\Pattern\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PatternDTO')]
class PatternDTO
{
    #[OA\Property(type: 'integer', maximum: 2147483647, minimum: 1, example: 42)]
    public int $id;

    #[OA\Property(description: 'ID van het account', type: 'integer', maximum: 2147483647, minimum: 1, example: 2)]
    public int $accountId;

    #[OA\Property(type: 'string', format: 'date', example: '2024-01-01')]
    public ?\DateTimeInterface $startDate = null;

    #[OA\Property(type: 'string', format: 'date', example: '2024-12-31')]
    public ?\DateTimeInterface $endDate = null;

    #[OA\Property(type: 'number', format: 'decimal', example: 10.00)]
    public ?float $minAmount = null;

    #[OA\Property(type: 'number', format: 'decimal', example: 100.00)]
    public ?float $maxAmount = null;

    #[OA\Property(type: 'string', example: 'credit')]
    public ?string $transactionType = null;

    #[OA\Property(type: 'string', example: 'Albert Heijn')]
    public ?string $description = null;

    #[OA\Property(description: 'Matching type voor description', type: 'string', example: 'LIKE')]
    public string $matchTypeDescription;

    #[OA\Property(type: 'string', example: 'Weekboodschappen')]
    public ?string $notes = null;

    #[OA\Property(description: 'Matching type voor notes', type: 'string', example: 'LIKE')]
    public string $matchTypeNotes;

    #[OA\Property(type: 'string', example: 'boodschappen')]
    public ?string $tag = null;

    #[OA\Property(type: 'boolean', example: 'false')]
    public ?bool $strict = false;

    #[OA\Property(
        properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'color', type: 'string', nullable: true),
        ],
        type: 'object',
        nullable: true
    )]
    public ?array $category = null;
}
