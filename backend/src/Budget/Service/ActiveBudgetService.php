<?php

namespace App\Budget\Service;

use App\Budget\Repository\BudgetRepository;
use App\Entity\Budget;
use App\Enum\BudgetType;
use App\Enum\ProjectStatus;

class ActiveBudgetService
{
    public function __construct(
        private readonly BudgetRepository $budgetRepository,
        private readonly ProjectStatusCalculator $statusCalculator
    ) {
    }

    /**
     * Get all active budgets (EXPENSE/INCOME where isActive=true)
     * Excludes PROJECT type - those are shown in Projects section
     */
    public function getActiveBudgets(?BudgetType $type = null, ?int $accountId = null): array
    {
        // Use eager loading to avoid N+1 queries and lazy load issues
        $allBudgets = $this->budgetRepository->findAllWithCategories($accountId);

        $active = [];

        foreach ($allBudgets as $budget) {
            // Skip PROJECT type budgets - they have their own section
            if ($budget->getBudgetType() === BudgetType::PROJECT) {
                continue;
            }

            // Filter by type if specified
            if ($type !== null && $budget->getBudgetType() !== $type) {
                continue;
            }

            if ($budget->isActive()) {
                $active[] = $budget;
            }
        }

        return $active;
    }

    /**
     * Get all older/inactive budgets (excludes PROJECT type - those are shown in Projects section)
     */
    public function getOlderBudgets(?BudgetType $type = null, ?int $accountId = null): array
    {
        // Use eager loading to avoid N+1 queries and lazy load issues
        $allBudgets = $this->budgetRepository->findAllWithCategories($accountId);

        $older = [];

        foreach ($allBudgets as $budget) {
            // Skip PROJECT type budgets - they have their own section
            if ($budget->getBudgetType() === BudgetType::PROJECT) {
                continue;
            }

            // Filter by type if specified
            if ($type !== null && $budget->getBudgetType() !== $type) {
                continue;
            }

            if (!$budget->isActive()) {
                $older[] = $budget;
            }
        }

        return $older;
    }
}
