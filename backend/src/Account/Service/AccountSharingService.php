<?php

namespace App\Account\Service;

use App\Account\Repository\AccountUserRepository;
use App\Entity\Account;
use App\Entity\AccountUser;
use App\Enum\AccountUserRole;
use App\Enum\AccountUserStatus;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service for managing account sharing and invitations
 *
 * Implements secure account sharing with:
 * - Owner-only control
 * - Time-limited invitations (7 days)
 * - Revocation capabilities
 */
class AccountSharingService
{
    private const INVITATION_EXPIRY_DAYS = 7;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountUserRepository $accountUserRepository,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Share account with another user via email
     *
     * Creates a pending invitation with 7-day expiry
     *
     * @param Account $account The account to share
     * @param User $owner The user initiating the share (must be owner)
     * @param string $email Email of user to share with
     * @return AccountUser|null The created invitation, or null if user not found (anti-enumeration)
     *
     * @throws ForbiddenHttpException If current user is not the owner
     * @throws BadRequestHttpException If user already has access or invitation pending
     */
    public function shareAccount(Account $account, User $owner, string $email): ?AccountUser
    {
        // Verify owner has permission to share
        if (!$account->isOwnedBy($owner)) {
            $this->logger->warning("Non-owner attempted to share account", [
                'accountId' => $account->getId(),
                'userId' => $owner->getId(),
                'email' => $email
            ]);

            throw new AccessDeniedHttpException('Only the account owner can share access.');
        }

        // Find user by email
        $userToShare = $this->userRepository->findOneBy(['email' => $email]);

        // Anti-enumeration: Don't reveal if user exists
        if (!$userToShare) {
            $this->logger->info("Share attempt with non-existent user email", [
                'accountId' => $account->getId(),
                'email' => $email
            ]);

            return null; // Controller will return success anyway
        }

        // Check if user already has access
        $existingAccess = $this->accountUserRepository->findByAccountAndUser($account, $userToShare);
        if ($existingAccess) {
            if ($existingAccess->isActive()) {
                throw new BadRequestHttpException('This user already has access to the account.');
            }

            if ($existingAccess->isPending()) {
                throw new BadRequestHttpException('An invitation for this user is already pending.');
            }

            // Revoked - can re-invite
        }

        // Create invitation
        $accountUser = new AccountUser();
        $accountUser->setAccount($account);
        $accountUser->setUser($userToShare);
        $accountUser->setRole(AccountUserRole::SHARED);
        $accountUser->setStatus(AccountUserStatus::PENDING);
        $accountUser->setInvitedBy($owner);
        $accountUser->setInvitedAt(new \DateTimeImmutable());
        $accountUser->setExpiresAt(new \DateTimeImmutable('+' . self::INVITATION_EXPIRY_DAYS . ' days'));

        $this->entityManager->persist($accountUser);
        $this->entityManager->flush();

        $this->logger->info("Account sharing invitation created", [
            'accountId' => $account->getId(),
            'ownerId' => $owner->getId(),
            'sharedWithId' => $userToShare->getId(),
            'expiresAt' => $accountUser->getExpiresAt()->format('Y-m-d H:i:s')
        ]);

        // TODO: Send email notification to user

        return $accountUser;
    }

    /**
     * Accept a pending invitation
     *
     * @param AccountUser $accountUser The invitation to accept
     * @param User $user The user accepting (must match invitation user)
     *
     * @throws BadRequestHttpException If invitation is not pending
     * @throws BadRequestHttpException If invitation has expired
     * @throws ForbiddenHttpException If user doesn't match invitation
     */
    public function acceptInvitation(AccountUser $accountUser, User $user): void
    {
        // Verify user matches invitation
        if ($accountUser->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('You cannot accept an invitation meant for another user.');
        }

        // Check if pending
        if (!$accountUser->isPending()) {
            throw new BadRequestHttpException('This invitation is not pending.');
        }

        // Check if expired
        if ($accountUser->isExpired()) {
            $this->logger->info("User attempted to accept expired invitation", [
                'accountUserId' => $accountUser->getId(),
                'userId' => $user->getId()
            ]);

            throw new BadRequestHttpException('This invitation has expired.');
        }

        // Activate access
        $accountUser->setStatus(AccountUserStatus::ACTIVE);
        $accountUser->setAcceptedAt(new \DateTimeImmutable());
        $accountUser->setExpiresAt(null); // No longer relevant

        $this->entityManager->flush();

        $this->logger->info("Account sharing invitation accepted", [
            'accountUserId' => $accountUser->getId(),
            'accountId' => $accountUser->getAccount()->getId(),
            'userId' => $user->getId()
        ]);
    }

    /**
     * Revoke user access to account
     *
     * @param Account $account The account
     * @param User $owner The user revoking access (must be owner)
     * @param User $userToRevoke The user whose access should be revoked
     *
     * @throws ForbiddenHttpException If current user is not the owner
     * @throws BadRequestHttpException If trying to revoke owner access
     * @throws NotFoundHttpException If user doesn't have access
     */
    public function revokeAccess(Account $account, User $owner, User $userToRevoke): void
    {
        // Verify owner has permission
        if (!$account->isOwnedBy($owner)) {
            $this->logger->warning("Non-owner attempted to revoke access", [
                'accountId' => $account->getId(),
                'attemptedById' => $owner->getId(),
                'targetUserId' => $userToRevoke->getId()
            ]);

            throw new AccessDeniedHttpException('Only the account owner can revoke access.');
        }

        // Find access record
        $accountUser = $this->accountUserRepository->findByAccountAndUser($account, $userToRevoke);
        if (!$accountUser) {
            throw new NotFoundHttpException('User does not have access to this account.');
        }

        // Cannot revoke owner
        if ($accountUser->isOwner()) {
            throw new BadRequestHttpException('Cannot revoke access for the account owner.');
        }

        // Revoke access
        $accountUser->setStatus(AccountUserStatus::REVOKED);

        $this->entityManager->flush();

        $this->logger->info("Account access revoked", [
            'accountUserId' => $accountUser->getId(),
            'accountId' => $account->getId(),
            'revokedById' => $owner->getId(),
            'revokedUserId' => $userToRevoke->getId()
        ]);
    }

    /**
     * Get all pending invitations for a user
     *
     * @return AccountUser[]
     */
    public function getPendingInvitationsForUser(User $user): array
    {
        return $this->accountUserRepository->findPendingInvitationsForUser($user);
    }

    /**
     * Get invitation by ID (only if pending)
     */
    public function getPendingInvitationById(int $id): ?AccountUser
    {
        return $this->accountUserRepository->findPendingInvitationById($id);
    }

    /**
     * Get all users with access to an account
     *
     * @return AccountUser[]
     */
    public function getAccountUsers(Account $account): array
    {
        return $this->accountUserRepository->findActiveUsersByAccount($account);
    }
}
