<?php

namespace App\Budget\Mapper;

use App\Budget\DTO\BudgetDTO;
use App\Budget\DTO\BudgetVersionDTO;
use App\Budget\DTO\BudgetSummaryDTO;
use App\Category\Mapper\CategoryMapper;
use App\Entity\Budget;
use App\Entity\BudgetVersion;

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
        $dto->createdAt = $budget->getCreatedAt()->format('Y-m-d H:i:s');
        $dto->updatedAt = $budget->getUpdatedAt()->format('Y-m-d H:i:s');

        // Map versions
        $dto->versions = array_map(
            fn(BudgetVersion $version) => $this->versionToDto($version),
            $budget->getBudgetVersions()->toArray()
        );

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

        // Current version convenience data
        $currentVersion = $budget->getCurrentVersion();
        if ($currentVersion) {
            $dto->currentMonthlyAmount = $currentVersion->getMonthlyAmount()->getAmount() / 100;
            $dto->currentEffectiveFrom = $currentVersion->getEffectiveFromMonth();
            $dto->currentEffectiveUntil = $currentVersion->getEffectiveUntilMonth();
        }

        return $dto;
    }

    public function versionToDto(BudgetVersion $version): BudgetVersionDTO
    {
        $dto = new BudgetVersionDTO();
        $dto->id = $version->getId();
        $dto->budgetId = $version->getBudget()->getId();
        $dto->monthlyAmount = $version->getMonthlyAmount()->getAmount() / 100;
        $dto->effectiveFromMonth = $version->getEffectiveFromMonth();
        $dto->effectiveUntilMonth = $version->getEffectiveUntilMonth();
        $dto->changeReason = $version->getChangeReason();
        $dto->createdAt = $version->getCreatedAt()->format('Y-m-d H:i:s');
        $dto->isCurrent = $version->isCurrent();
        $dto->displayName = $version->getDisplayName();

        return $dto;
    }

    public function toSummaryDto(Budget $budget, string $monthYear, float $spentAmount): BudgetSummaryDTO
    {
        $version = $budget->getEffectiveVersion($monthYear);
        $allocatedAmount = $version ? $version->getMonthlyAmount()->getAmount() / 100 : 0;
        $remainingAmount = $allocatedAmount - $spentAmount;
        $spentPercentage = $allocatedAmount > 0 ? ($spentAmount / $allocatedAmount) * 100 : 0;

        $dto = new BudgetSummaryDTO();
        $dto->budgetId = $budget->getId();
        $dto->budgetName = $budget->getName();
        $dto->allocatedAmount = $allocatedAmount;
        $dto->spentAmount = $spentAmount;
        $dto->remainingAmount = $remainingAmount;
        $dto->spentPercentage = round($spentPercentage, 2);
        $dto->monthYear = $monthYear;
        $dto->isOverspent = $spentAmount > $allocatedAmount;

        return $dto;
    }
}