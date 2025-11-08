<?php

namespace App\Budget\Service;

use App\Entity\Budget;
use App\Money\MoneyFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Money\Money;

class ProjectAggregatorService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MoneyFactory $moneyFactory
    ) {
    }

    /**
     * Get aggregated totals for a project
     */
    public function getProjectTotals(Budget $project): array
    {
        $trackedDebit = $this->getTrackedDebitTotal($project);
        $trackedCredit = $this->getTrackedCreditTotal($project);
        $tracked = $trackedDebit->subtract($trackedCredit); // Net tracked (debit - credit)

        $external = $this->getExternalTotal($project);

        // Total = Getrackte uitgaven (DEBIT) + Externe betalingen
        $total = $trackedDebit->add($external);

        return [
            'trackedDebit' => $this->moneyFactory->toString($trackedDebit),
            'trackedCredit' => $this->moneyFactory->toString($trackedCredit),
            'tracked' => $this->moneyFactory->toString($tracked),
            'external' => $this->moneyFactory->toString($external),
            'total' => $this->moneyFactory->toString($total),
            'categoryBreakdown' => $this->getCategoryBreakdown($project),
        ];
    }

    /**
     * Get all entries (transactions + external payments) for a project
     */
    public function getProjectEntries(Budget $project): array
    {
        $transactions = $this->getProjectTransactions($project);
        $externalPayments = $this->getProjectExternalPayments($project);

        // Merge and sort by date
        $entries = [];

        foreach ($transactions as $txn) {
            $entries[] = [
                'type' => 'transaction',
                'id' => $txn->getId(),
                'date' => $txn->getDate()->format('Y-m-d'),
                'description' => $txn->getDescription(),
                'amount' => $this->moneyFactory->toString($txn->getAmount()),
                'category' => $txn->getCategory()?->getName(),
                'transactionType' => $txn->getTransactionType()->value,
            ];
        }

        foreach ($externalPayments as $payment) {
            $entries[] = [
                'type' => 'external_payment',
                'id' => $payment->getId(),
                'date' => $payment->getPaidOn()->format('Y-m-d'),
                'description' => $payment->getNote(),
                'amount' => $this->moneyFactory->toString($payment->getAmount()),
                'payerSource' => $payment->getPayerSource()->value,
                'attachmentUrl' => $payment->getAttachmentUrl(),
            ];
        }

        // Sort by date descending
        usort($entries, fn($a, $b) => $b['date'] <=> $a['date']);

        return $entries;
    }

    /**
     * Get time series data for charts (monthly bars + cumulative line)
     */
    public function getProjectTimeSeries(Budget $project): array
    {
        // Use fixed date range: last 12 months
        $startDate = new DateTimeImmutable('-12 months');
        $endDate = new DateTimeImmutable();

        // Generate month list
        $months = $this->generateMonthList($startDate, $endDate);

        // Get monthly tracked totals
        $monthlyTracked = $this->getMonthlyTrackedTotals($project, $months);

        // Get monthly external totals
        $monthlyExternal = $this->getMonthlyExternalTotals($project, $months);

        // Build monthly bars
        $monthlyBars = [];
        $cumulative = 0;
        $cumulativeLine = [];

        foreach ($months as $month) {
            $trackedCents = $monthlyTracked[$month] ?? 0;
            $externalCents = $monthlyExternal[$month] ?? 0;
            $totalCents = $trackedCents + $externalCents;

            $monthlyBars[] = [
                'month' => $month,
                'tracked' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents($trackedCents)),
                'external' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents($externalCents)),
                'total' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents($totalCents)),
            ];

            $cumulative += $totalCents;
            $cumulativeLine[] = [
                'month' => $month,
                'cumulative' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents($cumulative)),
            ];
        }

        return [
            'monthlyBars' => $monthlyBars,
            'cumulativeLine' => $cumulativeLine,
        ];
    }

    /**
     * Get total from DEBIT tracked transactions (expenses)
     */
    private function getTrackedDebitTotal(Budget $project): Money
    {
        $categories = $project->getCategories();

        if ($categories->isEmpty()) {
            return $this->moneyFactory->zero();
        }

        $categoryIds = array_map(fn($cat) => $cat->getId(), $categories->toArray());

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        // Only DEBIT transactions (expenses)
        // Include parents with adjusted amount (parent - categorized children)
        // For children: DEBIT adds to total, CREDIT subtracts (refunds reduce total)
        $sql = "
            SELECT SUM(
                CASE
                    WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
                    THEN (t.amount - COALESCE((
                        SELECT SUM(
                            CASE WHEN st.transaction_type = 'DEBIT'
                            THEN ABS(st.amount)
                            ELSE -ABS(st.amount)
                            END
                        )
                        FROM transaction st
                        WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL
                    ), 0))
                    ELSE t.amount
                END
            ) as total
            FROM transaction t
            WHERE t.category_id IN ($placeholders)
            AND t.transaction_type = 'DEBIT'
            AND (
                (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                OR
                (t.amount - COALESCE((
                    SELECT SUM(
                        CASE WHEN st.transaction_type = 'DEBIT'
                        THEN ABS(st.amount)
                        ELSE -ABS(st.amount)
                        END
                    )
                    FROM transaction st
                    WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL
                ), 0)) != 0
            )
        ";

        $result = $this->entityManager->getConnection()->executeQuery($sql, $categoryIds)->fetchOne();

        return $this->moneyFactory->fromCents((int) ($result ?? 0));
    }

    /**
     * Get total from CREDIT tracked transactions (income/refunds)
     */
    private function getTrackedCreditTotal(Budget $project): Money
    {
        $categories = $project->getCategories();

        if ($categories->isEmpty()) {
            return $this->moneyFactory->zero();
        }

        $categoryIds = array_map(fn($cat) => $cat->getId(), $categories->toArray());

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        // Only CREDIT transactions (income/refunds)
        // Include parents with adjusted amount (parent - categorized children)
        // For children: DEBIT subtracts from total, CREDIT adds (both reduce parent amount)
        $sql = "
            SELECT SUM(
                CASE
                    WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
                    THEN (t.amount - COALESCE((
                        SELECT SUM(
                            CASE WHEN st.transaction_type = 'CREDIT'
                            THEN ABS(st.amount)
                            ELSE -ABS(st.amount)
                            END
                        )
                        FROM transaction st
                        WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL
                    ), 0))
                    ELSE t.amount
                END
            ) as total
            FROM transaction t
            WHERE t.category_id IN ($placeholders)
            AND t.transaction_type = 'CREDIT'
            AND (
                (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                OR
                (t.amount - COALESCE((
                    SELECT SUM(
                        CASE WHEN st.transaction_type = 'CREDIT'
                        THEN ABS(st.amount)
                        ELSE -ABS(st.amount)
                        END
                    )
                    FROM transaction st
                    WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL
                ), 0)) != 0
            )
        ";

        $result = $this->entityManager->getConnection()->executeQuery($sql, $categoryIds)->fetchOne();

        return $this->moneyFactory->fromCents((int) ($result ?? 0));
    }

    /**
     * Get total from external payments
     */
    private function getExternalTotal(Budget $project): Money
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('SUM(ep.amountInCents) as total')
            ->from('App\Entity\ExternalPayment', 'ep')
            ->where('ep.budget = :project')
            ->setParameter('project', $project);

        $result = $qb->getQuery()->getSingleScalarResult();

        return $this->moneyFactory->fromCents((int) ($result ?? 0));
    }

    /**
     * Get category breakdown (how much per category from tracked transactions)
     */
    private function getCategoryBreakdown(Budget $project): array
    {
        $categories = $project->getCategories();

        if ($categories->isEmpty()) {
            return [];
        }

        $categoryIds = array_map(fn($cat) => $cat->getId(), $categories->toArray());

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        // CREDIT transactions are subtracted (refunds), DEBIT transactions are added (expenses)
        // Include parents with adjusted amount (parent - categorized children)
        // For children: DEBIT adds to children total, CREDIT subtracts (refunds reduce total)
        $sql = "
            SELECT c.id, c.name,
            SUM(
                CASE
                    WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
                    THEN
                        CASE WHEN t.transaction_type = 'CREDIT'
                        THEN -(t.amount - COALESCE((
                            SELECT SUM(
                                CASE WHEN st.transaction_type = 'CREDIT'
                                THEN ABS(st.amount)
                                ELSE -ABS(st.amount)
                                END
                            )
                            FROM transaction st
                            WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL
                        ), 0))
                        ELSE (t.amount - COALESCE((
                            SELECT SUM(
                                CASE WHEN st.transaction_type = 'DEBIT'
                                THEN ABS(st.amount)
                                ELSE -ABS(st.amount)
                                END
                            )
                            FROM transaction st
                            WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL
                        ), 0))
                        END
                    ELSE
                        CASE WHEN t.transaction_type = 'CREDIT' THEN -t.amount ELSE t.amount END
                END
            ) as total
            FROM transaction t
            INNER JOIN category c ON t.category_id = c.id
            WHERE t.category_id IN ($placeholders)
            AND (
                (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                OR
                (t.amount - COALESCE((
                    SELECT SUM(
                        CASE WHEN t.transaction_type = 'DEBIT'
                        THEN CASE WHEN st.transaction_type = 'DEBIT' THEN ABS(st.amount) ELSE -ABS(st.amount) END
                        ELSE CASE WHEN st.transaction_type = 'CREDIT' THEN ABS(st.amount) ELSE -ABS(st.amount) END
                        END
                    )
                    FROM transaction st
                    WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL
                ), 0)) != 0
            )
            GROUP BY c.id, c.name
        ";

        $results = $this->entityManager->getConnection()->executeQuery($sql, $categoryIds)->fetchAllAssociative();

        return array_map(fn($row) => [
            'categoryId' => $row['id'],
            'categoryName' => $row['name'],
            'total' => $this->moneyFactory->toString($this->moneyFactory->fromCents((int) $row['total'])),
        ], $results);
    }

    /**
     * Get all transactions for a project (via categories)
     */
    private function getProjectTransactions(Budget $project): array
    {
        $categories = $project->getCategories();

        if ($categories->isEmpty()) {
            return [];
        }

        $categoryIds = array_map(fn($cat) => $cat->getId(), $categories->toArray());

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        // Exclude parent transactions only if adjusted amount is zero (fully categorized)
        // For children: same type as parent adds to total, opposite type subtracts
        $sql = "
            SELECT t.id
            FROM transaction t
            WHERE t.category_id IN ($placeholders)
            AND (
                (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                OR
                (t.amount - COALESCE((
                    SELECT SUM(
                        CASE WHEN t.transaction_type = 'DEBIT'
                        THEN CASE WHEN st.transaction_type = 'DEBIT' THEN ABS(st.amount) ELSE -ABS(st.amount) END
                        ELSE CASE WHEN st.transaction_type = 'CREDIT' THEN ABS(st.amount) ELSE -ABS(st.amount) END
                        END
                    )
                    FROM transaction st
                    WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL
                ), 0)) != 0
            )
        ";

        $results = $this->entityManager->getConnection()->executeQuery($sql, $categoryIds)->fetchAllAssociative();

        // Fetch full Transaction entities by IDs
        if (empty($results)) {
            return [];
        }

        $transactionIds = array_map(fn($row) => $row['id'], $results);
        $transactionRepository = $this->entityManager->getRepository('App\Entity\Transaction');

        return $transactionRepository->findBy(['id' => $transactionIds]);
    }

    /**
     * Get all external payments for a project
     */
    private function getProjectExternalPayments(Budget $project): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ep')
            ->from('App\Entity\ExternalPayment', 'ep')
            ->where('ep.budget = :project')
            ->orderBy('ep.paidOn', 'DESC')
            ->setParameter('project', $project);

        return $qb->getQuery()->getResult();
    }

    /**
     * Generate list of months between start and end dates
     */
    private function generateMonthList(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $months = [];
        $current = $start->modify('first day of this month');
        $endMonth = $end->modify('first day of this month');

        while ($current <= $endMonth) {
            $months[] = $current->format('Y-m');
            $current = $current->modify('+1 month');
        }

        return $months;
    }

    /**
     * Get monthly tracked totals (from transactions)
     */
    private function getMonthlyTrackedTotals(Budget $project, array $months): array
    {
        $categories = $project->getCategories();

        if ($categories->isEmpty()) {
            return array_fill_keys($months, 0);
        }

        $categoryIds = array_map(fn($cat) => $cat->getId(), $categories->toArray());

        // Build placeholders for IN clauses
        $categoryPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));

        // CREDIT transactions are subtracted (refunds), DEBIT transactions are added (expenses)
        // Include parents with adjusted amount (parent - categorized children)
        // For children: DEBIT adds to children total, CREDIT subtracts (refunds reduce total)
        $sql = "
            SELECT SUBSTRING(t.date, 1, 7) as month,
            SUM(
                CASE
                    WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
                    THEN
                        CASE WHEN t.transaction_type = 'CREDIT'
                        THEN -(t.amount - COALESCE((
                            SELECT SUM(
                                CASE WHEN st.transaction_type = 'CREDIT'
                                THEN ABS(st.amount)
                                ELSE -ABS(st.amount)
                                END
                            )
                            FROM transaction st
                            WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL
                        ), 0))
                        ELSE (t.amount - COALESCE((
                            SELECT SUM(
                                CASE WHEN st.transaction_type = 'DEBIT'
                                THEN ABS(st.amount)
                                ELSE -ABS(st.amount)
                                END
                            )
                            FROM transaction st
                            WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL
                        ), 0))
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
                (t.amount - COALESCE((
                    SELECT SUM(
                        CASE WHEN t.transaction_type = 'DEBIT'
                        THEN CASE WHEN st.transaction_type = 'DEBIT' THEN ABS(st.amount) ELSE -ABS(st.amount) END
                        ELSE CASE WHEN st.transaction_type = 'CREDIT' THEN ABS(st.amount) ELSE -ABS(st.amount) END
                        END
                    )
                    FROM transaction st
                    WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL
                ), 0)) != 0
            )
            GROUP BY month
        ";

        $params = array_merge($categoryIds, $months);
        $results = $this->entityManager->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

        // Build map with defaults of 0
        $totals = array_fill_keys($months, 0);

        foreach ($results as $row) {
            $totals[$row['month']] = (int) $row['total'];
        }

        return $totals;
    }

    /**
     * Get monthly external payment totals
     */
    private function getMonthlyExternalTotals(Budget $project, array $months): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select("SUBSTRING(ep.paidOn, 1, 7) as month", 'SUM(ep.amountInCents) as total')
            ->from('App\Entity\ExternalPayment', 'ep')
            ->where('ep.budget = :project')
            ->andWhere("SUBSTRING(ep.paidOn, 1, 7) IN (:months)")
            ->groupBy('month')
            ->setParameter('project', $project)
            ->setParameter('months', $months);

        $results = $qb->getQuery()->getResult();

        // Build map with defaults of 0
        $totals = array_fill_keys($months, 0);

        foreach ($results as $row) {
            $totals[$row['month']] = (int) $row['total'];
        }

        return $totals;
    }
}
