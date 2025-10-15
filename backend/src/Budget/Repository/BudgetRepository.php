<?php

namespace App\Budget\Repository;

use App\Entity\Budget;
use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BudgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Budget::class);
    }

    public function save(Budget $budget): Budget
    {
        $this->getEntityManager()->beginTransaction();

        try {
            $this->getEntityManager()->persist($budget);
            $this->getEntityManager()->flush();
            $this->getEntityManager()->commit();

            return $budget;

        } catch (\Exception $e) {
            $this->getEntityManager()->rollback();
            throw $e;
        }
    }

    public function delete(Budget $budget): void
    {
        $this->getEntityManager()->beginTransaction();
        try {
            $this->getEntityManager()->remove($budget);
            $this->getEntityManager()->flush();
            $this->getEntityManager()->commit();
        } catch (\Exception $e) {
            $this->getEntityManager()->rollback();
            throw $e;
        }
    }

    public function findByAccount(Account $account): array
    {
        return $this->findBy(['account' => $account], ['createdAt' => 'DESC']);
    }

    public function findByIdAndAccount(int $id, Account $account): ?Budget
    {
        return $this->createQueryBuilder('b')
            ->where('b.id = :id')
            ->andWhere('b.account = :account')
            ->setParameter('id', $id)
            ->setParameter('account', $account)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findWithVersionsAndCategories(int $id, Account $account): ?Budget
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.budgetVersions', 'bv')
            ->leftJoin('b.categories', 'c')
            ->addSelect('bv', 'c')
            ->where('b.id = :id')
            ->andWhere('b.account = :account')
            ->setParameter('id', $id)
            ->setParameter('account', $account)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findBudgetsWithActiveVersionForMonth(Account $account, string $monthYear): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.budgetVersions', 'bv')
            ->addSelect('bv')
            ->where('b.account = :account')
            ->andWhere('bv.effectiveFromMonth <= :monthYear')
            ->andWhere('bv.effectiveUntilMonth IS NULL OR bv.effectiveUntilMonth >= :monthYear')
            ->setParameter('account', $account)
            ->setParameter('monthYear', $monthYear)
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Haalt alle budgetten met hun categorieën en versies op voor een specifieke maand.
     * Inclusief eager loading voor betere performance.
     */
    public function findBudgetsWithCategoriesForMonth(Account $account, string $monthYear): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.budgetVersions', 'bv')
            ->leftJoin('b.categories', 'c')
            ->addSelect('bv', 'c')
            ->where('b.account = :account')
            ->andWhere('bv.effectiveFromMonth <= :monthYear')
            ->andWhere('bv.effectiveUntilMonth IS NULL OR bv.effectiveUntilMonth >= :monthYear')
            ->setParameter('account', $account)
            ->setParameter('monthYear', $monthYear)
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}