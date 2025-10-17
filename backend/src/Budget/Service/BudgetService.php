<?php

namespace App\Budget\Service;

use App\Account\Repository\AccountRepository;
use App\Budget\DTO\BudgetSummaryDTO;
use App\Budget\DTO\CreateBudgetDTO;
use App\Budget\DTO\UpdateBudgetDTO;
use App\Budget\Repository\BudgetRepository;
use App\Category\Repository\CategoryRepository;
use App\Entity\Account;
use App\Entity\Budget;
use App\Entity\BudgetVersion;
use App\Enum\BudgetType;
use App\Money\MoneyFactory;
use App\Transaction\Repository\TransactionRepository;
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
    private TransactionRepository $transactionRepository;
    private MoneyFactory $moneyFactory;

    public function __construct(
        AccountRepository $accountRepository,
        BudgetRepository $budgetRepository,
        BudgetVersionService $budgetVersionService,
        CategoryRepository $categoryRepository,
        TransactionRepository $transactionRepository,
        MoneyFactory $moneyFactory
    ) {
        $this->accountRepository = $accountRepository;
        $this->budgetRepository = $budgetRepository;
        $this->budgetVersionService = $budgetVersionService;
        $this->categoryRepository = $categoryRepository;
        $this->transactionRepository = $transactionRepository;
        $this->moneyFactory = $moneyFactory;
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
        $budget->setBudgetType(BudgetType::from($createBudgetDTO->budgetType));

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
        if ($updateBudgetDTO->name !== null) {
            $budget->setName($updateBudgetDTO->name);
        }

        if ($updateBudgetDTO->budgetType !== null) {
            $budget->setBudgetType(BudgetType::from($updateBudgetDTO->budgetType));
        }

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

    /**
     * Haalt budget summaries op voor een specifieke maand.
     *
     * @throws NotFoundHttpException
     */
    public function getBudgetSummariesForMonth(int $accountId, string $monthYear): array
    {
        $account = $this->getAccountById($accountId);

        // Haal alle budgetten op met hun categorieën en versies voor deze maand
        $budgets = $this->budgetRepository->findBudgetsWithCategoriesForMonth($account, $monthYear);

        $summaries = [];
        foreach ($budgets as $budget) {
            $summaries[] = $this->createBudgetSummary($budget, $monthYear, $accountId);
        }

        return $summaries;
    }

    /**
     * Creëert een budget summary voor een specifiek budget en maand.
     */
    private function createBudgetSummary(Budget $budget, string $monthYear, int $accountId): BudgetSummaryDTO
    {
        $summary = new BudgetSummaryDTO();
        $summary->budgetId = $budget->getId();
        $summary->budgetName = $budget->getName();
        $summary->budgetType = $budget->getBudgetType()->value;
        $summary->monthYear = $monthYear;

        // Haal effectieve versie op voor deze maand
        $effectiveVersion = $budget->getEffectiveVersion($monthYear);
        $allocatedMoney = $effectiveVersion ? $effectiveVersion->getMonthlyAmount() : Money::EUR(0);
        $summary->allocatedAmount = $this->moneyFactory->toFloat($allocatedMoney);

        // Verzamel alle categorie IDs die bij dit budget horen
        $categoryIds = [];
        foreach ($budget->getCategories() as $category) {
            $categoryIds[] = $category->getId();
        }
        $summary->categoryCount = count($categoryIds);

        // Bereken totaal uitgegeven in deze maand
        $spentInCents = $this->transactionRepository->getTotalSpentByCategoriesInMonth($categoryIds, $monthYear);
        $summary->spentAmount = $this->moneyFactory->toFloat(Money::EUR($spentInCents));

        // Bereken remaining en percentage
        $allocatedInCents = (int) $allocatedMoney->getAmount();
        $remainingInCents = $allocatedInCents - $spentInCents;
        $summary->remainingAmount = $this->moneyFactory->toFloat(Money::EUR($remainingInCents));

        if ($allocatedInCents > 0) {
            $summary->spentPercentage = round(($spentInCents / $allocatedInCents) * 100, 1);
        } else {
            $summary->spentPercentage = 0.0;
        }

        $summary->isOverspent = $spentInCents > $allocatedInCents;

        // Bepaal status
        if ($summary->isOverspent) {
            $summary->status = 'over';
        } elseif ($summary->spentPercentage >= 90) {
            $summary->status = 'warning';
        } elseif ($summary->spentPercentage >= 50) {
            $summary->status = 'good';
        } else {
            $summary->status = 'excellent';
        }

        // Bereken trend data
        $this->calculateTrendData($summary, $accountId, $categoryIds, $spentInCents);

        return $summary;
    }

    /**
     * Berekent trend informatie voor een budget summary.
     */
    private function calculateTrendData(BudgetSummaryDTO $summary, int $accountId, array $categoryIds, int $currentSpentInCents): void
    {
        if (empty($categoryIds)) {
            $summary->historicalMedian = 0.0;
            $summary->trendPercentage = 0.0;
            $summary->trendDirection = 'stable';
            return;
        }

        // Haal laatste 12 maanden op (exclusief eerste en huidige maand)
        $monthlyTotals = $this->transactionRepository->getMonthlySpentByCategories($accountId, $categoryIds, 12);

        if (empty($monthlyTotals)) {
            $summary->historicalMedian = 0.0;
            $summary->trendPercentage = 0.0;
            $summary->trendDirection = 'stable';
            return;
        }

        // Bereken mediaan
        $medianInCents = $this->calculateMedian($monthlyTotals);
        $summary->historicalMedian = $this->moneyFactory->toFloat(Money::EUR($medianInCents));

        // Bereken trend percentage
        if ($medianInCents > 0) {
            $difference = $currentSpentInCents - $medianInCents;
            $summary->trendPercentage = round(($difference / $medianInCents) * 100, 1);

            // Bepaal richting (significant vanaf 10% verschil)
            if ($summary->trendPercentage > 10) {
                $summary->trendDirection = 'up';
            } elseif ($summary->trendPercentage < -10) {
                $summary->trendDirection = 'down';
            } else {
                $summary->trendDirection = 'stable';
            }
        } else {
            $summary->trendPercentage = 0.0;
            $summary->trendDirection = 'stable';
        }
    }

    /**
     * Berekent de mediaan van maandelijkse totalen.
     */
    private function calculateMedian(array $monthlyTotals): int
    {
        if (empty($monthlyTotals)) {
            return 0;
        }

        // Extract alleen de totalen en sorteer
        $amounts = array_map(fn($row) => (int) $row['total'], $monthlyTotals);
        sort($amounts);

        $count = count($amounts);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            // Even aantal: gemiddelde van twee middelste waarden
            return (int) (($amounts[$middle - 1] + $amounts[$middle]) / 2);
        } else {
            // Oneven aantal: middelste waarde
            return $amounts[$middle];
        }
    }

    private function getAccountById(int $accountId): Account
    {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new NotFoundHttpException("Account with ID {$accountId} not found");
        }
        return $account;
    }

    /**
     * Get statistics for uncategorized transactions
     * @param int $accountId
     * @param string $monthYear
     * @return array
     */
    public function getUncategorizedTransactionStats(int $accountId, string $monthYear): array
    {
        $stats = $this->transactionRepository->getUncategorizedStats($accountId, $monthYear);

        return [
            'totalAmount' => $this->moneyFactory->toFloat(Money::EUR($stats['total_amount'])),
            'count' => $stats['count']
        ];
    }

}