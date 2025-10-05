<?php

namespace App\Category\Repository;

use App\Entity\Account;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository voor Category-entiteit.
 *
 * Bevat methodes voor het ophalen, opslaan en verwijderen van categorieën.
 *
 * @extends ServiceEntityRepository<Category>
 */

class CategoryRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManagerInterface;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManagerInterface)
    {
        parent::__construct($registry, Category::class);
        $this->entityManagerInterface = $entityManagerInterface;
    }

    /**
     * Haalt een categorie op aan de hand van het ID.
     *
     * @param int $id ID van de categorie
     * @return Category|null De categorie of null als deze niet bestaat
     */
    public function getById(int $id): ?Category
    {
        return $this->find($id);
    }

    /**
     * Haalt alle categorieën op.
     *
     * @return Category[] Array van alle categorieën
     */
    public function getAll(): array
    {
        return $this->findAll();
    }

    /**
     * Slaat een categorie op in de database.
     *
     * @param Category $category De op te slaan categorie
     */
    public function save(Category $category): void
    {
        $this->entityManagerInterface->persist($category);
        $this->entityManagerInterface->flush();
    }

    /**
     * Verwijdert een categorie uit de database.
     *
     * @param Category $category De te verwijderen categorie
     */
    public function remove(Category $category): void
    {
        $this->entityManagerInterface->remove($category);
        $this->entityManagerInterface->flush();
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find categories by account
     */
    public function findByAccount(Account $account): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.account = :account')
            ->setParameter('account', $account)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
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
        $amounts = array_map(fn($row) => abs((int) $row['total']), $monthlyTotals);
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
}
