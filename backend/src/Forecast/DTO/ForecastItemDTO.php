<?php

namespace App\Forecast\DTO;

class ForecastItemDTO
{
    public int $id;
    public string $type; // INCOME or EXPENSE
    public string $name;
    public ?string $icon = null;
    public ?int $budgetId = null;
    public ?int $categoryId = null;
    public float $expectedAmount;
    public float $actualAmount;
    public int $position;
    public ?string $customName = null;
}
