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
 * Service for manual PayPal transaction matching
 */
class PayPalManualMatchService
{
    public function __construct(
        private readonly PayPalCsvParserService $csvParser,
        private readonly TransactionRepository $transactionRepository,
        private readonly MoneyFactory $moneyFactory,
        private readonly PatternRepository $patternRepository,
        private readonly PatternAssignService $patternAssignService
    ) {
    }

    /**
     * Parse PayPal CSV without auto-matching
     * Returns array with temporary IDs for frontend selection
     */
    public function parseWithoutMatching(string $csvContent, int $accountId): array
    {
        $parsed = $this->csvParser->parseCsv($csvContent);

        // Get already linked references to filter them out
        $existingRefs = $this->getExistingPayPalReferences($accountId);

        // Add temporary IDs and filter already matched
        $items = [];
        foreach ($parsed as $index => $item) {
            $reference = $item['reference'] ?? '';

            // Skip if already linked
            if ($reference && in_array($reference, $existingRefs)) {
                continue;
            }

            $items[] = [
                'id' => 'pp_' . $index . '_' . uniqid(),
                'date' => $item['date'],
                'merchant' => $item['merchant'],
                'amount' => $item['amount'],
                'currency' => $item['currency'] ?? 'EUR',
                'reference' => $reference,
                'type' => $item['type'] ?? '',
            ];
        }

        return [
            'items' => $items,
            'count' => count($items),
            'totalParsed' => count($parsed),
            'alreadyLinked' => count($parsed) - count($items),
        ];
    }

    /**
     * Get bank transactions with "paypal" in description that don't have splits yet
     */
    public function getUnmatchedBankTransactions(int $accountId): array
    {
        $transactions = $this->transactionRepository->createQueryBuilder('t')
            ->where('t.account = :accountId')
            ->andWhere('LOWER(t.description) LIKE :paypal')
            ->andWhere('t.parentTransaction IS NULL')
            ->andWhere('SIZE(t.splits) = 0')
            ->setParameter('accountId', $accountId)
            ->setParameter('paypal', '%paypal%')
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn(Transaction $tx) => [
            'id' => $tx->getId(),
            'date' => $tx->getDate()->format('Y-m-d'),
            'description' => $tx->getDescription(),
            'amount' => $tx->getAmount()->getAmount() / 100,
            'hasSplits' => $tx->hasSplits(),
            'splitCount' => $tx->getSplits()->count(),
        ], $transactions);
    }

    /**
     * Create child transactions from manually selected PayPal items
     */
    public function createManualLinks(int $parentTransactionId, array $paypalItems, int $accountId): array
    {
        $parent = $this->transactionRepository->find($parentTransactionId);

        if (!$parent) {
            throw new \InvalidArgumentException('Parent transaction not found');
        }

        if ($parent->getAccount()->getId() !== $accountId) {
            throw new \InvalidArgumentException('Transaction does not belong to this account');
        }

        $created = 0;
        foreach ($paypalItems as $item) {
            $this->createChildTransaction($parent, $item);
            $created++;
        }

        // Apply patterns to newly created child transactions
        if ($created > 0) {
            $this->applyPatterns($accountId);
        }

        return [
            'created' => $created,
            'parentId' => $parentTransactionId,
        ];
    }

    /**
     * Create a child transaction for a PayPal purchase
     */
    private function createChildTransaction(Transaction $parentTransaction, array $item): void
    {
        $child = new Transaction();

        // Copy from parent
        $child->setAccount($parentTransaction->getAccount());
        $child->setBalanceAfter($parentTransaction->getBalanceAfter());

        // Set from PayPal item
        $date = DateTime::createFromFormat('Y-m-d', $item['date']);
        if (!$date) {
            $date = new DateTime($item['date']);
        }
        $child->setDate($date);
        $child->setDescription($item['merchant']);

        // Set amount (use absolute value - transaction type determines debit/credit)
        $amount = abs((float) $item['amount']);
        $amountMoney = $this->moneyFactory->fromFloat($amount);
        $child->setAmount($amountMoney);

        // Set transaction type (always DEBIT for PayPal expenses)
        $child->setTransactionType(TransactionType::DEBIT);

        // Set PayPal specific fields
        $child->setMutationType('PayPal');
        $child->setTransactionCode('PP');
        $child->setNotes($item['reference'] ?? '');
        $child->setCounterpartyAccount(null);
        $child->setTag('paypal');

        // Generate unique hash
        $child->setHash($this->generateChildHash($parentTransaction, $child, $item['reference'] ?? null));

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
        if ($reference) {
            $data = sprintf('%s_%s', $parent->getId(), $reference);
        } else {
            $data = sprintf(
                '%s_%s_%s_%s_%s',
                $parent->getId(),
                $child->getDate()->format('Y-m-d'),
                $child->getDescription(),
                $child->getAmount()->getAmount(),
                uniqid()
            );
        }

        return hash('sha256', $data);
    }

    /**
     * Get existing PayPal references that are already linked
     */
    private function getExistingPayPalReferences(int $accountId): array
    {
        $results = $this->transactionRepository->createQueryBuilder('t')
            ->select('t.notes')
            ->where('t.account = :accountId')
            ->andWhere('t.parentTransaction IS NOT NULL')
            ->andWhere('t.tag = :tag')
            ->andWhere('t.notes IS NOT NULL')
            ->andWhere('t.notes != :empty')
            ->setParameter('accountId', $accountId)
            ->setParameter('tag', 'paypal')
            ->setParameter('empty', '')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'notes');
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
