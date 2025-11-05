<?php

namespace App\Budget\Service;

use App\Budget\Repository\BudgetRepository;
use App\Entity\Budget;
use App\Enum\BudgetType;
use App\Enum\ProjectStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ActiveBudgetService
{
    private int $defaultMonths;

    public function __construct(
        private readonly BudgetRepository $budgetRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $params,
        private readonly ProjectStatusCalculator $statusCalculator
    ) {
        // Get from env or default to 2
        $this->defaultMonths = (int) ($_ENV['MUNNEY_ACTIVE_BUDGET_MONTHS'] ?? 2);
    }

    /**
     * Get all active budgets (EXPENSE/INCOME with recent transactions, or ACTIVE projects)
     */
    public function getActiveBudgets(?int $months = null, ?BudgetType $type = null, ?int $accountId = null): array
    {
        $months = $months ?? $this->defaultMonths;

        // Filter by account if specified
        if ($accountId !== null) {
            $allBudgets = $this->budgetRepository->findBy(['account' => $accountId]);
        } else {
            $allBudgets = $this->budgetRepository->findAll();
        }

        $active = [];

        foreach ($allBudgets as $budget) {
            // Filter by type if specified
            if ($type !== null && $budget->getBudgetType() !== $type) {
                continue;
            }

            if ($this->isActive($budget, $months)) {
                $active[] = $budget;
            }
        }

        return $active;
    }

    /**
     * Get all older/inactive budgets
     */
    public function getOlderBudgets(?int $months = null, ?BudgetType $type = null, ?int $accountId = null): array
    {
        $months = $months ?? $this->defaultMonths;

        // Filter by account if specified
        if ($accountId !== null) {
            $allBudgets = $this->budgetRepository->findBy(['account' => $accountId]);
        } else {
            $allBudgets = $this->budgetRepository->findAll();
        }

        $older = [];

        foreach ($allBudgets as $budget) {
            // Filter by type if specified
            if ($type !== null && $budget->getBudgetType() !== $type) {
                continue;
            }

            if (!$this->isActive($budget, $months)) {
                $older[] = $budget;
            }
        }

        return $older;
    }

    /**
     * Determine if a budget is "active"
     *
     * For EXPENSE/INCOME: active if has ≥1 transaction in last N months
     * For PROJECT: active if calculated status is ACTIVE (based on last payment date and duration)
     */
    public function isActive(Budget $budget, ?int $months = null): bool
    {
        $months = $months ?? $this->defaultMonths;

        // PROJECT budgets: calculate status based on transaction dates
        if ($budget->getBudgetType() === BudgetType::PROJECT) {
            return $this->isProjectActive($budget);
        }

        // EXPENSE/INCOME budgets: check for recent transactions
        return $this->hasRecentTransactions($budget, $months);
    }

    /**
     * Check if a project budget is active
     */
    private function isProjectActive(Budget $budget): bool
    {
        // Calculate status dynamically based on transaction dates and duration
        $calculatedStatus = $this->statusCalculator->calculateStatus($budget);

        return $calculatedStatus === ProjectStatus::ACTIVE;
    }

    /**
     * Check if a budget has transactions in the last N months
     */
    private function hasRecentTransactions(Budget $budget, int $months): bool
    {
        // Calculate cutoff date (N months ago from start of current month)
        $cutoffDate = (new DateTimeImmutable())
            ->modify('first day of this month')
            ->modify("-{$months} months");

        // Get all categories for this budget
        $categories = $budget->getCategories();

        if ($categories->isEmpty()) {
            return false;
        }

        // Build query to check for transactions in these categories since cutoff
        $categoryIds = array_map(fn($cat) => $cat->getId(), $categories->toArray());

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(t.id)')
            ->from('App\Entity\Transaction', 't')
            ->where('t.category IN (:categoryIds)')
            ->andWhere('t.date >= :cutoffDate')
            ->setParameter('categoryIds', $categoryIds)
            ->setParameter('cutoffDate', $cutoffDate);

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Get the cutoff date for active budget calculation
     */
    public function getCutoffDate(?int $months = null): DateTimeImmutable
    {
        $months = $months ?? $this->defaultMonths;

        return (new DateTimeImmutable())
            ->modify('first day of this month')
            ->modify("-{$months} months");
    }
}
