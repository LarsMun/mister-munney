<?php

namespace App\Transaction\Service;

use App\Entity\Transaction;
use App\Entity\Account;
use App\Enum\TransactionType;
use App\Money\MoneyFactory;
use App\Transaction\Repository\TransactionRepository;
use App\Pattern\Repository\PatternRepository;
use App\Pattern\Service\PatternAssignService;
use DateTime;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service for managing transaction splits
 */
class TransactionSplitService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly MoneyFactory $moneyFactory,
        private readonly PatternRepository $patternRepository,
        private readonly PatternAssignService $patternAssignService
    ) {
    }

    /**
     * Create split transactions from parsed credit card data
     *
     * @param int $parentTransactionId The parent transaction (incasso) ID
     * @param array $splitData Array of split transaction data from parser
     * @return Transaction The parent transaction with splits
     */
    public function createSplitsFromParsedData(int $parentTransactionId, array $splitData): Transaction
    {
        // Get parent transaction
        $parentTransaction = $this->transactionRepository->find($parentTransactionId);

        if (!$parentTransaction) {
            throw new NotFoundHttpException("Parent transaction with ID $parentTransactionId not found");
        }

        // Validate parent doesn't already have splits
        if ($parentTransaction->hasSplits()) {
            throw new BadRequestHttpException("Transaction already has splits. Delete existing splits first.");
        }

        // Get parent amount for validation
        $parentAmount = $parentTransaction->getAmount();
        $parentAmountFloat = floatval($this->moneyFactory->toString($parentAmount));

        // Calculate total from splits
        $splitsTotal = 0.0;
        foreach ($splitData as $split) {
            $splitsTotal += $split['amount'];
        }

        // Validate that splits sum to parent (within 1 cent tolerance)
        // Compare absolute values since both parent and splits are negative for expenses
        if (abs(abs($splitsTotal) - abs($parentAmountFloat)) > 0.01) {
            throw new BadRequestHttpException(
                sprintf(
                    "Split transactions sum (%.2f) does not match parent transaction amount (%.2f)",
                    $splitsTotal,
                    $parentAmountFloat
                )
            );
        }

        // Create split transactions
        foreach ($splitData as $splitInfo) {
            $split = new Transaction();

            // Set basic fields
            $split->setAccount($parentTransaction->getAccount());
            $split->setDate(DateTime::createFromFormat('Y-m-d', $splitInfo['date']));
            $split->setDescription($splitInfo['description']);

            // Set amount
            $amountMoney = $this->moneyFactory->fromFloat($splitInfo['amount']);
            $split->setAmount($amountMoney);

            // Set transaction type
            $transactionType = TransactionType::from($splitInfo['transaction_type']);
            $split->setTransactionType($transactionType);

            // Set other fields
            $split->setMutationType($splitInfo['mutation_type'] ?? 'Creditcard');
            $split->setTransactionCode($splitInfo['transaction_code'] ?? 'CC');
            $split->setNotes($splitInfo['notes'] ?? '');
            $split->setCounterpartyAccount($splitInfo['counterparty_account'] ?? null);
            $split->setTag($splitInfo['tag'] ?? 'creditcard');

            // Use parent's balance after since splits don't affect actual balance
            $split->setBalanceAfter($parentTransaction->getBalanceAfter());

            // Generate unique hash for split
            $split->setHash($this->generateSplitHash($parentTransaction, $split));

            // Link to parent
            $split->setParentTransaction($parentTransaction);

            // Add to parent's collection
            $parentTransaction->addSplit($split);
        }

        // Save parent (cascades to splits)
        $this->transactionRepository->save($parentTransaction);

        // Apply patterns to newly created splits
        $this->applyPatternsToSplits($parentTransaction);

        return $parentTransaction;
    }

    /**
     * Apply account patterns to split transactions
     */
    private function applyPatternsToSplits(Transaction $parentTransaction): void
    {
        $account = $parentTransaction->getAccount();
        $patterns = $this->patternRepository->findByAccountId($account->getId());

        // Apply each pattern - they will only affect matching uncategorized splits
        foreach ($patterns as $pattern) {
            $this->patternAssignService->assignSinglePattern($pattern);
        }
    }

    /**
     * Create a single split transaction manually
     */
    public function createSplit(
        int $parentTransactionId,
        DateTime $date,
        string $description,
        float $amount,
        ?int $categoryId = null
    ): Transaction {
        $parentTransaction = $this->transactionRepository->find($parentTransactionId);

        if (!$parentTransaction) {
            throw new NotFoundHttpException("Parent transaction not found");
        }

        // Create split
        $split = new Transaction();
        $split->setAccount($parentTransaction->getAccount());
        $split->setDate($date);
        $split->setDescription($description);

        $amountMoney = $this->moneyFactory->fromFloat($amount);
        $split->setAmount($amountMoney);

        $split->setTransactionType($amount < 0 ? TransactionType::DEBIT : TransactionType::CREDIT);
        $split->setMutationType('Creditcard');
        $split->setTransactionCode('CC');
        $split->setNotes('');
        $split->setBalanceAfter($parentTransaction->getBalanceAfter());
        $split->setHash($this->generateSplitHash($parentTransaction, $split));
        $split->setParentTransaction($parentTransaction);

        $parentTransaction->addSplit($split);

        $this->transactionRepository->save($parentTransaction);

        // Apply patterns to the newly created split
        $this->applyPatternsToSplits($parentTransaction);

        return $split;
    }

    /**
     * Delete all splits from a transaction
     */
    public function deleteSplits(int $parentTransactionId): void
    {
        $parentTransaction = $this->transactionRepository->find($parentTransactionId);

        if (!$parentTransaction) {
            throw new NotFoundHttpException("Transaction not found");
        }

        // Get splits before clearing
        $splits = $parentTransaction->getSplits()->toArray();

        // Clear splits from parent
        foreach ($splits as $split) {
            $parentTransaction->removeSplit($split);
            $this->transactionRepository->delete($split);
        }

        $this->transactionRepository->save($parentTransaction);
    }

    /**
     * Delete a single split transaction
     */
    public function deleteSplit(int $splitId): void
    {
        $split = $this->transactionRepository->find($splitId);

        if (!$split) {
            throw new NotFoundHttpException("Split transaction not found");
        }

        if (!$split->isSplit()) {
            throw new BadRequestHttpException("Transaction is not a split");
        }

        $parent = $split->getParentTransaction();
        $parent->removeSplit($split);

        $this->transactionRepository->delete($split);
        $this->transactionRepository->save($parent);
    }

    /**
     * Get all splits for a parent transaction
     */
    public function getSplits(int $parentTransactionId): array
    {
        $parentTransaction = $this->transactionRepository->find($parentTransactionId);

        if (!$parentTransaction) {
            throw new NotFoundHttpException("Transaction not found");
        }

        return $parentTransaction->getSplits()->toArray();
    }

    /**
     * Generate a unique hash for a split transaction
     */
    private function generateSplitHash(Transaction $parent, Transaction $split): string
    {
        $data = sprintf(
            "%s_%s_%s_%s_%s",
            $parent->getId(),
            $split->getDate()->format('Y-m-d'),
            $split->getDescription(),
            $split->getAmount()->getAmount(),
            uniqid()
        );

        return 'SPLIT_' . hash('sha256', $data);
    }
}
