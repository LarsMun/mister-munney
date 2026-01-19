<?php

namespace App\Security\Repository;

use App\Security\Entity\AuditLog;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Find audit logs for a specific user
     */
    public function findByUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find audit logs by action type
     */
    public function findByAction(string $action, \DateTime $since = null, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.action = :action')
            ->setParameter('action', $action)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('a.createdAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find audit logs for a specific entity
     */
    public function findByEntity(string $entityType, int $entityId, int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.entityType = :entityType')
            ->andWhere('a.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent security-related events
     */
    public function findSecurityEvents(\DateTime $since, int $limit = 100): array
    {
        $securityActions = [
            AuditLog::ACTION_LOGIN,
            AuditLog::ACTION_LOGOUT,
            AuditLog::ACTION_LOGIN_FAILED,
            AuditLog::ACTION_ACCOUNT_LOCKED,
            AuditLog::ACTION_ACCOUNT_UNLOCKED,
            AuditLog::ACTION_PASSWORD_CHANGED,
        ];

        return $this->createQueryBuilder('a')
            ->where('a.action IN (:actions)')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('actions', $securityActions)
            ->setParameter('since', $since)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count actions by type within a time period
     */
    public function countByAction(string $action, \DateTime $since): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.action = :action')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('action', $action)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Delete old audit logs (cleanup)
     */
    public function deleteOlderThan(\DateTime $date): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Get audit logs with pagination
     */
    public function findPaginated(int $page = 1, int $limit = 50, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if (isset($filters['action'])) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $filters['action']);
        }

        if (isset($filters['user'])) {
            $qb->andWhere('a.user = :user')
               ->setParameter('user', $filters['user']);
        }

        if (isset($filters['since'])) {
            $qb->andWhere('a.createdAt >= :since')
               ->setParameter('since', $filters['since']);
        }

        if (isset($filters['until'])) {
            $qb->andWhere('a.createdAt <= :until')
               ->setParameter('until', $filters['until']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get total count for pagination
     */
    public function countAll(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        if (isset($filters['action'])) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $filters['action']);
        }

        if (isset($filters['user'])) {
            $qb->andWhere('a.user = :user')
               ->setParameter('user', $filters['user']);
        }

        if (isset($filters['since'])) {
            $qb->andWhere('a.createdAt >= :since')
               ->setParameter('since', $filters['since']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
