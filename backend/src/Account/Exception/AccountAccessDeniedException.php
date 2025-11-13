<?php

namespace App\Account\Exception;

use RuntimeException;

/**
 * Exception thrown when a user attempts to access an account they don't own
 *
 * Security: Prevents unauthorized account access via CSV import
 */
class AccountAccessDeniedException extends RuntimeException
{
    private string $accountNumber;

    public function __construct(string $accountNumber, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->accountNumber = $accountNumber;

        if (empty($message)) {
            $message = sprintf(
                'Access denied to account %s. This account belongs to another user. If this is a shared account, please ask the owner to invite you.',
                $this->maskAccountNumber($accountNumber)
            );
        }

        parent::__construct($message, $code, $previous);
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    /**
     * Mask account number for privacy (show first 4 and last 4 characters)
     *
     * Examples:
     * - NL91ABNA0417164300 → NL91********4300
     * - SHORT → ****
     */
    private function maskAccountNumber(string $accountNumber): string
    {
        if (strlen($accountNumber) <= 8) {
            return str_repeat('*', strlen($accountNumber));
        }

        return substr($accountNumber, 0, 4) .
               str_repeat('*', strlen($accountNumber) - 8) .
               substr($accountNumber, -4);
    }
}
