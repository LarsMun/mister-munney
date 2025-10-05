<?php

namespace App\Budget\DTO;

class BudgetSummaryDTO
{
    public int $budgetId;
    public string $budgetName;
    public float $allocatedAmount;
    public float $spentAmount;
    public float $remainingAmount;
    public float $spentPercentage;
    public string $monthYear;
    public bool $isOverspent;
    public string $status;
}