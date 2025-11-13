<?php

namespace App\Account\Repository;

use App\Entity\Account;
use App\Entity\AccountUser;
use App\Enum\AccountUserRole;
use App\Enum\AccountUserStatus;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for AccountUser entity
 *
 * @extends ServiceEntityRepository<AccountUser>
 */
class AccountUserRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $em;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $em)
    {
        parent::__construct($registry, AccountUser::class);
        $this->em = $em;
    }

    /**
     * Save an AccountUser entity
     */
    public function save(AccountUser $accountUser, bool $flush = true): void
    {
        $this->em->persist($accountUser);
        if ($flush) {
            $this->em->flush();
        }
    }

    /**
     * Find AccountUser by account and user
     */
    public function findByAccountAndUser(Account $account, User $user): ?AccountUser
    {
        return $this->findOneBy([
            'account' => $account,
            'user' => $user
        ]);
    }

    /**
     * Find all pending invitations for a user
     *
     * @return AccountUser[]
     */
    public function findPendingInvitationsForUser(User $user): array
    {
        return $this->createQueryBuilder('au')
            ->where('au.user = :user')
            ->andWhere('au.status = :status')
            ->andWhere('au.expiresAt IS NULL OR au.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('status', AccountUserStatus::PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('au.invitedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count owners for an account
     */
    public function countOwnersByAccount(Account $account): int
    {
        return (int) $this->createQueryBuilder('au')
            ->select('COUNT(au.id)')
            ->where('au.account = :account')
            ->andWhere('au.role = :role')
            ->andWhere('au.status = :status')
            ->setParameter('account', $account)
            ->setParameter('role', AccountUserRole::OWNER)
            ->setParameter('status', AccountUserStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find active owner for an account
     */
    public function findOwnerByAccount(Account $account): ?AccountUser
    {
        return $this->findOneBy([
            'account' => $account,
            'role' => AccountUserRole::OWNER,
            'status' => AccountUserStatus::ACTIVE
        ]);
    }

    /**
     * Find all active users for an account
     *
     * @return AccountUser[]
     */
    public function findActiveUsersByAccount(Account $account): array
    {
        return $this->findBy([
            'account' => $account,
            'status' => AccountUserStatus::ACTIVE
        ]);
    }

    /**
     * Find all accounts owned by a user
     *
     * @return AccountUser[]
     */
    public function findOwnedAccountsByUser(User $user): array
    {
        return $this->findBy([
            'user' => $user,
            'role' => AccountUserRole::OWNER,
            'status' => AccountUserStatus::ACTIVE
        ]);
    }

    /**
     * Check if a user has active access to an account
     */
    public function hasActiveAccess(User $user, Account $account): bool
    {
        $result = $this->createQueryBuilder('au')
            ->select('COUNT(au.id)')
            ->where('au.user = :user')
            ->andWhere('au.account = :account')
            ->andWhere('au.status = :status')
            ->setParameter('user', $user)
            ->setParameter('account', $account)
            ->setParameter('status', AccountUserStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Find pending invitation by ID
     */
    public function findPendingInvitationById(int $id): ?AccountUser
    {
        return $this->findOneBy([
            'id' => $id,
            'status' => AccountUserStatus::PENDING
        ]);
    }
}
