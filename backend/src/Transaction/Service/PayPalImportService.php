<?php

namespace App\Transaction\Service;

use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Money\MoneyFactory;
use App\Transaction\Repository\TransactionRepository;
use App\Pattern\Repository\PatternRepository;
use App\Pattern\Service\PatternAssignService;
use DateTime;

/**
 * Service to import PayPal transactions and create child transactions
 */
class PayPalImportService
{
    public function __construct(
        private readonly PayPalWebPasteParserService $parser,
        private readonly PayPalCsvParserService $csvParser,
        private readonly PayPalMatchingService $matcher,
        private readonly TransactionRepository $transactionRepository,
        private readonly MoneyFactory $moneyFactory,
        private readonly PatternRepository $patternRepository,
        private readonly PatternAssignService $patternAssignService
    ) {
    }

    /**
     * Import PayPal transactions from pasted text
     *
     * @param string $pastedText Copy-pasted text from PayPal website
     * @param int $accountId Account ID to match against
     * @return array Statistics about the import
     */
    public function importFromPastedText(string $pastedText, int $accountId): array
    {
        // Parse pasted text
        $pastedTransactions = $this->parser->parsePayPalWebPaste($pastedText);

        if (empty($pastedTransactions)) {
            return [
                'parsed' => 0,
                'matched' => 0,
                'imported' => 0,
                'skipped' => 0,
            ];
        }

        // Match with database transactions
        $matches = $this->matcher->matchTransactions($pastedTransactions, $accountId);

        // Create child transactions for each match
        $imported = 0;
        foreach ($matches as $match) {
            $this->createChildTransaction($match['dbTransaction'], $match['pastedTransaction']);
            $imported++;
        }

        // Apply patterns to newly created child transactions
        if ($imported > 0) {
            $this->applyPatterns($accountId);
        }

        return [
            'parsed' => count($pastedTransactions),
            'matched' => count($matches),
            'imported' => $imported,
            'skipped' => count($pastedTransactions) - count($matches),
        ];
    }

    /**
     * Import PayPal transactions from CSV export
     *
     * @param string $csvContent CSV file content from PayPal export
     * @param int $accountId Account ID to match against
     * @return array Statistics about the import
     */
    public function importFromCsv(string $csvContent, int $accountId): array
    {
        // Parse CSV
        $parsedTransactions = $this->csvParser->parseCsv($csvContent);

        if (empty($parsedTransactions)) {
            return [
                'parsed' => 0,
                'matched' => 0,
                'imported' => 0,
                'skipped' => 0,
            ];
        }

        // Match with database transactions
        $matches = $this->matcher->matchTransactions($parsedTransactions, $accountId);

        // Create child transactions for each match
        $imported = 0;
        foreach ($matches as $match) {
            $this->createChildTransaction(
                $match['dbTransaction'],
                $match['pastedTransaction'],
                $match['pastedTransaction']['reference'] ?? null
            );
            $imported++;
        }

        // Apply patterns to newly created child transactions
        if ($imported > 0) {
            $this->applyPatterns($accountId);
        }

        return [
            'parsed' => count($parsedTransactions),
            'matched' => count($matches),
            'imported' => $imported,
            'skipped' => count($parsedTransactions) - count($matches),
        ];
    }

    /**
     * Create a child transaction for a PayPal purchase
     */
    private function createChildTransaction(Transaction $parentTransaction, array $pastedTx, ?string $reference = null): void
    {
        $child = new Transaction();

        // Copy from parent
        $child->setAccount($parentTransaction->getAccount());
        $child->setBalanceAfter($parentTransaction->getBalanceAfter());

        // Set from pasted transaction
        $child->setDate(DateTime::createFromFormat('Y-m-d', $pastedTx['date']));
        $child->setDescription($pastedTx['merchant']);

        // Set amount (use absolute value - transaction type determines debit/credit)
        $amountMoney = $this->moneyFactory->fromFloat(abs($pastedTx['amount']));
        $child->setAmount($amountMoney);

        // Set transaction type (always DEBIT for PayPal expenses)
        $child->setTransactionType(TransactionType::DEBIT);

        // Set PayPal specific fields
        $child->setMutationType('PayPal');
        $child->setTransactionCode('PP');
        $child->setNotes($reference ?? '');
        $child->setCounterpartyAccount(null);
        $child->setTag('paypal');

        // Generate unique hash (include reference if available for better uniqueness)
        $child->setHash($this->generateChildHash($parentTransaction, $child, $reference));

        // Link to parent
        $child->setParentTransaction($parentTransaction);
        $parentTransaction->addSplit($child);

        // Save
        $this->transactionRepository->save($child);
    }

    /**
     * Generate a unique hash for child transaction
     */
    private function generateChildHash(Transaction $parent, Transaction $child, ?string $reference = null): string
    {
        // If we have a PayPal reference, use it for uniqueness
        if ($reference) {
            $data = sprintf('%s_%s', $parent->getId(), $reference);
        } else {
            $data = sprintf(
                '%s_%s_%s_%s',
                $parent->getId(),
                $child->getDate()->format('Y-m-d'),
                $child->getDescription(),
                $child->getAmount()->getAmount()
            );
        }

        return hash('sha256', $data);
    }

    /**
     * Apply account patterns to newly created child transactions
     */
    private function applyPatterns(int $accountId): void
    {
        $patterns = $this->patternRepository->findByAccountId($accountId);

        foreach ($patterns as $pattern) {
            $this->patternAssignService->assignSinglePattern($pattern);
        }
    }
}
