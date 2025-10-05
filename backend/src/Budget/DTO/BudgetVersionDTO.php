<?php

namespace App\Budget\DTO;

class BudgetVersionDTO
{
    public int $id;
    public int $budgetId;
    public float $monthlyAmount;
    public string $effectiveFromMonth;
    public ?string $effectiveUntilMonth = null;
    public ?string $changeReason = null;
    public string $createdAt;
    public bool $isCurrent;
    public string $displayName;
}