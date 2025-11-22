<?php

namespace App\Security\Service;

use App\Security\Entity\LoginAttempt;
use App\Security\Repository\LoginAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for tracking login attempts and determining account lock status
 */
readonly class LoginAttemptService
{
    private const FAILED_ATTEMPTS_THRESHOLD = 5;
    private const ATTEMPT_WINDOW_HOURS = 1;

    public function __construct(
        private LoginAttemptRepository $loginAttemptRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Record a login attempt
     */
    public function recordAttempt(
        string $email,
        bool $success,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $attempt = new LoginAttempt();
        $attempt->setEmail($email)
            ->setSuccess($success)
            ->setIpAddress($ipAddress)
            ->setUserAgent($userAgent)
            ->setAttemptedAt(new \DateTime());

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();
    }

    /**
     * Check if account should be locked based on failed attempts
     * Hybrid approach: Lock after 5 failed attempts within last hour
     */
    public function shouldLockAccount(string $email): bool
    {
        $failedCount = $this->getFailedAttemptsCount($email);
        return $failedCount >= self::FAILED_ATTEMPTS_THRESHOLD;
    }

    /**
     * Get count of failed login attempts within the last hour
     */
    public function getFailedAttemptsCount(string $email): int
    {
        $since = new \DateTime(sprintf('-%d hours', self::ATTEMPT_WINDOW_HOURS));
        return $this->loginAttemptRepository->countFailedAttempts($email, $since);
    }

    /**
     * Clear failed attempts for an email (called on successful login)
     */
    public function clearAttempts(string $email): void
    {
        // Delete failed attempts to immediately reset CAPTCHA requirement
        // Successful login attempts are kept for audit trail
        $this->loginAttemptRepository->deleteFailedAttempts($email);
        $this->entityManager->flush();
    }

    /**
     * Get time when account will be automatically unlocked
     * (i.e., when the oldest failed attempt falls outside the 1-hour window)
     */
    public function getUnlockTime(string $email): ?\DateTime
    {
        $since = new \DateTime(sprintf('-%d hours', self::ATTEMPT_WINDOW_HOURS));
        $attempts = $this->loginAttemptRepository->getAttempts($email, $since);

        if (empty($attempts)) {
            return null;
        }

        // Find oldest failed attempt
        $oldestFailedAttempt = null;
        foreach ($attempts as $attempt) {
            if (!$attempt->isSuccess()) {
                if ($oldestFailedAttempt === null ||
                    $attempt->getAttemptedAt() < $oldestFailedAttempt->getAttemptedAt()) {
                    $oldestFailedAttempt = $attempt;
                }
            }
        }

        if ($oldestFailedAttempt === null) {
            return null;
        }

        // Account unlocks when this attempt is 1 hour old
        $unlockTime = clone $oldestFailedAttempt->getAttemptedAt();
        $unlockTime->modify(sprintf('+%d hours', self::ATTEMPT_WINDOW_HOURS));

        return $unlockTime;
    }

    /**
     * Cleanup old login attempts (for maintenance/GDPR)
     * Should be run periodically via cron job
     */
    public function cleanupOldAttempts(int $daysToKeep = 90): int
    {
        $cutoffDate = new \DateTime(sprintf('-%d days', $daysToKeep));
        return $this->loginAttemptRepository->deleteOlderThan($cutoffDate);
    }
}
