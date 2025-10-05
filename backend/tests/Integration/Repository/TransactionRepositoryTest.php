<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Tests\TestCase\DatabaseTestCase;
use App\Transaction\DTO\TransactionFilterDTO;
use App\Transaction\Repository\TransactionRepository;
use Money\Money;

class TransactionRepositoryTest extends DatabaseTestCase
{
    private TransactionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->entityManager->getRepository(Transaction::class);
    }

    public function testFindByFilterAppliesDateRange(): void
    {
        // Given
        $account = $this->createTestAccount();
        $this->createTestTransactions($account);

        $filter = new TransactionFilterDTO();
        $filter->accountId = $account->getId();
        $filter->startDate = new \DateTime('2024-01-10');
        $filter->endDate = new \DateTime('2024-01-20');

        // When
        $result = $this->repository->findByFilter($filter);

        // Then
        $this->assertCount(2, $result); // Should find 2 transactions in date range

        foreach ($result as $transaction) {
            $this->assertGreaterThanOrEqual($filter->startDate, $transaction->getDate());
            $this->assertLessThanOrEqual($filter->endDate, $transaction->getDate());
        }
    }

    public function testFindByFilterAppliesAmountRange(): void
    {
        // Given
        $account = $this->createTestAccount();
        $this->createTestTransactions($account);

        $filter = new TransactionFilterDTO();
        $filter->accountId = $account->getId();
        $filter->minAmount = 20.00;
        $filter->maxAmount = 30.00;

        // When
        $result = $this->repository->findByFilter($filter);

        // Then
        $this->assertCount(1, $result); // Should find 1 transaction in amount range

        $transaction = $result[0];
        $amount = abs($transaction->getAmount()->getAmount() / 100);
        $this->assertGreaterThanOrEqual($filter->minAmount, $amount);
        $this->assertLessThanOrEqual($filter->maxAmount, $amount);
    }

    public function testSummaryByFilterCalculatesCorrectTotals(): void
    {
        // Given
        $account = $this->createTestAccount();
        $this->createTestTransactions($account);

        $filter = new TransactionFilterDTO();
        $filter->accountId = $account->getId();

        // When
        $summary = $this->repository->summaryByFilter($filter);

        // Then
        $this->assertArrayHasKey('total_debit', $summary);
        $this->assertArrayHasKey('total_credit', $summary);
        $this->assertArrayHasKey('net_amount', $summary);
        $this->assertArrayHasKey('transaction_count', $summary);

        $this->assertEquals(44.25, $summary['total_debit']); // €25.50 + €18.75
        $this->assertEquals(3000.00, $summary['total_credit']); // €3000.00
        $this->assertEquals(2955.75, $summary['net_amount']); // €3000 - €44.25
        $this->assertEquals(3, $summary['transaction_count']);
    }

    private function createTestAccount(): Account
    {
        $account = new Account();
        $account->setName('Test Account')
            ->setAccountNumber('NL91TEST0123456789')
            ->setIsDefault(true);

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }

    private function createTestTransactions(Account $account): void
    {
        $category = new Category();
        $category->setName('Test Category')
            ->setColor('#22C55E')
            ->setIcon('test');
        $this->entityManager->persist($category);

        $transactions = [
            ['amount' => -2550, 'date' => '2024-01-15', 'type' => TransactionType::DEBIT],
            ['amount' => 300000, 'date' => '2024-01-01', 'type' => TransactionType::CREDIT],
            ['amount' => -1875, 'date' => '2024-01-20', 'type' => TransactionType::DEBIT],
        ];

        foreach ($transactions as $i => $txData) {
            $transaction = new Transaction();
            $transaction->setHash(md5("test_$i"))
                ->setDate(new \DateTime($txData['date']))
                ->setDescription("Test Transaction $i")
                ->setAccount($account)
                ->setTransactionType($txData['type'])
                ->setAmount(Money::EUR($txData['amount']))
                ->setMutationType('Test')
                ->setNotes('Test transaction')
                ->setBalanceAfter(Money::EUR(100000))
                ->setCategory($category);

            $this->entityManager->persist($transaction);
        }

        $this->entityManager->flush();
    }
}