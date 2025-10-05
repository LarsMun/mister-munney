<?php

namespace App\Budget\Service;

use App\Account\Repository\AccountRepository;
use App\Budget\DTO\CreateBudgetDTO;
use App\Budget\DTO\UpdateBudgetDTO;
use App\Budget\Repository\BudgetRepository;
use App\Category\Repository\CategoryRepository;
use App\Entity\Account;
use App\Entity\Budget;
use App\Entity\BudgetVersion;
use Exception;
use InvalidArgumentException;
use Money\Money;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BudgetService
{
    private AccountRepository $accountRepository;
    private BudgetRepository $budgetRepository;
    private BudgetVersionService $budgetVersionService;
    private CategoryRepository $categoryRepository;

    public function __construct(
        AccountRepository $accountRepository,
        BudgetRepository $budgetRepository,
        BudgetVersionService $budgetVersionService,
        CategoryRepository $categoryRepository
    ) {
        $this->accountRepository = $accountRepository;
        $this->budgetRepository = $budgetRepository;
        $this->budgetVersionService = $budgetVersionService;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @throws NotFoundHttpException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function createBudget(CreateBudgetDTO $createBudgetDTO): Budget
    {
        // Create new Budget entity
        $budget = new Budget();
        $budget->setName($createBudgetDTO->name);
        $budget->setAccount($this->getAccountById($createBudgetDTO->accountId));

        // Save via repository
        $budget = $this->budgetRepository->save($budget);

        // Create the first version using BudgetVersionService
        $budgetVersion = new BudgetVersion();
        $budgetVersion->setBudget($budget);
        $budgetVersion->setMonthlyAmount(Money::EUR($createBudgetDTO->monthlyAmount * 100));
        $budgetVersion->setEffectiveFromMonth($createBudgetDTO->effectiveFromMonth);

        $this->budgetVersionService->createSimpleVersion($budgetVersion);

        return $budget;
    }

    /**
     * @throws NotFoundHttpException
     */
    public function updateBudget(int $accountId, int $budgetId, UpdateBudgetDTO $updateBudgetDTO): Budget
    {
        $account = $this->getAccountById($accountId);

        $budget = $this->budgetRepository->findByIdAndAccount($budgetId, $account);
        if (!$budget) {
            throw new NotFoundHttpException("Budget with ID {$budgetId} not found for account {$accountId}");
        }

        // Update only the basic budget properties
        $budget->setName($updateBudgetDTO->name);

        return $this->budgetRepository->save($budget);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function deleteBudget(int $accountId, int $budgetId): void
    {
        $account = $this->getAccountById($accountId);

        $budget = $this->budgetRepository->findByIdAndAccount($budgetId, $account);
        if (!$budget) {
            throw new NotFoundHttpException("Budget with ID {$budgetId} not found for account {$accountId}");
        }

        $this->budgetRepository->delete($budget);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function findBudgetsByAccount(int $accountId): array
    {
        $account = $this->getAccountById($accountId);

        return $this->budgetRepository->findByAccount($account);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function findBudgetsForMonth(int $accountId, string $monthYear): array
    {
        $account = $this->getAccountById($accountId);

        return $this->budgetRepository->findBudgetsWithActiveVersionForMonth($account, $monthYear);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function findBudgetById(int $accountId, int $budgetId, bool $withDetails = false): Budget
    {
        $account = $this->getAccountById($accountId);

        $budget = $withDetails
            ? $this->budgetRepository->findWithVersionsAndCategories($budgetId, $account)
            : $this->budgetRepository->findByIdAndAccount($budgetId, $account);

        if (!$budget) {
            throw new NotFoundHttpException("Budget with ID {$budgetId} not found for account {$accountId}");
        }

        return $budget;
    }

    /**
     * @throws NotFoundHttpException
     */
    public function assignCategoriesToBudget(int $accountId, int $budgetId, array $categoryIds): Budget
    {
        $account = $this->getAccountById($accountId);

        $budget = $this->budgetRepository->findByIdAndAccount($budgetId, $account);
        if (!$budget) {
            throw new NotFoundHttpException("Budget with ID {$budgetId} not found for account {$accountId}");
        }

        // Clear existing categories
        foreach ($budget->getCategories() as $category) {
            $budget->removeCategory($category);
        }

        // Assign new categories
        foreach ($categoryIds as $categoryId) {
            $category = $this->categoryRepository->find($categoryId);
            if ($category) {
                $category->setBudget($budget);
            }
        }

        return $this->budgetRepository->save($budget);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function removeCategoryFromBudget(int $accountId, int $budgetId, int $categoryId): Budget
    {
        $account = $this->getAccountById($accountId);

        $budget = $this->budgetRepository->findByIdAndAccount($budgetId, $account);
        if (!$budget) {
            throw new NotFoundHttpException("Budget with ID {$budgetId} not found for account {$accountId}");
        }

        $category = $this->categoryRepository->find($categoryId);
        if (!$category) {
            throw new NotFoundHttpException("Category with ID {$categoryId} not found");
        }

        // Check if category is actually assigned to this budget
        if ($category->getBudget() !== $budget) {
            throw new NotFoundHttpException("Category {$categoryId} is not assigned to budget {$budgetId}");
        }

        // Remove category from budget
        $category->setBudget(null);

        return $this->budgetRepository->save($budget);
    }

    private function getAccountById(int $accountId): Account
    {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new NotFoundHttpException("Account with ID {$accountId} not found");
        }
        return $account;
    }

}