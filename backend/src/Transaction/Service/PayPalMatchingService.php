<?php

namespace App\Transaction\Service;

use App\Entity\Transaction;
use App\Transaction\Repository\TransactionRepository;
use App\Money\MoneyFactory;
use DateTime;

/**
 * Service to match PayPal CSV transactions with database transactions using FIFO
 */
class PayPalMatchingService
{
    private const DATE_WINDOW_DAYS = 5; // Match window: CSV date + 0 to 5 days

    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly MoneyFactory $moneyFactory
    ) {
    }

    /**
     * Match PayPal web paste transactions with database transactions using FIFO principle
     *
     * @param array $pastedTransactions Parsed transactions from PayPalWebPasteParserService
     * @param int $accountId Account ID to search in
     * @return array Array of matches: ['pastedTransaction' => ..., 'dbTransaction' => ...]
     */
    public function matchTransactions(array $pastedTransactions, int $accountId): array
    {
        // Fetch all PayPal transactions from database (not already having children)
        $dbTransactions = $this->fetchPayPalTransactions($accountId);

        // Sort both lists chronologically (FIFO)
        usort($pastedTransactions, fn($a, $b) => strcmp($a['date'], $b['date']));
        usort($dbTransactions, fn($a, $b) => $a->getDate() <=> $b->getDate());

        $matches = [];
        $usedDbTransactions = []; // Track which DB transactions have been matched

        foreach ($pastedTransactions as $pastedTx) {
            $match = $this->findMatch($pastedTx, $dbTransactions, $usedDbTransactions);

            if ($match !== null) {
                $matches[] = [
                    'pastedTransaction' => $pastedTx,
                    'dbTransaction' => $match,
                ];
                $usedDbTransactions[] = $match->getId(); // Mark as used
            }
        }

        return $matches;
    }

    /**
     * Find a matching database transaction for a pasted transaction (FIFO)
     */
    private function findMatch(array $pastedTx, array $dbTransactions, array $usedDbTransactions): ?Transaction
    {
        $pastedAmount = $pastedTx['amount']; // Already in float (e.g., -24.99)
        $pastedDate = new DateTime($pastedTx['date']);

        // Calculate date window
        $minDate = clone $pastedDate;
        $maxDate = (clone $pastedDate)->modify('+' . self::DATE_WINDOW_DAYS . ' days');

        foreach ($dbTransactions as $dbTx) {
            // Skip if already used
            if (in_array($dbTx->getId(), $usedDbTransactions)) {
                continue;
            }

            // Check if amount matches (compare in cents to avoid float precision issues)
            $dbAmount = $dbTx->getAmount()->getAmount() / 100; // Convert cents to float
            if (abs(abs($pastedAmount) - abs($dbAmount)) > 0.01) {
                continue; // Amount doesn't match (within 1 cent tolerance)
            }

            // Check if date is within window
            $dbDate = $dbTx->getDate();
            if ($dbDate < $minDate || $dbDate > $maxDate) {
                continue; // Date outside window
            }

            // Match found! (FIFO: return first match)
            return $dbTx;
        }

        return null; // No match found
    }

    /**
     * Fetch all PayPal transactions from database for the given account
     * Only returns transactions that don't already have child transactions
     */
    private function fetchPayPalTransactions(int $accountId): array
    {
        // Find all transactions with "paypal" in description (case-insensitive)
        // Exclude transactions that are child transactions or already have splits
        return $this->transactionRepository->createQueryBuilder('t')
            ->where('t.account = :accountId')
            ->andWhere('LOWER(t.description) LIKE :paypal')
            ->andWhere('t.parentTransaction IS NULL') // Not a child transaction itself
            ->andWhere('SIZE(t.splits) = 0') // Don't match transactions that already have splits
            ->setParameter('accountId', $accountId)
            ->setParameter('paypal', '%paypal%')
            ->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
