<?php

namespace App\RecurringTransaction\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'UpdateRecurringTransactionDTO')]
class UpdateRecurringTransactionDTO
{
    #[OA\Property(type: 'string', example: 'Netflix Subscription', nullable: true)]
    public ?string $displayName = null;

    #[OA\Property(type: 'boolean', example: true, nullable: true)]
    public ?bool $isActive = null;

    #[OA\Property(type: 'integer', example: 5, nullable: true)]
    public ?int $categoryId = null;
}
