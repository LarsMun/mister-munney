<?php

declare(strict_types=1);

namespace App\Budget\Service;

use App\Budget\DTO\SankeyFlowDTO;
use App\Budget\DTO\SankeyNodeDTO;
use App\Budget\DTO\SankeyLinkDTO;
use App\Budget\Repository\BudgetRepository;
use App\Entity\Account;
use App\Entity\Budget;
use App\Entity\Category;
use App\Enum\BudgetType;
use App\Money\MoneyFactory;
use App\Transaction\Repository\TransactionRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class SankeyFlowService
{
    public function __construct(
        private readonly BudgetRepository $budgetRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MoneyFactory $moneyFactory
    ) {
    }

    /**
     * Generate Sankey flow data for an account in a date range
     *
     * @param Account $account
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @param string $mode 'actual' | 'median'
     * @return SankeyFlowDTO
     */
    public function generateFlowData(
        Account $account,
        string $startDate,
        string $endDate,
        string $mode = 'actual'
    ): SankeyFlowDTO {
        $dto = new SankeyFlowDTO();
        $dto->mode = $mode;

        $nodes = [];
        $links = [];
        $nodeIndex = 0;

        // 1. Get all budgets for account (excluding PROJECT type)
        $allBudgets = $this->budgetRepository->findByAccount($account);
        $incomeBudgets = array_filter($allBudgets, fn(Budget $b) => $b->getBudgetType() === BudgetType::INCOME);
        $expenseBudgets = array_filter($allBudgets, fn(Budget $b) => $b->getBudgetType() === BudgetType::EXPENSE);

        // Reindex arrays
        $incomeBudgets = array_values($incomeBudgets);
        $expenseBudgets = array_values($expenseBudgets);

        // 2. Build nodes: Income Budgets (column 0)
        $incomeBudgetIndices = [];
        foreach ($incomeBudgets as $budget) {
            $node = new SankeyNodeDTO();
            $node->name = $budget->getName();
            $node->type = 'income_budget';
            $node->id = $budget->getId();
            $nodes[] = $node;
            $incomeBudgetIndices[$budget->getId()] = $nodeIndex++;
        }

        // 3. Build nodes: Income Categories (column 1)
        $incomeCategoryIndices = [];
        foreach ($incomeBudgets as $budget) {
            foreach ($budget->getCategories() as $category) {
                // Avoid duplicate categories
                if (!isset($incomeCategoryIndices[$category->getId()])) {
                    $node = new SankeyNodeDTO();
                    $node->name = $category->getName();
                    $node->type = 'income_category';
                    $node->id = $category->getId();
                    $node->color = $category->getColor();
                    $nodes[] = $node;
                    $incomeCategoryIndices[$category->getId()] = $nodeIndex++;
                }
            }
        }

        // 4. Build node: Total (column 2)
        $totalNode = new SankeyNodeDTO();
        $totalNode->name = 'Totaal';
        $totalNode->type = 'total';
        $nodes[] = $totalNode;
        $totalNodeIndex = $nodeIndex++;

        // 5. Build nodes: Expense Budgets (column 3)
        $expenseBudgetIndices = [];
        foreach ($expenseBudgets as $budget) {
            $node = new SankeyNodeDTO();
            $node->name = $budget->getName();
            $node->type = 'expense_budget';
            $node->id = $budget->getId();
            $nodes[] = $node;
            $expenseBudgetIndices[$budget->getId()] = $nodeIndex++;
        }

        // 6. Build nodes: Expense Categories (column 4)
        $expenseCategoryIndices = [];
        foreach ($expenseBudgets as $budget) {
            foreach ($budget->getCategories() as $category) {
                // Avoid duplicate categories
                if (!isset($expenseCategoryIndices[$category->getId()])) {
                    $node = new SankeyNodeDTO();
                    $node->name = $category->getName();
                    $node->type = 'expense_category';
                    $node->id = $category->getId();
                    $node->color = $category->getColor();
                    $nodes[] = $node;
                    $expenseCategoryIndices[$category->getId()] = $nodeIndex++;
                }
            }
        }

        // 7. Calculate amounts and build links
        $totalIncome = 0.0;
        $totalExpense = 0.0;

        // Process Income Budgets
        foreach ($incomeBudgets as $budget) {
            $categoryAmounts = $this->getCategoryAmounts($budget, $startDate, $endDate, $mode);

            foreach ($categoryAmounts as $categoryId => $amount) {
                if ($amount > 0 && isset($incomeCategoryIndices[$categoryId])) {
                    // Link: Income Budget -> Income Category
                    $link = new SankeyLinkDTO();
                    $link->source = $incomeBudgetIndices[$budget->getId()];
                    $link->target = $incomeCategoryIndices[$categoryId];
                    $link->value = $amount;
                    $links[] = $link;

                    // Link: Income Category -> Total
                    $link2 = new SankeyLinkDTO();
                    $link2->source = $incomeCategoryIndices[$categoryId];
                    $link2->target = $totalNodeIndex;
                    $link2->value = $amount;
                    $links[] = $link2;

                    $totalIncome += $amount;
                }
            }
        }

        // Process Expense Budgets
        foreach ($expenseBudgets as $budget) {
            $categoryAmounts = $this->getCategoryAmounts($budget, $startDate, $endDate, $mode);
            $budgetTotal = 0.0;

            foreach ($categoryAmounts as $categoryId => $amount) {
                if ($amount > 0 && isset($expenseCategoryIndices[$categoryId])) {
                    $budgetTotal += $amount;
                }
            }

            if ($budgetTotal > 0) {
                // Link: Total -> Expense Budget
                $link = new SankeyLinkDTO();
                $link->source = $totalNodeIndex;
                $link->target = $expenseBudgetIndices[$budget->getId()];
                $link->value = $budgetTotal;
                $links[] = $link;

                $totalExpense += $budgetTotal;

                // Links: Expense Budget -> Expense Categories
                foreach ($categoryAmounts as $categoryId => $amount) {
                    if ($amount > 0 && isset($expenseCategoryIndices[$categoryId])) {
                        $link2 = new SankeyLinkDTO();
                        $link2->source = $expenseBudgetIndices[$budget->getId()];
                        $link2->target = $expenseCategoryIndices[$categoryId];
                        $link2->value = $amount;
                        $links[] = $link2;
                    }
                }
            }
        }

        $dto->nodes = $nodes;
        $dto->links = $links;
        $dto->totalIncome = $totalIncome;
        $dto->totalExpense = $totalExpense;
        $dto->netFlow = $totalIncome - $totalExpense;

        return $dto;
    }

    /**
     * Get category-level amounts for a budget (actual or median)
     *
     * @return array<int, float> categoryId => amount in euros
     */
    private function getCategoryAmounts(
        Budget $budget,
        string $startDate,
        string $endDate,
        string $mode
    ): array {
        $categories = $budget->getCategories();

        if ($categories->isEmpty()) {
            return [];
        }

        $categoryIds = array_map(fn(Category $cat) => $cat->getId(), $categories->toArray());

        if ($mode === 'median') {
            return $this->getCategoryMedians($categoryIds, $startDate);
        }

        // Actual mode: get breakdown for date range
        $breakdown = $this->transactionRepository->getCategoryBreakdownForDateRange(
            $categoryIds,
            $startDate,
            $endDate
        );

        $result = [];
        foreach ($breakdown as $row) {
            $amountCents = (int) $row['totalAmount'];
            // Convert cents to euros, take absolute value
            $result[$row['categoryId']] = abs($amountCents / 100);
        }

        return $result;
    }

    /**
     * Calculate 6-month median for categories
     *
     * @param int[] $categoryIds
     * @param string $beforeDate YYYY-MM-DD - calculate median from months before this date
     * @return array<int, float> categoryId => median amount in euros
     */
    private function getCategoryMedians(array $categoryIds, string $beforeDate): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $beforeDateObj = new DateTimeImmutable($beforeDate);

        // Build list of 6 months before the given date
        $monthsList = [];
        $date = $beforeDateObj->modify('-1 month'); // Exclude current month

        for ($i = 0; $i < 6; $i++) {
            $monthsList[] = $date->format('Y-m');
            $date = $date->modify('-1 month');
        }

        // Build placeholders
        $categoryPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($monthsList), '?'));

        // Query to get monthly totals per category
        $sql = "
            SELECT
                t.category_id AS categoryId,
                SUBSTRING(t.date, 1, 7) AS month,
                SUM(
                    CASE
                        WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
                        THEN
                            CASE WHEN t.transaction_type = 'CREDIT'
                            THEN -(t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0))
                            ELSE (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0))
                            END
                        ELSE
                            CASE WHEN t.transaction_type = 'CREDIT' THEN -t.amount ELSE t.amount END
                    END
                ) AS total
            FROM transaction t
            WHERE t.category_id IN ($categoryPlaceholders)
            AND SUBSTRING(t.date, 1, 7) IN ($monthPlaceholders)
            AND (
                (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                OR
                (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
            )
            GROUP BY t.category_id, month
        ";

        $params = array_merge($categoryIds, $monthsList);
        $results = $this->entityManager->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

        // Organize results by category
        $categoryMonthlyTotals = [];
        foreach ($categoryIds as $categoryId) {
            $categoryMonthlyTotals[$categoryId] = [];
        }

        foreach ($results as $row) {
            $categoryId = (int) $row['categoryId'];
            $total = abs((int) $row['total']); // Take absolute value
            $categoryMonthlyTotals[$categoryId][] = $total;
        }

        // Calculate median for each category
        $medians = [];
        foreach ($categoryMonthlyTotals as $categoryId => $totals) {
            if (empty($totals)) {
                $medians[$categoryId] = 0.0;
                continue;
            }

            sort($totals);
            $count = count($totals);

            if ($count % 2 === 0) {
                $mid1 = $totals[($count / 2) - 1];
                $mid2 = $totals[$count / 2];
                $medianCents = (int) (($mid1 + $mid2) / 2);
            } else {
                $medianCents = $totals[(int) floor($count / 2)];
            }

            // Convert cents to euros
            $medians[$categoryId] = $medianCents / 100;
        }

        return $medians;
    }
}
