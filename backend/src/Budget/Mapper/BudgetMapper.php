<?php

namespace App\Budget\Mapper;

use App\Budget\DTO\BudgetDTO;
use App\Entity\Budget;

class BudgetMapper
{
    public function __construct(
    ) {
    }

    public function toDto(Budget $budget): BudgetDTO
    {
        $dto = new BudgetDTO();
        $dto->id = $budget->getId();
        $dto->name = $budget->getName();
        $dto->accountId = $budget->getAccount()->getId();
        $dto->budgetType = $budget->getBudgetType()->value;
        $dto->icon = $budget->getIcon();
        $dto->isActive = $budget->isActive();
        $dto->createdAt = $budget->getCreatedAt()->format('Y-m-d H:i:s');
        $dto->updatedAt = $budget->getUpdatedAt()->format('Y-m-d H:i:s');

        // Map categories
        $dto->categoryIds = $budget->getCategories()->map(fn($cat) => $cat->getId())->toArray();
        $dto->categories = $budget->getCategories()->map(function($category) {
            return [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'color' => $category->getColor(),
                'icon' => $category->getIcon()
            ];
        })->toArray();

        return $dto;
    }
}