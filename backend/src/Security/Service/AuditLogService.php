<?php

namespace App\Security\Service;

use App\Security\Entity\AuditLog;
use App\Security\Repository\AuditLogRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Log an audit event
     */
    public function log(
        string $action,
        ?User $user = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null
    ): AuditLog {
        $auditLog = new AuditLog();
        $auditLog->setAction($action);
        $auditLog->setUser($user);
        $auditLog->setEntityType($entityType);
        $auditLog->setEntityId($entityId);
        $auditLog->setDetails($details);

        // Add request information if available
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $auditLog->setIpAddress($request->getClientIp());
            $auditLog->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();

        return $auditLog;
    }

    /**
     * Log a login event
     */
    public function logLogin(User $user): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_LOGIN,
            $user,
            'User',
            $user->getId()
        );
    }

    /**
     * Log a failed login attempt
     */
    public function logFailedLogin(string $email): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_LOGIN_FAILED,
            null,
            null,
            null,
            ['email' => $this->maskEmail($email)]
        );
    }

    /**
     * Log account locked event
     */
    public function logAccountLocked(User $user): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_ACCOUNT_LOCKED,
            $user,
            'User',
            $user->getId()
        );
    }

    /**
     * Log account unlocked event
     */
    public function logAccountUnlocked(User $user): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_ACCOUNT_UNLOCKED,
            $user,
            'User',
            $user->getId()
        );
    }

    /**
     * Log password change
     */
    public function logPasswordChanged(User $user): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_PASSWORD_CHANGED,
            $user,
            'User',
            $user->getId()
        );
    }

    /**
     * Log account sharing
     */
    public function logAccountShared(User $owner, int $accountId, string $sharedWithEmail): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_ACCOUNT_SHARED,
            $owner,
            'Account',
            $accountId,
            ['shared_with' => $this->maskEmail($sharedWithEmail)]
        );
    }

    /**
     * Log transaction import
     */
    public function logTransactionImport(User $user, int $accountId, int $count): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_TRANSACTION_IMPORTED,
            $user,
            'Account',
            $accountId,
            ['count' => $count]
        );
    }

    /**
     * Log bulk categorization
     */
    public function logBulkCategorize(User $user, int $count, int $categoryId): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_BULK_CATEGORIZE,
            $user,
            'Category',
            $categoryId,
            ['count' => $count]
        );
    }

    /**
     * Log pattern creation
     */
    public function logPatternCreated(User $user, int $patternId, string $description): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_PATTERN_CREATED,
            $user,
            'Pattern',
            $patternId,
            ['description' => $description]
        );
    }

    /**
     * Log pattern deletion
     */
    public function logPatternDeleted(User $user, int $patternId): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_PATTERN_DELETED,
            $user,
            'Pattern',
            $patternId
        );
    }

    /**
     * Log data export
     */
    public function logDataExport(User $user, string $exportType, int $recordCount): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_DATA_EXPORT,
            $user,
            null,
            null,
            ['type' => $exportType, 'count' => $recordCount]
        );
    }

    /**
     * Get recent audit logs for a user
     */
    public function getRecentLogs(User $user, int $limit = 100): array
    {
        return $this->auditLogRepository->findByUser($user, $limit);
    }

    /**
     * Get security events
     */
    public function getSecurityEvents(\DateTime $since, int $limit = 100): array
    {
        return $this->auditLogRepository->findSecurityEvents($since, $limit);
    }

    /**
     * Clean up old audit logs
     */
    public function cleanupOldLogs(int $daysToKeep = 90): int
    {
        $cutoff = new \DateTime("-{$daysToKeep} days");
        return $this->auditLogRepository->deleteOlderThan($cutoff);
    }

    /**
     * Mask email for privacy in logs
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        if (strlen($local) <= 2) {
            $maskedLocal = $local[0] . '*';
        } else {
            $maskedLocal = $local[0] . str_repeat('*', strlen($local) - 2) . $local[strlen($local) - 1];
        }

        return $maskedLocal . '@' . $domain;
    }
}
