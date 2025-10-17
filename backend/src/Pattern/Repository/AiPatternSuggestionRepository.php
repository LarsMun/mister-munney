<?php

namespace App\Pattern\Repository;

use App\Entity\AiPatternSuggestion;
use App\Enum\AiPatternSuggestionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AiPatternSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiPatternSuggestion::class);
    }

    /**
     * Check if a processed (accepted/rejected) suggestion with this pattern hash exists
     * Pending suggestions are NOT considered as existing, so they can be shown again
     */
    public function existsProcessedByPatternHash(int $accountId, string $patternHash): bool
    {
        $count = $this->createQueryBuilder('aps')
            ->select('COUNT(aps.id)')
            ->where('aps.account = :accountId')
            ->andWhere('aps.patternHash = :patternHash')
            ->andWhere('aps.status != :pendingStatus')
            ->setParameter('accountId', $accountId)
            ->setParameter('patternHash', $patternHash)
            ->setParameter('pendingStatus', AiPatternSuggestionStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Check if a pending suggestion with this pattern hash exists
     */
    public function existsPendingByPatternHash(int $accountId, string $patternHash): bool
    {
        $count = $this->createQueryBuilder('aps')
            ->select('COUNT(aps.id)')
            ->where('aps.account = :accountId')
            ->andWhere('aps.patternHash = :patternHash')
            ->andWhere('aps.status = :pendingStatus')
            ->setParameter('accountId', $accountId)
            ->setParameter('patternHash', $patternHash)
            ->setParameter('pendingStatus', AiPatternSuggestionStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Find all pending suggestions for an account
     */
    public function findPendingByAccount(int $accountId): array
    {
        return $this->createQueryBuilder('aps')
            ->where('aps.account = :accountId')
            ->andWhere('aps.status = :status')
            ->setParameter('accountId', $accountId)
            ->setParameter('status', AiPatternSuggestionStatus::PENDING)
            ->orderBy('aps.confidence', 'DESC')
            ->addOrderBy('aps.matchCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find suggestion by pattern hash and account
     */
    public function findByPatternHash(int $accountId, string $patternHash): ?AiPatternSuggestion
    {
        return $this->createQueryBuilder('aps')
            ->where('aps.account = :accountId')
            ->andWhere('aps.patternHash = :patternHash')
            ->setParameter('accountId', $accountId)
            ->setParameter('patternHash', $patternHash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get statistics for suggestions by account
     */
    public function getStatsByAccount(int $accountId): array
    {
        $result = $this->createQueryBuilder('aps')
            ->select('aps.status, COUNT(aps.id) as count')
            ->where('aps.account = :accountId')
            ->setParameter('accountId', $accountId)
            ->groupBy('aps.status')
            ->getQuery()
            ->getResult();

        $stats = [
            'pending' => 0,
            'accepted' => 0,
            'accepted_altered' => 0,
            'rejected' => 0,
        ];

        foreach ($result as $row) {
            $stats[$row['status']->value] = (int) $row['count'];
        }

        return $stats;
    }

    public function save(AiPatternSuggestion $suggestion): void
    {
        $this->getEntityManager()->persist($suggestion);
        $this->getEntityManager()->flush();
    }
}
