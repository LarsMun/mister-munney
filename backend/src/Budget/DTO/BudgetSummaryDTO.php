<?php

namespace App\Budget\DTO;

class BudgetSummaryDTO
{
    public int $budgetId;
    public string $budgetName;
    public string $budgetType; // 'EXPENSE' or 'INCOME'
    public float $allocatedAmount;
    public float $spentAmount;
    public float $remainingAmount;
    public float $spentPercentage;
    public string $monthYear;
    public bool $isOverspent;
    public string $status;

    // Trend informatie
    public float $trendPercentage;
    public string $trendDirection; // 'up', 'down', 'stable'
    public float $historicalMedian;
    public int $categoryCount;
}
