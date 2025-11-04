<?php

namespace App\Budget\DTO;

class ActiveBudgetDTO
{
    public int $id;
    public string $name;
    public string $budgetType; // EXPENSE, INCOME, PROJECT
    public ?string $description = null;
    public ?int $durationMonths = null; // For PROJECT type
    public ?string $status = null; // For PROJECT type
    public ?array $insight = null; // For EXPENSE/INCOME with insights
    public int $categoryCount;
}
