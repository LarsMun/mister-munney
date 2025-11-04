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

    public function findByAccountAndDateRange(int $accountId, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.account = :accountId')
            ->andWhere('t.category IS NULL')
            ->andWhere('t.savingsAccount IS NULL')
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

        $result = $this->createQueryBuilder('t')
            ->select('SUM(ABS(t.amountInCents)) as total')
            ->where('t.category IN (:categoryIds)')
            ->andWhere('t.date BETWEEN :startDate AND :endDate')
            ->andWhere('t.transactionType = :debitType')
            ->setParameter('categoryIds', $categoryIds)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('debitType', \App\Enum\TransactionType::DEBIT)
            ->getQuery()
            ->getSingleScalarResult();

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
        $qb = $this->createQueryBuilder('t')
            ->select(
                "SUBSTRING(t.date, 1, 7) AS month",
                "SUM(CASE WHEN t.transaction_type = 'CREDIT' THEN t.amountInCents ELSE -t.amountInCents END) AS total"
            )
            ->where('t.account = :accountId')
            ->setParameter('accountId', $accountId);

        // Filter op categorie
        if ($categoryId === 0) {
            $qb->andWhere('t.category IS NULL');
        } else {
            $qb->andWhere('t.category = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        // Als monthLimit is ingesteld, filter op datum
        if ($monthLimit !== null && $monthLimit > 0) {
            $startDate = new \DateTime();
            $startDate->modify("-{$monthLimit} months");
            $startDate->modify('first day of this month');
            $startDate->setTime(0, 0, 0);

            $qb->andWhere('t.date >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        $qb->groupBy('month')
            ->orderBy('month', 'DESC');

        return $qb->getQuery()->getResult();
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
        $qb = $this->createQueryBuilder('t')
            ->select(
                'COALESCE(c.id, 0) AS categoryId',
                'COALESCE(c.name, \'Niet ingedeeld\') AS categoryName',
                'COALESCE(c.color, \'#CCCCCC\') AS categoryColor',
                'COALESCE(c.icon, \'help-circle\') AS categoryIcon',
                'COUNT(t.id) AS transactionCount',
                'SUM(CASE WHEN t.transaction_type = \'CREDIT\' THEN t.amountInCents ELSE -t.amountInCents END) AS totalAmount',
                'AVG(CASE WHEN t.transaction_type = \'CREDIT\' THEN t.amountInCents ELSE -t.amountInCents END) AS averagePerTransaction'
            )
            ->leftJoin('t.category', 'c')
            ->where('t.account = :accountId')
            ->setParameter('accountId', $accountId);

        // Als monthLimit is ingesteld, filter op datum
        if ($monthLimit !== null && $monthLimit > 0) {
            $startDate = new \DateTime();
            $startDate->modify("-{$monthLimit} months");
            $startDate->modify('first day of this month');
            $startDate->setTime(0, 0, 0);

            $qb->andWhere('t.date >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        $qb->groupBy('c.id, c.name, c.color, c.icon')
            ->orderBy('totalAmount', 'ASC');  // ASC want negatieve getallen (uitgaven) moeten bovenaan

        return $qb->getQuery()->getResult();
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

        $result = $this->createQueryBuilder('t')
            ->select('SUM(CASE WHEN t.transaction_type = \'CREDIT\' THEN -t.amountInCents ELSE t.amountInCents END) as total')
            ->where('t.category IN (:categoryIds)')
            ->andWhere('SUBSTRING(t.date, 1, 7) = :monthYear')
            ->setParameter('categoryIds', $categoryIds)
            ->setParameter('monthYear', $monthYear)
            ->getQuery()
            ->getSingleScalarResult();

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

        $results = $this->createQueryBuilder('t')
            ->select(
                'IDENTITY(t.category) AS categoryId',
                'SUM(CASE WHEN t.transaction_type = \'CREDIT\' THEN -t.amountInCents ELSE t.amountInCents END) AS totalAmount',
                'COUNT(t.id) AS transactionCount'
            )
            ->where('t.category IN (:categoryIds)')
            ->andWhere('SUBSTRING(t.date, 1, 7) = :monthYear')
            ->setParameter('categoryIds', $categoryIds)
            ->setParameter('monthYear', $monthYear)
            ->groupBy('t.category')
            ->getQuery()
            ->getResult();

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

        $results = $this->createQueryBuilder('t')
            ->select(
                'IDENTITY(t.category) AS categoryId',
                'SUM(CASE WHEN t.transaction_type = \'CREDIT\' THEN -t.amountInCents ELSE t.amountInCents END) AS totalAmount',
                'COUNT(t.id) AS transactionCount'
            )
            ->where('t.category IN (:categoryIds)')
            ->andWhere('t.date >= :startDate')
            ->andWhere('t.date <= :endDate')
            ->setParameter('categoryIds', $categoryIds)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('t.category')
            ->getQuery()
            ->getResult();

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

        $qb = $this->createQueryBuilder('t')
            ->select(
                "SUBSTRING(t.date, 1, 7) AS month",
                "SUM(CASE WHEN t.transaction_type = 'CREDIT' THEN -t.amountInCents ELSE t.amountInCents END) AS total"
            )
            ->where('t.account = :accountId')
            ->andWhere('t.category IN (:categoryIds)')
            ->setParameter('accountId', $accountId)
            ->setParameter('categoryIds', $categoryIds);

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

        $qb = $this->createQueryBuilder('t')
            ->select(
                "SUM(CASE WHEN t.transaction_type = 'CREDIT' THEN t.amountInCents ELSE -t.amountInCents END) AS total"
            )
            ->where('t.account = :accountId')
            ->andWhere('SUBSTRING(t.date, 1, 7) = :currentMonth')
            ->setParameter('accountId', $accountId)
            ->setParameter('currentMonth', $currentMonth);

        // Filter op categorie
        if ($categoryId === 0) {
            $qb->andWhere('t.category IS NULL');
        } else {
            $qb->andWhere('t.category = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

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
}
