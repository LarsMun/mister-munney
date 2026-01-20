<?php

namespace App\RecurringTransaction\Repository;

use App\Entity\Account;
use App\Entity\RecurringTransaction;
use App\Enum\RecurrenceFrequency;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecurringTransaction>
 */
class RecurringTransactionRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, RecurringTransaction::class);
        $this->entityManager = $entityManager;
    }

    public function save(RecurringTransaction $recurringTransaction): void
    {
        $this->entityManager->persist($recurringTransaction);
        $this->entityManager->flush();
    }

    public function saveAll(array $recurringTransactions): void
    {
        foreach ($recurringTransactions as $rt) {
            $this->entityManager->persist($rt);
        }
        $this->entityManager->flush();
    }

    public function remove(RecurringTransaction $recurringTransaction): void
    {
        $this->entityManager->remove($recurringTransaction);
        $this->entityManager->flush();
    }

    /**
     * @return RecurringTransaction[]
     */
    public function findByAccount(Account $account): array
    {
        return $this->createQueryBuilder('rt')
            ->leftJoin('rt.category', 'c')
            ->addSelect('c')
            ->where('rt.account = :account')
            ->setParameter('account', $account)
            ->orderBy('rt.nextExpected', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RecurringTransaction[]
     */
    public function findActiveByAccount(Account $account): array
    {
        return $this->createQueryBuilder('rt')
            ->leftJoin('rt.category', 'c')
            ->addSelect('c')
            ->where('rt.account = :account')
            ->andWhere('rt.isActive = :active')
            ->setParameter('account', $account)
            ->setParameter('active', true)
            ->orderBy('rt.nextExpected', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RecurringTransaction[]
     */
    public function findByAccountAndFrequency(Account $account, RecurrenceFrequency $frequency): array
    {
        return $this->createQueryBuilder('rt')
            ->leftJoin('rt.category', 'c')
            ->addSelect('c')
            ->where('rt.account = :account')
            ->andWhere('rt.frequency = :frequency')
            ->setParameter('account', $account)
            ->setParameter('frequency', $frequency)
            ->orderBy('rt.nextExpected', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming transactions within the next N days
     *
     * @return RecurringTransaction[]
     */
    public function findUpcoming(Account $account, int $days = 30): array
    {
        $now = new DateTimeImmutable();
        $endDate = $now->modify("+{$days} days");

        return $this->createQueryBuilder('rt')
            ->leftJoin('rt.category', 'c')
            ->addSelect('c')
            ->where('rt.account = :account')
            ->andWhere('rt.isActive = :active')
            ->andWhere('rt.nextExpected >= :now')
            ->andWhere('rt.nextExpected <= :endDate')
            ->setParameter('account', $account)
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->setParameter('endDate', $endDate)
            ->orderBy('rt.nextExpected', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find overdue transactions (expected date passed but not yet updated)
     *
     * @return RecurringTransaction[]
     */
    public function findOverdue(Account $account): array
    {
        $now = new DateTimeImmutable();

        return $this->createQueryBuilder('rt')
            ->leftJoin('rt.category', 'c')
            ->addSelect('c')
            ->where('rt.account = :account')
            ->andWhere('rt.isActive = :active')
            ->andWhere('rt.nextExpected < :now')
            ->setParameter('account', $account)
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->orderBy('rt.nextExpected', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find by merchant pattern to check for duplicates
     */
    public function findByMerchantPattern(Account $account, string $merchantPattern): ?RecurringTransaction
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.account = :account')
            ->andWhere('rt.merchantPattern = :pattern')
            ->setParameter('account', $account)
            ->setParameter('pattern', $merchantPattern)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get summary statistics for an account
     */
    public function getSummary(Account $account): array
    {
        $result = $this->createQueryBuilder('rt')
            ->select(
                'COUNT(rt.id) as total',
                'SUM(CASE WHEN rt.isActive = true THEN 1 ELSE 0 END) as active',
                'SUM(CASE WHEN rt.transactionType = \'debit\' AND rt.isActive = true THEN rt.predictedAmountInCents ELSE 0 END) as monthlyDebit',
                'SUM(CASE WHEN rt.transactionType = \'credit\' AND rt.isActive = true THEN rt.predictedAmountInCents ELSE 0 END) as monthlyCredit'
            )
            ->where('rt.account = :account')
            ->andWhere('rt.frequency = :monthly')
            ->setParameter('account', $account)
            ->setParameter('monthly', RecurrenceFrequency::MONTHLY)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) ($result['total'] ?? 0),
            'active' => (int) ($result['active'] ?? 0),
            'monthlyDebit' => (int) ($result['monthlyDebit'] ?? 0),
            'monthlyCredit' => (int) ($result['monthlyCredit'] ?? 0),
        ];
    }

    /**
     * Delete all recurring transactions for an account (for re-detection)
     */
    public function deleteAllForAccount(Account $account): int
    {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.account = :account')
            ->setParameter('account', $account)
            ->getQuery()
            ->execute();
    }
}
