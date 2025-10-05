<?php

namespace App\Budget\DTO;

class AvailableCategoryDTO
{
    public int $id;
    public string $name;
    public ?string $icon = null;
    public ?string $color = null;
    public bool $isAssigned;
    public ?int $currentBudgetId = null;
    public ?string $currentBudgetName = null;
}