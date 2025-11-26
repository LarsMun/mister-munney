<?php

namespace App\Transaction\Repository;

use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Money\MoneyFactory;
use App\Transaction\DTO\TransactionFilterDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{

    private EntityManagerInterface $entityManager;
    private MoneyFactory $moneyFactory;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, MoneyFactory $moneyFactory)
    {
        parent::__construct($registry, Transaction::class);
        $this->entityManager = $entityManager;
        $this->moneyFactory = $moneyFactory;
    }

    public function findByFilter(TransactionFilterDTO $filter): array
    {
        $qb = $this->createQueryBuilder('t');
        $this->applyFilter($qb, $filter);

        if ($filter->sortBy) {
            $qb->orderBy('t.' . $filter->sortBy, $filter->sortDirection ?? 'ASC');
        }

        return $qb->getQuery()->getResult();
    }

    public function summaryByFilter(TransactionFilterDTO $filter): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select(
                'COUNT(t.id) AS total',
                'SUM(CASE WHEN t.transaction_type = \'DEBIT\' THEN t.amountInCents ELSE 0 END) AS total_debit',
                'SUM(CASE WHEN t.transaction_type = \'CREDIT\' THEN t.amountInCents ELSE 0 END) AS total_credit',
                'SUM(CASE WHEN t.transaction_type = \'CREDIT\' THEN t.amountInCents ELSE 0 END) - 
             SUM(CASE WHEN t.transaction_type = \'DEBIT\' THEN t.amountInCents ELSE 0 END) AS net_total'
            );

        $this->applyFilter($qb, $filter);

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'total_debit' => $this->moneyFactory->toFloat(
                $this->moneyFactory->fromCents((int) ($result['total_debit'] ?? 0))
            ),
            'total_credit' => $this->moneyFactory->toFloat(
                $this->moneyFactory->fromCents((int) ($result['total_credit'] ?? 0))
            ),
            'net_total' => $this->moneyFactory->toFloat(
                $this->moneyFactory->fromCents((int) ($result['net_total'] ?? 0))
            ),
        ];
    }

    private function applyFilter(QueryBuilder $qb, TransactionFilterDTO $filter): void {
        if ($filter->accountId !== null) {
            $qb->join('t.account', 'a')
                ->andWhere('a.id = :accountId')
                ->setParameter('accountId', $filter->accountId);
        }

        // Optionally exclude parent transactions that have splits (to avoid double counting in budgets)
        // For transaction lists: keep parents visible
        // For budget calculations: exclude parents, count only the splits
        if ($filter->excludeSplitParents) {
            $qb->andWhere('(SELECT COUNT(st.id) FROM App\Entity\Transaction st WHERE st.parentTransaction = t) = 0');
        }

        if ($filter->search) {
            $words = preg_split('/\s+/', trim($filter->search));
            $i = 0;
            foreach ($words as $word) {
                $param = 'search_' . $i++;
                $expr = $qb->expr()->orX(
                    "t.description LIKE :$param",
                    "t.counterparty_account LIKE :$param",
                    "t.notes LIKE :$param"
                );
                $qb->andWhere($expr)
                    ->setParameter($param, '%' . $word . '%');
            }
        }

        if ($filter->transactionType) {
            $qb->andWhere('t.transaction_type = :type')
                ->setParameter('type', strtoupper($filter->transactionType));
        }

        if ($filter->startDate) {
            $startDate = new \DateTimeImmutable($filter->startDate);
            $qb->andWhere('t.date >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($filter->endDate) {
            $endDate = new \DateTimeImmutable($filter->endDate);
            $qb->andWhere('t.date <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        if ($filter->minAmount !== null) {
            $qb->andWhere('t.amountInCents >= :minAmount')
                ->setParameter('minAmount', $filter->minAmount);
        }

        if ($filter->maxAmount !== null) {
            $qb->andWhere('t.amountInCents <= :maxAmount')
                ->setParameter('maxAmount', $filter->maxAmount);
        }

        if (!empty($filter->sortBy)) {
            $direction = strtoupper($filter->sortDirection ?? 'DESC');
            if (!in_array($direction, ['ASC', 'DESC'])) {
                $direction = 'DESC';
            }
            $qb->orderBy('t.' . $filter->sortBy, $direction);
        } else {
            $qb->orderBy('t.date', 'DESC');
        }
    }

    public function findAvailableMonths(int $accountId): array
    {
        $result = $this->createQueryBuilder('t')
            ->select("SUBSTRING(t.date, 1, 7) AS month") // YYYY-MM formaat pakken
            ->where('t.account = :accountId')
            ->setParameter('accountId', $accountId)
            ->groupBy('month')
            ->orderBy('month', 'DESC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'month');
    }

    public function findHashesByDates(array $dates): array
    {
        $query = $this->createQueryBuilder('t')
            ->select('t.hash')
            ->where('t.date IN (:dates)')
            ->setParameter('dates', $dates)
            ->getQuery();

        return $query->getResult();
    }

    public function saveAll(array $transactions): void
    {
        foreach ($transactions as $transaction) {
            $this->entityManager->persist($transaction);
        }

        $this->entityManager->flush();
    }

    public function save(Transaction $transaction): void
    {
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();
    }

    public function delete(Transaction $transaction): void
    {
        $this->entityManager->remove($transaction);
        $this->entityManager->flush();
    }

    public function findByAccountAndDateRange(int $accountId, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.account = :accountId')
            ->andWhere('t.category IS NULL')
            ->setParameter('accountId', $accountId);

        if ($startDate !== null) {
            $qb->andWhere('t.date >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('t.date <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $qb
            ->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function bulkAssignCategory(array $transactionIds, int $categoryId): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.category', ':categoryId')
            ->where('t.id IN (:transactionIds)')
            ->setParameter('categoryId', $categoryId)
            ->setParameter('transactionIds', $transactionIds)
            ->getQuery()
            ->execute();
    }

    public function bulkRemoveCategory(array $transactionIds): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.category', 'NULL')
            ->where('t.id IN (:transactionIds)')
            ->setParameter('transactionIds', $transactionIds)
            ->getQuery()
            ->execute();
    }

    public function getTotalSpentByCategoriesInPeriod(array $categoryIds, \DateTime $startDate, \DateTime $endDate): \Money\Money
    {
        if (empty($categoryIds)) {
            return \Money\Money::EUR(0);
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        $sql = "
            SELECT
                SUM(
                    CASE
                        WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
                        THEN ABS(t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0))
                        ELSE ABS(t.amount)
                    END
                ) as total
            FROM transaction t
            WHERE t.category_id IN ($placeholders)
              AND t.date BETWEEN ? AND ?
              AND t.transaction_type = ?
              AND (
                  (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                  OR
                  (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
              )
        ";

        $params = array_merge(
            $categoryIds,
            [$startDate->format('Y-m-d'), $endDate->format('Y-m-d'), 'DEBIT']
        );

        $result = $this->getEntityManager()->getConnection()->executeQuery($sql, $params)->fetchOne();

        return \Money\Money::EUR((int) ($result ?? 0));
    }

    public function countByCategoryIds(array $categoryIds): int
    {
        if (empty($categoryIds)) {
            return 0;
        }

        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.category IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Haalt de totale DEBIT uitgaven per maand op voor een account.
     * Sluit de eerste en huidige (incomplete) maand uit.
     *
     * @param int $accountId
     * @param int|null $monthLimit Aantal maanden terug te gaan (null = alle maanden)
     * @return array Array met ['month' => 'YYYY-MM', 'total' => int (cents)]
     */
    public function getMonthlyDebitTotals(int $accountId, ?int $monthLimit = null): array
    {
        // Haal eerst de eerste en laatste maand op
        $firstMonth = $this->getFirstTransactionMonth($accountId);
        $currentMonth = (new \DateTime())->format('Y-m');

        $qb = $this->createQueryBuilder('t')
            ->select(
                "SUBSTRING(t.date, 1, 7) AS month",
                "SUM(t.amountInCents) AS total"
            )
            ->where('t.account = :accountId')
            ->setParameter('accountId', $accountId);

        // Sluit eerste en huidige maand uit
        if ($firstMonth) {
            $qb->andWhere('SUBSTRING(t.date, 1, 7) > :firstMonth')
                ->setParameter('firstMonth', $firstMonth);
        }

        $qb->andWhere('SUBSTRING(t.date, 1, 7) < :currentMonth')
            ->setParameter('currentMonth', $currentMonth);

        $qb->groupBy('month')
            ->orderBy('month', 'DESC');

        if ($monthLimit !== null && $monthLimit > 0) {
            $qb->setMaxResults($monthLimit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Haalt de maandelijkse uitgaven per categorie op.
     * Inclusief alle maanden (eerste en huidige maand worden meegenomen).
     *
     * @param int $accountId
     * @param int $categoryId (0 = niet ingedeeld)
     * @param int|null $monthLimit
     * @return array Array met ['month' => 'YYYY-MM', 'total' => int (cents)]
     */
    public function getMonthlyTotalsByCategory(int $accountId, int $categoryId, ?int $monthLimit = null): array
    {
        $sql = "
            SELECT
                SUBSTRING(t.date, 1, 7) AS month,
                SUM(CASE WHEN t.transaction_type = 'CREDIT' THEN t.amount ELSE -t.amount END) AS total
            FROM transaction t
            WHERE t.account_id = ?
        ";

        $params = [$accountId];

        // Filter op categorie
        if ($categoryId === 0) {
            $sql .= " AND t.category_id IS NULL";
        } else {
            $sql .= " AND t.category_id = ?";
            $params[] = $categoryId;
        }

        // Exclude split parents only if adjusted amount is zero
        $sql .= "
            AND (
                (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                OR
                (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
            )
        ";

        // Als monthLimit is ingesteld, filter op datum
        if ($monthLimit !== null && $monthLimit > 0) {
            $startDate = new \DateTime();
            $startDate->modify("-{$monthLimit} months");
            $startDate->modify('first day of this month');
            $startDate->setTime(0, 0, 0);

            $sql .= " AND t.date >= ?";
            $params[] = $startDate->format('Y-m-d');
        }

        $sql .= "
            GROUP BY month
            ORDER BY month DESC
        ";

        return $this->getEntityManager()->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();
    }

    /**
     * Haalt statistieken per categorie op voor een account.
     * Inclusief alle maanden (eerste en huidige maand worden meegenomen).
     *
     * @param int $accountId
     * @param int|null $monthLimit Aantal maanden terug te gaan (null = alle maanden)
     * @return array Array met statistieken per categorie
     */
    public function getCategoryStatistics(int $accountId, ?int $monthLimit = null): array
    {
        $sql = "
            SELECT
                COALESCE(c.id, 0) AS categoryId,
                COALESCE(c.name, 'Niet ingedeeld') AS categoryName,
                COALESCE(c.color, '#CCCCCC') AS categoryColor,
                COALESCE(c.icon, 'help-circle') AS categoryIcon,
                COUNT(t.id) AS transactionCount,
                SUM(
                    CASE
                        WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
                        THEN
                            CASE WHEN t.transaction_type = 'CREDIT'
                            THEN (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0))
                            ELSE -(t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0))
                            END
                        ELSE
                            CASE WHEN t.transaction_type = 'CREDIT' THEN t.amount ELSE -t.amount END
                    END
                ) AS totalAmount,
                AVG(CASE WHEN t.transaction_type = 'CREDIT' THEN t.amount ELSE -t.amount END) AS averagePerTransaction
            FROM transaction t
            LEFT JOIN category c ON t.category_id = c.id
            WHERE t.account_id = ?
              AND (
                  (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                  OR
                  (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
              )
        ";

        $params = [$accountId];

        // Als monthLimit is ingesteld, filter op datum
        if ($monthLimit !== null && $monthLimit > 0) {
            $startDate = new \DateTime();
            $startDate->modify("-{$monthLimit} months");
            $startDate->modify('first day of this month');
            $startDate->setTime(0, 0, 0);

            $sql .= " AND t.date >= ?";
            $params[] = $startDate->format('Y-m-d');
        }

        $sql .= "
            GROUP BY c.id, c.name, c.color, c.icon
            ORDER BY totalAmount ASC
        ";

        return $this->getEntityManager()->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();
    }

    /**
     * Haalt het totale aantal maanden met transacties op voor een account.
     * Sluit de eerste en huidige (incomplete) maand uit.
     *
     * @param int $accountId
     * @param int|null $monthLimit
     * @return int
     */
    public function getMonthCountForPeriod(int $accountId, ?int $monthLimit = null): int
    {
        // Haal eerst de eerste en laatste maand op
        $firstMonth = $this->getFirstTransactionMonth($accountId);
        $currentMonth = (new \DateTime())->format('Y-m');

        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(DISTINCT SUBSTRING(t.date, 1, 7)) AS monthCount')
            ->where('t.account = :accountId')
            ->andWhere('t.transaction_type = :debitType')
            ->setParameter('accountId', $accountId)
            ->setParameter('debitType', \App\Enum\TransactionType::DEBIT);

        // Sluit eerste en huidige maand uit
        if ($firstMonth) {
            $qb->andWhere('SUBSTRING(t.date, 1, 7) > :firstMonth')
                ->setParameter('firstMonth', $firstMonth);
        }

        $qb->andWhere('SUBSTRING(t.date, 1, 7) < :currentMonth')
            ->setParameter('currentMonth', $currentMonth);

        if ($monthLimit !== null && $monthLimit > 0) {
            $startDate = new \DateTime();
            $startDate->modify("-{$monthLimit} months");
            $startDate->modify('first day of this month');
            $startDate->setTime(0, 0, 0);

            $qb->andWhere('t.date >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return (int) $result;
    }

    /**
     * Haalt de eerste maand met transacties op (YYYY-MM formaat).
     *
     * @param int $accountId
     * @return string|null
     */
    private function getFirstTransactionMonth(int $accountId): ?string
    {
        $result = $this->createQueryBuilder('t')
            ->select('SUBSTRING(t.date, 1, 7) AS month')
            ->where('t.account = :accountId')
            ->setParameter('accountId', $accountId)
            ->orderBy('t.date', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['month'] : null;
    }

    /**
     * Haalt recent statistics op voor een categorie met trend informatie.
     * Berekent mediaan voor laatste 12 maanden en vergelijkt met overall mediaan.
     *
     * @param int $accountId
     * @param int $categoryId
     * @return array ['medianLast12Months' => int, 'medianAll' => int, 'trend' => string, 'trendPercentage' => float]
     */
    public function getCategoryRecentStatistics(int $accountId, int $categoryId): array
    {
        // Haal alle maandelijkse totalen op (ongelimiteerd)
        $allMonthlyTotals = $this->getMonthlyTotalsByCategory($accountId, $categoryId, null);

        // Haal laatste 12 maanden op
        $last12MonthsTotals = $this->getMonthlyTotalsByCategory($accountId, $categoryId, 12);

        // Bereken mediaan voor beide periodes
        $medianAll = $this->calculateMedian($allMonthlyTotals);
        $medianLast12 = $this->calculateMedian($last12MonthsTotals);

        // Bepaal trend
        $trend = 'stable';
        $trendPercentage = 0.0;

        if ($medianAll > 0 && $medianLast12 > 0) {
            $difference = $medianLast12 - $medianAll;
            $trendPercentage = round(($difference / $medianAll) * 100, 1);

            // Trend is significant als verschil > 10%
            if ($trendPercentage > 10) {
                $trend = 'increasing';
            } elseif ($trendPercentage < -10) {
                $trend = 'decreasing';
            }
        }

        return [
            'medianLast12Months' => (int) $medianLast12,
            'medianAll' => (int) $medianAll,
            'trend' => $trend,
            'trendPercentage' => $trendPercentage,
            'monthsLast12' => count($last12MonthsTotals),
            'monthsAll' => count($allMonthlyTotals),
        ];
    }

    /**
     * Berekent de mediaan van maandelijkse totalen.
     *
     * @param array $monthlyTotals Array met ['month' => string, 'total' => int]
     * @return int Mediaan in centen
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

    /**
     * Haalt de totale netto uitgaven op voor categorieën binnen een specifieke maand.
     * DEBIT transacties tellen als uitgaven (positief), CREDIT transacties als compensatie (negatief).
     *
     * @param array $categoryIds Array met categorie IDs
     * @param string $monthYear Maand in YYYY-MM formaat
     * @return int Totaal in centen
     */
    public function getTotalSpentByCategoriesInMonth(array $categoryIds, string $monthYear): int
    {
        if (empty($categoryIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        $sql = "
            SELECT
                SUM(
                    CASE
                        -- If parent with splits: use adjusted amount (parent - categorized children)
                        WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
                        THEN
                            CASE WHEN t.transaction_type = 'CREDIT'
                            THEN -(t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0))
                            ELSE (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0))
                            END
                        -- Regular transaction or child: use full amount
                        ELSE
                            CASE WHEN t.transaction_type = 'CREDIT' THEN -t.amount ELSE t.amount END
                    END
                ) as total
            FROM transaction t
            WHERE t.category_id IN ($placeholders)
              AND SUBSTRING(t.date, 1, 7) = ?
              AND (
                  (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                  OR
                  (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
              )
        ";

        $params = array_merge($categoryIds, [$monthYear]);

        $result = $this->getEntityManager()->getConnection()->executeQuery($sql, $params)->fetchOne();

        return (int) ($result ?? 0);
    }

    /**
     * Haalt breakdown van netto uitgaven per categorie op voor een specifieke maand.
     * DEBIT transacties tellen als uitgaven (positief), CREDIT transacties als compensatie (negatief).
     *
     * @param array $categoryIds Array met categorie IDs
     * @param string $monthYear Maand in YYYY-MM formaat
     * @return array Array met ['categoryId' => int, 'totalAmount' => int (cents), 'transactionCount' => int]
     */
    public function getCategoryBreakdownForMonth(array $categoryIds, string $monthYear): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        $sql = "
            SELECT
                t.category_id AS categoryId,
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
                ) AS totalAmount,
                COUNT(t.id) AS transactionCount
            FROM transaction t
            WHERE t.category_id IN ($placeholders)
              AND SUBSTRING(t.date, 1, 7) = ?
              AND (
                  (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                  OR
                  (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
              )
            GROUP BY t.category_id
        ";

        $params = array_merge($categoryIds, [$monthYear]);

        $results = $this->getEntityManager()->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

        return array_map(function ($row) {
            return [
                'categoryId' => (int) $row['categoryId'],
                'totalAmount' => (int) $row['totalAmount'],
                'transactionCount' => (int) $row['transactionCount']
            ];
        }, $results);
    }

    /**
     * Haalt category breakdown op voor een datumbereik.
     * DEBIT transacties tellen als uitgaven (positief), CREDIT transacties als compensatie (negatief).
     *
     * @param array $categoryIds Array met categorie IDs
     * @param string $startDate Start datum in YYYY-MM-DD formaat
     * @param string $endDate Eind datum in YYYY-MM-DD formaat
     * @return array Array met ['categoryId' => int, 'totalAmount' => int (cents), 'transactionCount' => int]
     */
    public function getCategoryBreakdownForDateRange(array $categoryIds, string $startDate, string $endDate): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        $sql = "
            SELECT
                t.category_id AS categoryId,
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
                ) AS totalAmount,
                COUNT(t.id) AS transactionCount
            FROM transaction t
            WHERE t.category_id IN ($placeholders)
              AND t.date >= ?
              AND t.date <= ?
              AND (
                  (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                  OR
                  (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
              )
            GROUP BY t.category_id
        ";

        $params = array_merge($categoryIds, [$startDate, $endDate]);

        $results = $this->getEntityManager()->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

        return array_map(function ($row) {
            return [
                'categoryId' => (int) $row['categoryId'],
                'totalAmount' => (int) $row['totalAmount'],
                'transactionCount' => (int) $row['transactionCount']
            ];
        }, $results);
    }

    /**
     * Haalt historische maandelijkse netto uitgaven op voor categorieën.
     * Sluit de eerste en huidige (incomplete) maand uit.
     * DEBIT transacties tellen als uitgaven (positief), CREDIT transacties als compensatie (negatief).
     *
     * @param int $accountId
     * @param array $categoryIds Array met categorie IDs
     * @param int|null $monthLimit Aantal maanden terug (null = alle maanden)
     * @return array Array met ['month' => 'YYYY-MM', 'total' => int (cents)]
     */
    public function getMonthlySpentByCategories(int $accountId, array $categoryIds, ?int $monthLimit = null): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        // Haal eerst de eerste en laatste maand op
        $firstMonth = $this->getFirstTransactionMonth($accountId);
        $currentMonth = (new \DateTime())->format('Y-m');

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        $sql = "
            SELECT
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
            WHERE t.account_id = ?
              AND t.category_id IN ($placeholders)
              AND (
                  (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                  OR
                  (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
              )
        ";

        $params = [$accountId];
        $params = array_merge($params, $categoryIds);

        // Sluit eerste en huidige maand uit
        if ($firstMonth) {
            $sql .= " AND SUBSTRING(t.date, 1, 7) > ?";
            $params[] = $firstMonth;
        }

        $sql .= " AND SUBSTRING(t.date, 1, 7) < ?";
        $params[] = $currentMonth;

        $sql .= "
            GROUP BY month
            ORDER BY month DESC
        ";

        if ($monthLimit !== null && $monthLimit > 0) {
            $sql .= " LIMIT ?";
            $params[] = $monthLimit;
        }

        return $this->getEntityManager()->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();
    }

    /**
     * Haalt het huidige maand bedrag op voor een specifieke categorie.
     * Inclusief alle transacties van deze maand tot nu toe.
     *
     * @param int $accountId
     * @param int $categoryId (0 = niet ingedeeld)
     * @return int Totaal in centen (netto: CREDIT - DEBIT)
     */
    public function getCurrentMonthTotalByCategory(int $accountId, int $categoryId): int
    {
        $currentMonth = (new \DateTime())->format('Y-m');

        $sql = "
            SELECT
                SUM(
                    CASE
                        WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
                        THEN
                            CASE WHEN t.transaction_type = 'CREDIT'
                            THEN (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0))
                            ELSE -(t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0))
                            END
                        ELSE
                            CASE WHEN t.transaction_type = 'CREDIT' THEN t.amount ELSE -t.amount END
                    END
                ) AS total
            FROM transaction t
            WHERE t.account_id = ?
              AND SUBSTRING(t.date, 1, 7) = ?
              AND (
                  (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                  OR
                  (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
              )
        ";

        $params = [$accountId, $currentMonth];

        // Filter op categorie
        if ($categoryId === 0) {
            $sql .= " AND t.category_id IS NULL";
        } else {
            $sql .= " AND t.category_id = ?";
            $params[] = $categoryId;
        }

        $result = $this->getEntityManager()->getConnection()->executeQuery($sql, $params)->fetchOne();

        return (int) ($result ?? 0);
    }

    /**
     * Find uncategorized transactions for AI suggestions
     *
     * @param int $accountId
     * @param int $limit
     * @return Transaction[]
     */
    public function findUncategorizedTransactions(int $accountId, int $limit = 50): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.account = :accountId')
            ->andWhere('t.category IS NULL')
            ->setParameter('accountId', $accountId)
            ->orderBy('t.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all uncategorized transactions for pattern discovery
     *
     * @param int $accountId
     * @return Transaction[]
     */
    public function findUncategorizedByAccount(int $accountId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.account = :accountId')
            ->andWhere('t.category IS NULL')
            ->setParameter('accountId', $accountId)
            ->orderBy('t.date', 'DESC')
            ->setMaxResults(1000) // Limit for AI processing
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total amount and count of uncategorized transactions for an account
     * @param int $accountId
     * @param string|null $monthYear Optional YYYY-MM format to filter by month
     * @return array ['total_amount' => int (cents), 'count' => int]
     */
    public function getUncategorizedStats(int $accountId, ?string $monthYear = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('SUM(t.amountInCents) as total_amount, COUNT(t.id) as count')
            ->where('t.account = :accountId')
            ->andWhere('t.category IS NULL')
            ->setParameter('accountId', $accountId);

        if ($monthYear) {
            // Parse monthYear to get start and end dates (database-agnostic)
            $startDate = $monthYear . '-01';
            $endDate = date('Y-m-t', strtotime($startDate)); // Last day of the month

            $qb->andWhere('t.date >= :startDate')
                ->andWhere('t.date <= :endDate')
                ->setParameter('startDate', new \DateTime($startDate))
                ->setParameter('endDate', new \DateTime($endDate));
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total_amount' => (int) ($result['total_amount'] ?? 0),
            'count' => (int) ($result['count'] ?? 0)
        ];
    }

    /**
     * Get monthly totals for multiple categories combined (for budget history)
     *
     * @param int $accountId
     * @param array $categoryIds Array of category IDs
     * @param int|null $monthLimit Optional month limit
     * @return array Array with month, total, and transactionCount
     */
    public function getMonthlyTotalsByCategories(int $accountId, array $categoryIds, ?int $monthLimit = null): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        $sql = "
            SELECT
                SUBSTRING(t.date, 1, 7) AS month,
                SUM(CASE WHEN t.transaction_type = 'CREDIT' THEN t.amount ELSE -t.amount END) AS total,
                COUNT(t.id) AS transactionCount
            FROM transaction t
            WHERE t.account_id = ?
            AND t.category_id IN ($placeholders)
        ";

        $params = [$accountId, ...$categoryIds];

        // Exclude split parents only if adjusted amount is zero
        $sql .= "
            AND (
                (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
                OR
                (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
            )
        ";

        // Als monthLimit is ingesteld, filter op datum
        if ($monthLimit !== null && $monthLimit > 0) {
            $cutoffDate = (new \DateTime())->modify("-{$monthLimit} months")->format('Y-m-01');
            $sql .= " AND t.date >= ?";
            $params[] = $cutoffDate;
        }

        $sql .= "
            GROUP BY month
            ORDER BY month DESC
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery($params);

        return $result->fetchAllAssociative();
    }
}
