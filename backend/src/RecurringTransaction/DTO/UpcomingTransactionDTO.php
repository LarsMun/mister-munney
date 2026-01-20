<?php

namespace App\RecurringTransaction\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'UpcomingTransactionDTO')]
class UpcomingTransactionDTO
{
    #[OA\Property(type: 'integer', example: 1)]
    public int $id;

    #[OA\Property(type: 'string', example: 'Netflix')]
    public string $displayName;

    #[OA\Property(type: 'number', format: 'float', example: 12.99)]
    public float $predictedAmount;

    #[OA\Property(type: 'string', format: 'date', example: '2026-02-15')]
    public string $expectedDate;

    #[OA\Property(type: 'integer', example: 5, description: 'Days until expected')]
    public int $daysUntil;

    #[OA\Property(type: 'string', example: 'debit', enum: ['debit', 'credit'])]
    public string $transactionType;

    #[OA\Property(type: 'string', example: 'monthly')]
    public string $frequency;

    #[OA\Property(type: 'string', example: '#4CAF50', nullable: true)]
    public ?string $categoryColor = null;

    #[OA\Property(type: 'string', example: 'Abonnementen', nullable: true)]
    public ?string $categoryName = null;
}
