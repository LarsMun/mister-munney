<?php

namespace App\Security\Repository;

use App\Security\Entity\LoginAttempt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LoginAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginAttempt::class);
    }

    /**
     * Count failed login attempts for an email within a time window
     */
    public function countFailedAttempts(string $email, \DateTime $since): int
    {
        return (int) $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->where('la.email = :email')
            ->andWhere('la.success = false')
            ->andWhere('la.attemptedAt >= :since')
            ->setParameter('email', $email)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get all login attempts for an email within a time window
     */
    public function getAttempts(string $email, \DateTime $since): array
    {
        return $this->createQueryBuilder('la')
            ->where('la.email = :email')
            ->andWhere('la.attemptedAt >= :since')
            ->setParameter('email', $email)
            ->setParameter('since', $since)
            ->orderBy('la.attemptedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete old login attempts (cleanup)
     */
    public function deleteOlderThan(\DateTime $date): int
    {
        return $this->createQueryBuilder('la')
            ->delete()
            ->where('la.attemptedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete failed login attempts for a specific email
     */
    public function deleteFailedAttempts(string $email): int
    {
        return $this->createQueryBuilder('la')
            ->delete()
            ->where('la.email = :email')
            ->andWhere('la.success = false')
            ->setParameter('email', $email)
            ->getQuery()
            ->execute();
    }
}
