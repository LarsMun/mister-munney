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
        $tracked = $this->getTrackedTotal($project);
        $external = $this->getExternalTotal($project);
        $total = $tracked->add($external);

        return [
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
     * Get total from tracked transactions (via categories)
     */
    private function getTrackedTotal(Budget $project): Money
    {
        $categories = $project->getCategories();

        if ($categories->isEmpty()) {
            return $this->moneyFactory->zero();
        }

        $categoryIds = array_map(fn($cat) => $cat->getId(), $categories->toArray());

        // CREDIT transactions are subtracted (refunds), DEBIT transactions are added (expenses)
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select("SUM(CASE WHEN t.transaction_type = 'credit' THEN -t.amountInCents ELSE t.amountInCents END) as total")
            ->from('App\Entity\Transaction', 't')
            ->where('t.category IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds);

        $result = $qb->getQuery()->getSingleScalarResult();

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

        // CREDIT transactions are subtracted (refunds), DEBIT transactions are added (expenses)
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('c.id', 'c.name', "SUM(CASE WHEN t.transaction_type = 'credit' THEN -t.amountInCents ELSE t.amountInCents END) as total")
            ->from('App\Entity\Transaction', 't')
            ->join('t.category', 'c')
            ->where('t.category IN (:categoryIds)')
            ->groupBy('c.id', 'c.name')
            ->setParameter('categoryIds', $categoryIds);

        $results = $qb->getQuery()->getResult();

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

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from('App\Entity\Transaction', 't')
            ->where('t.category IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds);

        return $qb->getQuery()->getResult();
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

        // CREDIT transactions are subtracted (refunds), DEBIT transactions are added (expenses)
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select("SUBSTRING(t.date, 1, 7) as month", "SUM(CASE WHEN t.transaction_type = 'credit' THEN -t.amountInCents ELSE t.amountInCents END) as total")
            ->from('App\Entity\Transaction', 't')
            ->where('t.category IN (:categoryIds)')
            ->andWhere("SUBSTRING(t.date, 1, 7) IN (:months)")
            ->groupBy('month')
            ->setParameter('categoryIds', $categoryIds)
            ->setParameter('months', $months);

        $results = $qb->getQuery()->getResult();

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
