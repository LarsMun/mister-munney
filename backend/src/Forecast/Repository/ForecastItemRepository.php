<?php

namespace App\Forecast\Repository;

use App\Entity\ForecastItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForecastItem>
 */
class ForecastItemRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $em;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $em)
    {
        parent::__construct($registry, ForecastItem::class);
        $this->em = $em;
    }

    /**
     * Haal alle forecast items op voor een account, gesorteerd op positie
     *
     * @return ForecastItem[]
     */
    public function findByAccount(int $accountId): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.account = :accountId')
            ->setParameter('accountId', $accountId)
            ->orderBy('f.type', 'ASC') // INCOME eerst, dan EXPENSE
            ->addOrderBy('f.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Haal forecast items op per type
     *
     * @return ForecastItem[]
     */
    public function findByAccountAndType(int $accountId, string $type): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.account = :accountId')
            ->andWhere('f.type = :type')
            ->setParameter('accountId', $accountId)
            ->setParameter('type', $type)
            ->orderBy('f.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check of een budget al in de forecast zit
     */
    public function existsByBudget(int $accountId, int $budgetId): bool
    {
        $count = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.account = :accountId')
            ->andWhere('f.budget = :budgetId')
            ->setParameter('accountId', $accountId)
            ->setParameter('budgetId', $budgetId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Check of een categorie al in de forecast zit
     */
    public function existsByCategory(int $accountId, int $categoryId): bool
    {
        $count = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.account = :accountId')
            ->andWhere('f.category = :categoryId')
            ->setParameter('accountId', $accountId)
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Haal de hoogste positie op voor een type
     */
    public function getMaxPosition(int $accountId, string $type): int
    {
        $result = $this->createQueryBuilder('f')
            ->select('MAX(f.position)')
            ->where('f.account = :accountId')
            ->andWhere('f.type = :type')
            ->setParameter('accountId', $accountId)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    public function save(ForecastItem $item, bool $flush = true): void
    {
        $this->em->persist($item);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(ForecastItem $item, bool $flush = true): void
    {
        $this->em->remove($item);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->em;
    }
}
