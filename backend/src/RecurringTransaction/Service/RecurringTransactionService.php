<?php

namespace App\RecurringTransaction\Service;

use App\Account\Repository\AccountRepository;
use App\Category\Repository\CategoryRepository;
use App\Entity\Account;
use App\Entity\RecurringTransaction;
use App\Enum\RecurrenceFrequency;
use App\RecurringTransaction\Repository\RecurringTransactionRepository;
use App\Transaction\Repository\TransactionRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RecurringTransactionService
{
    public function __construct(
        private RecurringTransactionRepository $repository,
        private RecurringTransactionDetector $detector,
        private AccountRepository $accountRepository,
        private CategoryRepository $categoryRepository,
        private TransactionRepository $transactionRepository,
        private MerchantNormalizer $merchantNormalizer,
    ) {
    }

    /**
     * Get all recurring transactions for an account
     *
     * @return RecurringTransaction[]
     */
    public function getAllByAccount(int $accountId, ?string $frequency = null, ?bool $isActive = null): array
    {
        $account = $this->getAccountOrFail($accountId);

        if ($frequency !== null) {
            $frequencyEnum = RecurrenceFrequency::tryFrom($frequency);
            if ($frequencyEnum === null) {
                throw new \InvalidArgumentException("Invalid frequency: $frequency");
            }
            return $this->repository->findByAccountAndFrequency($account, $frequencyEnum);
        }

        if ($isActive === true) {
            return $this->repository->findActiveByAccount($account);
        }

        return $this->repository->findByAccount($account);
    }

    /**
     * Get a specific recurring transaction
     */
    public function getById(int $id, int $accountId): RecurringTransaction
    {
        $account = $this->getAccountOrFail($accountId);
        $recurring = $this->repository->find($id);

        if ($recurring === null || $recurring->getAccount()->getId() !== $account->getId()) {
            throw new NotFoundHttpException('Recurring transaction not found');
        }

        return $recurring;
    }

    /**
     * Update a recurring transaction
     */
    public function update(int $id, int $accountId, array $data): RecurringTransaction
    {
        $recurring = $this->getById($id, $accountId);

        if (isset($data['displayName'])) {
            $recurring->setDisplayName($data['displayName']);
        }

        if (isset($data['isActive'])) {
            $recurring->setIsActive((bool) $data['isActive']);
        }

        if (array_key_exists('categoryId', $data)) {
            if ($data['categoryId'] === null) {
                $recurring->setCategory(null);
            } else {
                $category = $this->categoryRepository->find($data['categoryId']);
                if ($category !== null && $category->getAccount()->getId() === $accountId) {
                    $recurring->setCategory($category);
                }
            }
        }

        $this->repository->save($recurring);

        return $recurring;
    }

    /**
     * Soft delete (deactivate) a recurring transaction
     */
    public function deactivate(int $id, int $accountId): void
    {
        $recurring = $this->getById($id, $accountId);
        $recurring->setIsActive(false);
        $this->repository->save($recurring);
    }

    /**
     * Get upcoming transactions within N days
     *
     * @return RecurringTransaction[]
     */
    public function getUpcoming(int $accountId, int $days = 30): array
    {
        $account = $this->getAccountOrFail($accountId);
        return $this->repository->findUpcoming($account, $days);
    }

    /**
     * Get overdue transactions
     *
     * @return RecurringTransaction[]
     */
    public function getOverdue(int $accountId): array
    {
        $account = $this->getAccountOrFail($accountId);
        return $this->repository->findOverdue($account);
    }

    /**
     * Trigger detection for an account
     *
     * @return RecurringTransaction[]
     */
    public function detect(int $accountId, bool $force = false): array
    {
        $account = $this->getAccountOrFail($accountId);
        return $this->detector->detect($account, $force);
    }

    /**
     * Get summary statistics for an account
     */
    public function getSummary(int $accountId): array
    {
        $account = $this->getAccountOrFail($accountId);
        return $this->repository->getSummary($account);
    }

    /**
     * Group recurring transactions by frequency
     *
     * @return array<string, RecurringTransaction[]>
     */
    public function getGroupedByFrequency(int $accountId, bool $activeOnly = true): array
    {
        $items = $activeOnly
            ? $this->repository->findActiveByAccount($this->getAccountOrFail($accountId))
            : $this->repository->findByAccount($this->getAccountOrFail($accountId));

        $grouped = [];
        foreach (RecurrenceFrequency::cases() as $freq) {
            $grouped[$freq->value] = [];
        }

        foreach ($items as $item) {
            $grouped[$item->getFrequency()->value][] = $item;
        }

        return $grouped;
    }

    /**
     * Get transactions that match a recurring transaction's pattern
     */
    public function getLinkedTransactions(int $id, int $accountId, int $limit = 20): array
    {
        $recurring = $this->getById($id, $accountId);
        $account = $recurring->getAccount();
        $merchantPattern = $recurring->getMerchantPattern();
        $transactionType = $recurring->getTransactionType();

        // Fetch transactions for this account
        $transactions = $this->transactionRepository->createQueryBuilder('t')
            ->where('t.account = :account')
            ->andWhere('t.parentTransaction IS NULL')
            ->setParameter('account', $account)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();

        // Filter by matching merchant pattern and transaction type
        $matched = [];
        foreach ($transactions as $transaction) {
            if ($transaction->getTransactionType() !== $transactionType) {
                continue;
            }

            $normalizedPattern = $this->merchantNormalizer->normalize($transaction);
            if ($normalizedPattern === $merchantPattern) {
                $matched[] = [
                    'id' => $transaction->getId(),
                    'date' => $transaction->getDate()->format('Y-m-d'),
                    'description' => $transaction->getDescription(),
                    'amount' => (int) $transaction->getAmount()->getAmount() / 100,
                    'categoryId' => $transaction->getCategory()?->getId(),
                    'categoryName' => $transaction->getCategory()?->getName(),
                    'categoryColor' => $transaction->getCategory()?->getColor(),
                ];

                if (count($matched) >= $limit) {
                    break;
                }
            }
        }

        return $matched;
    }

    private function getAccountOrFail(int $accountId): Account
    {
        $account = $this->accountRepository->find($accountId);
        if ($account === null) {
            throw new NotFoundHttpException('Account not found');
        }
        return $account;
    }
}
