<?php

namespace App\RecurringTransaction\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'RecurringTransactionDTO')]
class RecurringTransactionDTO
{
    #[OA\Property(type: 'integer', example: 1)]
    public int $id;

    #[OA\Property(type: 'integer', example: 1)]
    public int $accountId;

    #[OA\Property(type: 'string', example: 'IBAN:NL91ABNA0417164300')]
    public string $merchantPattern;

    #[OA\Property(type: 'string', example: 'Netflix')]
    public string $displayName;

    #[OA\Property(type: 'number', format: 'float', example: 12.99)]
    public float $predictedAmount;

    #[OA\Property(type: 'number', format: 'float', example: 5.5, description: 'Amount variance percentage')]
    public float $amountVariance;

    #[OA\Property(type: 'string', example: 'monthly', enum: ['weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'])]
    public string $frequency;

    #[OA\Property(type: 'string', example: 'Maandelijks')]
    public string $frequencyLabel;

    #[OA\Property(type: 'number', format: 'float', example: 0.85, description: 'Confidence score 0-1')]
    public float $confidenceScore;

    #[OA\Property(type: 'string', format: 'date', example: '2026-01-15', nullable: true)]
    public ?string $lastOccurrence = null;

    #[OA\Property(type: 'string', format: 'date', example: '2026-02-15', nullable: true)]
    public ?string $nextExpected = null;

    #[OA\Property(type: 'boolean', example: true)]
    public bool $isActive;

    #[OA\Property(type: 'integer', example: 6)]
    public int $occurrenceCount;

    #[OA\Property(type: 'number', format: 'float', example: 0.92, description: 'How consistent the intervals are')]
    public float $intervalConsistency;

    #[OA\Property(type: 'string', example: 'debit', enum: ['debit', 'credit'])]
    public string $transactionType;

    #[OA\Property(type: 'integer', example: 5, nullable: true)]
    public ?int $categoryId = null;

    #[OA\Property(type: 'string', example: 'Abonnementen', nullable: true)]
    public ?string $categoryName = null;

    #[OA\Property(type: 'string', example: '#4CAF50', nullable: true)]
    public ?string $categoryColor = null;

    #[OA\Property(type: 'string', format: 'date-time')]
    public string $createdAt;

    #[OA\Property(type: 'string', format: 'date-time')]
    public string $updatedAt;
}
