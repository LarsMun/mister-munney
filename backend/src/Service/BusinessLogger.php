<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for logging business-critical events.
 *
 * Use this for:
 * - Authentication events (login, logout, failed attempts)
 * - Financial operations (transactions imported, categories assigned)
 * - Data modifications (account created, budget updated)
 * - Security events (password changes, permission changes)
 */
class BusinessLogger
{
    public function __construct(
        private readonly LoggerInterface $businessLogger,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Log a successful login
     */
    public function logLogin(int $userId, string $email): void
    {
        $this->log('user.login', [
            'user_id' => $userId,
            'email' => $email,
        ]);
    }

    /**
     * Log a failed login attempt
     */
    public function logFailedLogin(string $email, string $reason = 'invalid_credentials'): void
    {
        $this->log('user.login_failed', [
            'email' => $email,
            'reason' => $reason,
        ], 'warning');
    }

    /**
     * Log a logout
     */
    public function logLogout(int $userId): void
    {
        $this->log('user.logout', [
            'user_id' => $userId,
        ]);
    }

    /**
     * Log user registration
     */
    public function logRegistration(int $userId, string $email): void
    {
        $this->log('user.registered', [
            'user_id' => $userId,
            'email' => $email,
        ]);
    }

    /**
     * Log transaction import
     */
    public function logTransactionImport(int $userId, int $accountId, int $imported, int $duplicates): void
    {
        $this->log('transaction.import', [
            'user_id' => $userId,
            'account_id' => $accountId,
            'imported_count' => $imported,
            'duplicates_count' => $duplicates,
        ]);
    }

    /**
     * Log category assignment
     */
    public function logCategoryAssignment(int $userId, int $transactionId, ?int $categoryId): void
    {
        $this->log('transaction.category_assigned', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'category_id' => $categoryId,
        ]);
    }

    /**
     * Log pattern creation
     */
    public function logPatternCreated(int $userId, int $patternId, int $matchedCount): void
    {
        $this->log('pattern.created', [
            'user_id' => $userId,
            'pattern_id' => $patternId,
            'matched_transactions' => $matchedCount,
        ]);
    }

    /**
     * Log budget creation
     */
    public function logBudgetCreated(int $userId, int $budgetId, string $name): void
    {
        $this->log('budget.created', [
            'user_id' => $userId,
            'budget_id' => $budgetId,
            'name' => $name,
        ]);
    }

    /**
     * Log account creation
     */
    public function logAccountCreated(int $userId, int $accountId, string $accountNumber): void
    {
        $this->log('account.created', [
            'user_id' => $userId,
            'account_id' => $accountId,
            'account_number' => $this->maskAccountNumber($accountNumber),
        ]);
    }

    /**
     * Generic method to log any business event
     */
    public function log(string $event, array $context = [], string $level = 'info'): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $context = array_merge([
            'event' => $event,
            'correlation_id' => $request?->attributes->get('correlation_id'),
            'client_ip' => $request?->getClientIp(),
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $context);

        match ($level) {
            'debug' => $this->businessLogger->debug($event, $context),
            'warning' => $this->businessLogger->warning($event, $context),
            'error' => $this->businessLogger->error($event, $context),
            'critical' => $this->businessLogger->critical($event, $context),
            default => $this->businessLogger->info($event, $context),
        };
    }

    /**
     * Mask account number for privacy (show last 4 digits)
     */
    private function maskAccountNumber(string $accountNumber): string
    {
        $length = strlen($accountNumber);
        if ($length <= 4) {
            return $accountNumber;
        }
        return str_repeat('*', $length - 4) . substr($accountNumber, -4);
    }
}
