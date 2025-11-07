<?php

namespace App\Budget\DTO;

use App\Category\DTO\CategoryDTO;

class BudgetDTO
{
    public int $id;
    public string $name;
    public int $accountId;
    public string $budgetType;
    public ?string $icon = null;
    public string $createdAt;
    public string $updatedAt;

    /** @var CategoryDTO[] */
    public array $categoryIds = [];
    public array $categories = [];
}