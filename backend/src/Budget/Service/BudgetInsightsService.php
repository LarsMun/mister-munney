<?php

namespace App\Budget\Service;

use App\Entity\Budget;
use App\Enum\BudgetType;
use App\Money\MoneyFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Money\Money;

class BudgetInsightsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MoneyFactory $moneyFactory
    ) {
    }

    /**
     * Compute insights for active budgets (only EXPENSE/INCOME, not PROJECT)
     *
     * @param Budget[] $budgets
     * @param int|null $limit Max number of insights to return
     * @param string|null $startDate Selected period start date (YYYY-MM-DD)
     * @param string|null $endDate Selected period end date (YYYY-MM-DD)
     * @return array
     */
    public function computeInsights(array $budgets, ?int $limit = 3, ?string $startDate = null, ?string $endDate = null): array
    {
        $insights = [];

        foreach ($budgets as $budget) {
            // Skip PROJECT budgets - insights only for recurring budgets
            if ($budget->getBudgetType() === BudgetType::PROJECT) {
                continue;
            }

            $insight = $this->computeBudgetInsight($budget, $startDate, $endDate);

            if ($insight) {
                $insights[] = $insight;
            }
        }

        // Return all insights (no sorting needed - just display spending data)
        return $limit ? array_slice($insights, 0, $limit) : $insights;
    }

    /**
     * Compute insight for a single budget
     *
     * @param Budget $budget
     * @param string|null $startDate Selected period start date (YYYY-MM-DD), defaults to current month
     * @param string|null $endDate Selected period end date (YYYY-MM-DD), defaults to current month
     * @return array|null
     */
    public function computeBudgetInsight(Budget $budget, ?string $startDate = null, ?string $endDate = null): ?array
    {
        // Parse dates
        $start = $startDate ? new DateTimeImmutable($startDate) : (new DateTimeImmutable())->modify('first day of this month');
        $end = $endDate ? new DateTimeImmutable($endDate) : (new DateTimeImmutable())->modify('last day of this month');

        // Get current period total
        $current = $this->getSelectedPeriodTotal($budget, $startDate, $endDate);

        // Get normal (median of 6 months prior)
        $normal = $this->computeNormal($budget, 6, $start);

        // Get sparkline (last 6 periods before current)
        $sparkline = $this->getSparklineData($budget, 6, $start);

        // Calculate previous period
        $previousPeriodData = $this->calculatePreviousPeriod($budget, $start, $end);

        // Calculate same period last year
        $lastYearData = $this->calculateSamePeriodLastYear($budget, $start, $end);

        // Determine period type for labels
        $periodType = $this->determinePeriodType($start, $end);

        return [
            'budgetId' => $budget->getId(),
            'budgetName' => $budget->getName(),
            'current' => $this->moneyFactory->toString($current),
            'normal' => $this->moneyFactory->toString($normal),
            'previousPeriod' => $previousPeriodData['amount'] ? $this->moneyFactory->toString($previousPeriodData['amount']) : null,
            'previousPeriodLabel' => $previousPeriodData['label'],
            'lastYear' => $lastYearData ? $this->moneyFactory->toString($lastYearData) : null,
            'sparkline' => $sparkline,
            'periodType' => $periodType,
        ];
    }

    /**
     * Compute "normal" (rolling median over last N complete months before the given date)
     */
    public function computeNormal(Budget $budget, int $months = 6, ?DateTimeImmutable $beforeDate = null): Money
    {
        $monthlyTotals = $this->getMonthlyTotals($budget, $months, excludeCurrentMonth: true, beforeDate: $beforeDate);

        if (empty($monthlyTotals)) {
            return $this->moneyFactory->zero();
        }

        // Calculate median
        $values = array_values($monthlyTotals);
        sort($values);
        $count = count($values);

        if ($count === 0) {
            return $this->moneyFactory->zero();
        }

        if ($count % 2 === 0) {
            // Even number: average of two middle values
            $mid1 = $values[($count / 2) - 1];
            $mid2 = $values[$count / 2];
            $medianCents = (int) (($mid1 + $mid2) / 2);
        } else {
            // Odd number: middle value
            $medianCents = $values[(int) floor($count / 2)];
        }

        return $this->moneyFactory->fromCents($medianCents);
    }

    /**
     * Get sparkline data (last N months before the given date)
     */
    public function getSparklineData(Budget $budget, int $months = 6, ?DateTimeImmutable $beforeDate = null): array
    {
        $monthlyTotals = $this->getMonthlyTotals($budget, $months, excludeCurrentMonth: true, beforeDate: $beforeDate);

        // Return as array of float values for the frontend
        return array_map(
            fn($cents) => $this->moneyFactory->toFloat($this->moneyFactory->fromCents($cents)),
            array_values($monthlyTotals)
        );
    }

    /**
     * Get total for the selected period (or current month if not specified)
     *
     * @param Budget $budget
     * @param string|null $startDate YYYY-MM-DD
     * @param string|null $endDate YYYY-MM-DD
     * @return Money
     */
    private function getSelectedPeriodTotal(Budget $budget, ?string $startDate, ?string $endDate): Money
    {
        // Use provided dates or default to current month
        if ($startDate && $endDate) {
            $start = new DateTimeImmutable($startDate);
            $end = new DateTimeImmutable($endDate);
        } else {
            $start = (new DateTimeImmutable())->modify('first day of this month');
            $end = (new DateTimeImmutable())->modify('last day of this month');
        }

        return $this->getDateRangeTotal($budget, $start, $end);
    }

    /**
     * Get current month total for a budget
     */
    private function getCurrentMonthTotal(Budget $budget): Money
    {
        $startOfMonth = (new DateTimeImmutable())->modify('first day of this month');
        $endOfMonth = (new DateTimeImmutable())->modify('last day of this month');

        return $this->getDateRangeTotal($budget, $startOfMonth, $endOfMonth);
    }

    /**
     * Get monthly totals for a budget over N months
     *
     * @param Budget $budget
     * @param int $months Number of months to retrieve
     * @param bool $excludeCurrentMonth Whether to exclude the current month
     * @param DateTimeImmutable|null $beforeDate Calculate months before this date (defaults to now)
     * @return array<string, int> Key: YYYY-MM, Value: cents
     */
    private function getMonthlyTotals(Budget $budget, int $months, bool $excludeCurrentMonth, ?DateTimeImmutable $beforeDate = null): array
    {
        $categories = $budget->getCategories();

        if ($categories->isEmpty()) {
            return [];
        }

        $categoryIds = array_map(fn($cat) => $cat->getId(), $categories->toArray());

        // Build month list
        $monthsList = [];
        $date = $beforeDate ?? new DateTimeImmutable();

        if ($excludeCurrentMonth) {
            $date = $date->modify('-1 month');
        }

        for ($i = 0; $i < $months; $i++) {
            $monthKey = $date->format('Y-m');
            $monthsList[] = $monthKey;
            $date = $date->modify('-1 month');
        }

        $monthsList = array_reverse($monthsList); // Oldest first

        // Build placeholders for IN clause
        $categoryPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($monthsList), '?'));

        // Native SQL query - DQL doesn't support subqueries in SELECT clause
        // CREDIT transactions are subtracted (refunds), DEBIT transactions are added (expenses)
        // Include parents with adjusted amount (parent - categorized children)
        $sql = "
            SELECT
                SUBSTRING(t.date, 1, 7) as month,
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
                ) as total
            FROM transaction t
            WHERE t.category_id IN ($categoryPlaceholders)
            AND SUBSTRING(t.date, 1, 7) IN ($monthPlaceholders)
            AND (
                (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                OR
                (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
            )
            GROUP BY month
        ";

        $params = array_merge($categoryIds, $monthsList);
        $results = $this->entityManager->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

        // Build map with defaults of 0
        $totals = array_fill_keys($monthsList, 0);

        foreach ($results as $row) {
            $totals[$row['month']] = (int) $row['total'];
        }

        return $totals;
    }

    /**
     * Get total for a date range
     */
    private function getDateRangeTotal(Budget $budget, DateTimeImmutable $start, DateTimeImmutable $end): Money
    {
        $categories = $budget->getCategories();

        if ($categories->isEmpty()) {
            return $this->moneyFactory->zero();
        }

        $categoryIds = array_map(fn($cat) => $cat->getId(), $categories->toArray());

        // Build placeholders for IN clause
        $categoryPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));

        // Native SQL query - DQL doesn't support subqueries in SELECT clause
        // CREDIT transactions are subtracted (refunds), DEBIT transactions are added (expenses)
        // Include parents with adjusted amount (parent - categorized children)
        $sql = "
            SELECT
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
                ) as total
            FROM transaction t
            WHERE t.category_id IN ($categoryPlaceholders)
            AND t.date >= ?
            AND t.date <= ?
            AND (
                (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                OR
                (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
            )
        ";

        $params = array_merge($categoryIds, [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        $result = $this->entityManager->getConnection()->executeQuery($sql, $params)->fetchOne();

        return $this->moneyFactory->fromCents((int) ($result ?? 0));
    }

    /**
     * Calculate percentage delta between current and normal
     */
    private function calculatePercentageDelta(Money $current, Money $normal): float
    {
        if ($normal->isZero()) {
            return 0.0;
        }

        $currentFloat = $this->moneyFactory->toFloat($current);
        $normalFloat = $this->moneyFactory->toFloat($normal);

        return (($currentFloat - $normalFloat) / $normalFloat) * 100;
    }

    /**
     * Generate neutral, coaching-style message based on delta percentage
     */
    private function generateInsightMessage(string $budgetName, float $deltaPercent): string
    {
        $absDelta = abs($deltaPercent);

        // |Δ| < 10% → Stable
        if ($absDelta < 10) {
            return "Stabiel.";
        }

        // 10% ≤ Δ < 30% → Slightly higher/lower
        if ($absDelta < 30) {
            $direction = $deltaPercent > 0 ? 'hoger' : 'lager';
            return "Iets {$direction} dan normaal.";
        }

        // Δ ≥ 30% → Noticeably higher/lower
        $direction = $deltaPercent > 0 ? 'hoger' : 'lager';
        return "Opvallend {$direction} dan jouw gebruikelijke niveau.";
    }

    /**
     * Get insight level for UI styling
     */
    private function getInsightLevel(float $deltaPercent): string
    {
        $absDelta = abs($deltaPercent);

        if ($absDelta < 10) {
            return 'stable';
        }

        if ($absDelta < 30) {
            return 'slight';
        }

        return 'anomaly';
    }

    /**
     * Calculate previous period total and label
     *
     * @return array{amount: Money|null, label: string}
     */
    private function calculatePreviousPeriod(Budget $budget, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $daysDiff = $start->diff($end)->days + 1;

        // Calculate previous period start and end
        $previousEnd = $start->modify('-1 day');
        $previousStart = $previousEnd->modify("-{$daysDiff} days")->modify('+1 day');

        // Determine label based on period length
        if ($daysDiff <= 31) {
            $label = 'Vorige maand';
        } elseif ($daysDiff >= 85 && $daysDiff <= 95) {
            $label = 'Vorig kwartaal';
        } elseif ($daysDiff >= 170 && $daysDiff <= 190) {
            $label = 'Vorig half jaar';
        } elseif ($daysDiff >= 360) {
            $label = 'Vorig jaar';
        } else {
            $label = 'Vorige periode';
        }

        $amount = $this->getDateRangeTotal($budget, $previousStart, $previousEnd);

        return [
            'amount' => $amount->isZero() ? null : $amount,
            'label' => $label
        ];
    }

    /**
     * Calculate same period last year
     */
    private function calculateSamePeriodLastYear(Budget $budget, DateTimeImmutable $start, DateTimeImmutable $end): ?Money
    {
        $lastYearStart = $start->modify('-1 year');
        $lastYearEnd = $end->modify('-1 year');

        $amount = $this->getDateRangeTotal($budget, $lastYearStart, $lastYearEnd);

        return $amount->isZero() ? null : $amount;
    }

    /**
     * Determine period type from date range
     */
    private function determinePeriodType(DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        $daysDiff = $start->diff($end)->days + 1;

        if ($daysDiff <= 31) {
            return 'month';
        } elseif ($daysDiff >= 85 && $daysDiff <= 95) {
            return 'quarter';
        } elseif ($daysDiff >= 170 && $daysDiff <= 190) {
            return 'halfYear';
        } elseif ($daysDiff >= 360) {
            return 'year';
        }

        return 'custom';
    }
}
