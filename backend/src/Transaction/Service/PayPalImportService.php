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
     * Create a child transaction for a PayPal purchase
     */
    private function createChildTransaction(Transaction $parentTransaction, array $pastedTx): void
    {
        $child = new Transaction();

        // Copy from parent
        $child->setAccount($parentTransaction->getAccount());
        $child->setBalanceAfter($parentTransaction->getBalanceAfter());

        // Set from pasted transaction
        $child->setDate(DateTime::createFromFormat('Y-m-d', $pastedTx['date']));
        $child->setDescription($pastedTx['merchant']);

        // Set amount
        $amountMoney = $this->moneyFactory->fromFloat($pastedTx['amount']);
        $child->setAmount($amountMoney);

        // Set transaction type (always DEBIT for PayPal expenses)
        $child->setTransactionType(TransactionType::DEBIT);

        // Set PayPal specific fields
        $child->setMutationType('PayPal');
        $child->setTransactionCode('PP');
        $child->setNotes(''); // Could add transaction reference here if available
        $child->setCounterpartyAccount(null);
        $child->setTag('paypal');

        // Generate unique hash
        $child->setHash($this->generateChildHash($parentTransaction, $child));

        // Link to parent
        $child->setParentTransaction($parentTransaction);
        $parentTransaction->addSplit($child);

        // Save
        $this->transactionRepository->save($child);
    }

    /**
     * Generate a unique hash for child transaction
     */
    private function generateChildHash(Transaction $parent, Transaction $child): string
    {
        $data = sprintf(
            '%s_%s_%s_%s',
            $parent->getId(),
            $child->getDate()->format('Y-m-d'),
            $child->getDescription(),
            $child->getAmount()->getAmount()
        );

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
