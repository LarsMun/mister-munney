<?php

namespace App\Budget\DTO;

use App\Category\DTO\CategoryDTO;

class BudgetDTO
{
    public int $id;
    public string $name;
    public int $accountId;
    public string $budgetType;
    public string $createdAt;
    public string $updatedAt;

    /** @var BudgetVersionDTO[] */
    public array $versions = [];

    /** @var CategoryDTO[] */
    public array $categoryIds = [];
    public array $categories = [];

    // Current version info (convenience)
    public ?float $currentMonthlyAmount = null;
    public ?string $currentEffectiveFrom = null;
    public ?string $currentEffectiveUntil = null;
}