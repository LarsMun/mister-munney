<?php

namespace App\Security\Service;

use App\User\Entity\User;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Service for locking/unlocking user accounts and managing unlock tokens
 */
readonly class AccountLockService
{
    private const UNLOCK_TOKEN_VALIDITY_HOURS = 1;

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private string $mailFromAddress,
        private string $mailFromName,
        private string $appUrl
    ) {}

    /**
     * Lock a user account and send unlock email
     */
    public function lockAccount(User $user, string $ipAddress): void
    {
        // Generate cryptographically secure unlock token
        $unlockToken = bin2hex(random_bytes(32));

        // Set lock fields
        $user->setIsLocked(true)
            ->setLockedAt(new \DateTime())
            ->setUnlockToken($unlockToken)
            ->setUnlockTokenExpiresAt(
                new \DateTime(sprintf('+%d hours', self::UNLOCK_TOKEN_VALIDITY_HOURS))
            );

        $this->entityManager->flush();

        // Send unlock email
        $this->sendUnlockEmail($user, $unlockToken, $ipAddress);
    }

    /**
     * Unlock account using unlock token
     */
    public function unlockAccount(string $token): User
    {
        $user = $this->userRepository->findOneBy(['unlockToken' => $token]);

        if (!$user) {
            throw new NotFoundHttpException('Invalid unlock token');
        }

        // Check if token has expired
        $now = new \DateTime();
        if ($user->getUnlockTokenExpiresAt() === null ||
            $user->getUnlockTokenExpiresAt() < $now) {
            throw new BadRequestHttpException('Unlock token has expired');
        }

        // Unlock account
        $user->setIsLocked(false)
            ->setLockedAt(null)
            ->setUnlockToken(null)
            ->setUnlockTokenExpiresAt(null);

        $this->entityManager->flush();

        return $user;
    }

    /**
     * Check if account is locked
     */
    public function isAccountLocked(User $user): bool
    {
        return $user->isLocked();
    }

    /**
     * Manually unlock account (for admin use)
     */
    public function manuallyUnlockAccount(User $user): void
    {
        $user->setIsLocked(false)
            ->setLockedAt(null)
            ->setUnlockToken(null)
            ->setUnlockTokenExpiresAt(null);

        $this->entityManager->flush();
    }

    /**
     * Send unlock email to user
     */
    private function sendUnlockEmail(User $user, string $unlockToken, string $ipAddress): void
    {
        $unlockUrl = sprintf('%s/unlock?token=%s', rtrim($this->appUrl, '/'), $unlockToken);

        $email = (new TemplatedEmail())
            ->from($this->mailFromAddress)
            ->to($user->getEmail())
            ->subject('Your Munney account was locked')
            ->htmlTemplate('emails/account_locked.html.twig')
            ->context([
                'user' => $user,
                'unlockUrl' => $unlockUrl,
                'ipAddress' => $ipAddress,
                'expiresAt' => $user->getUnlockTokenExpiresAt(),
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log the error but don't fail the account lock
            error_log(sprintf(
                'Failed to send unlock email to %s: %s',
                $user->getEmail(),
                $e->getMessage()
            ));
            throw $e; // Re-throw to see in logs
        }
    }

    /**
     * Resend unlock email (if user lost the email)
     */
    public function resendUnlockEmail(string $email): void
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            // Don't reveal if user exists or not (security)
            return;
        }

        if (!$user->isLocked()) {
            throw new BadRequestHttpException('Account is not locked');
        }

        // Check if token is still valid
        $now = new \DateTime();
        if ($user->getUnlockTokenExpiresAt() === null ||
            $user->getUnlockTokenExpiresAt() < $now) {
            // Generate new token if expired
            $unlockToken = bin2hex(random_bytes(32));
            $user->setUnlockToken($unlockToken)
                ->setUnlockTokenExpiresAt(
                    new \DateTime(sprintf('+%d hours', self::UNLOCK_TOKEN_VALIDITY_HOURS))
                );
            $this->entityManager->flush();
        } else {
            $unlockToken = $user->getUnlockToken();
        }

        // Send email with current/new token
        $this->sendUnlockEmail($user, $unlockToken, 'N/A');
    }
}
