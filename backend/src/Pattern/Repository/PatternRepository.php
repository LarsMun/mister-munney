<?php

namespace App\Pattern\Repository;

use App\Entity\Pattern;
use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class PatternRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $em;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $em)
    {
        parent::__construct($registry, Pattern::class);
        $this->em = $em;
    }

    public function findByHash(string $hash): ?Pattern
    {
        return $this->findOneBy(['uniqueHash' => $hash]);
    }

    public function findByAccountId(int $accountId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.account', 'a')
            ->andWhere('a.id = :accountId')
            ->setParameter('accountId', $accountId)
            ->getQuery()
            ->getResult();
    }

    public function findByCategoryId(int $categoryId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.category', 'c')
            ->andWhere('c.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getResult();
    }

    public function findBySavingsAccountId(int $savingsAccountId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.savingsAccount', 's')
            ->andWhere('s.id = :savingsAccountId')
            ->setParameter('savingsAccountId', $savingsAccountId)
            ->getQuery()
            ->getResult();
    }

    public function save(Pattern $pattern, bool $flush = true): void
    {
        $this->em->persist($pattern);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(Pattern $pattern, bool $flush = true): void
    {
        $this->em->remove($pattern);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function persist(Transaction $transaction): void
    {
        $this->em->persist($transaction);
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function findWithoutCategory(int $accountId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.account', 'a')
            ->andWhere('a.id = :accountId')
            ->andWhere('p.category IS NULL')
            ->setParameter('accountId', $accountId)
            ->getQuery()
            ->getResult();
    }
}