<?php

namespace App\Category\DTO;

use App\Enum\TransactionType;
use DateTimeImmutable;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'CategoryDTO')]
class CategoryDTO
{
    #[OA\Property(type: 'integer', example: 1)]
    public int $id;

    #[OA\Property(type: 'string', example: 'Boodschappen')]
    public string $name;

    #[OA\Property(type: 'string', example: 'shopping-cart', nullable: true)]
    public ?string $icon = null;

    #[OA\Property(type: 'string', example: '#FF9900', nullable: true)]
    public ?string $color = null;

    #[OA\Property(type: 'string', format: 'date-time')]
    public DateTimeImmutable $createdAt;

    #[OA\Property(type: 'string', format: 'date-time')]
    public DateTimeImmutable $updatedAt;

    #[OA\Property(type: 'integer', example: 1)]
    public int $accountId;

    #[OA\Property(type: 'string', enum: ['debit', 'credit'], example: 'debit')]
    public TransactionType $transactionType;

    #[OA\Property(type: 'integer', example: 5, nullable: true)]
    public ?int $budgetId = null;

    #[OA\Property(type: 'string', example: 'Budget 2024', nullable: true)]
    public ?string $budgetName = null;
}