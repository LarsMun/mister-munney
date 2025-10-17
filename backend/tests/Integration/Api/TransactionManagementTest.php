<?php

namespace App\Tests\Integration\Api;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Tests\TestCase\ApiTestCase;
use Money\Money;

class TransactionManagementTest extends ApiTestCase
{
    private Account $account;
    private Category $groceriesCategory;
    private Category $salaryCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    public function testGetTransactionsReturnsAllTransactions(): void
    {
        // When - Get all transactions
        $this->client->request('GET', '/api/account/' . $this->account->getId() . '/transactions');

        // Then - Response contains all transactions
        $data = $this->assertJsonResponse(200);

        $this->assertResponseHasKeys(['data', 'summary', 'treeMapData'], $data);
        $this->assertCount(3, $data['data'], 'Should return 3 transactions');
        $this->assertArrayItemsHaveKeys([
            'id',
            'hash',
            'date',
            'description',
            'accountId',
            'transactionType',
            'amount',
            'balanceAfter'
        ], $data['data']);
    }

    public function testGetTransactionsWithDateRangeFilter(): void
    {
        // When - Filter by date range
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/transactions',
            [
                'startDate' => '2024-01-10',
                'endDate' => '2024-01-20'
            ]
        );

        // Then - Only transactions in range are returned
        $data = $this->assertJsonResponse(200);

        $this->assertCount(2, $data['data'], 'Should return 2 transactions in date range');

        foreach ($data['data'] as $transaction) {
            $date = new \DateTime($transaction['date']);
            $this->assertGreaterThanOrEqual(new \DateTime('2024-01-10'), $date);
            $this->assertLessThanOrEqual(new \DateTime('2024-01-20'), $date);
        }
    }

    public function testGetTransactionsWithAmountFilter(): void
    {
        // When - Filter by absolute amount range (negative for debits)
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/transactions',
            [
                'minAmount' => -3000,  // -30 EUR in cents (more negative = larger debit)
                'maxAmount' => -2000   // -20 EUR in cents (less negative = smaller debit)
            ]
        );

        // Then - Only transactions in amount range
        $data = $this->assertJsonResponse(200);

        $this->assertCount(1, $data['data'], 'Should return 1 transaction in amount range');

        $transaction = $data['data'][0];
        $amount = abs($transaction['amount']);
        $this->assertGreaterThanOrEqual(20, $amount);
        $this->assertLessThanOrEqual(30, $amount);
    }

    public function testGetTransactionsWithTransactionTypeFilter(): void
    {
        // When - Filter by transaction type (debit)
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/transactions',
            ['transactionType' => 'debit']
        );

        // Then - Only debit transactions returned
        $data = $this->assertJsonResponse(200);

        $this->assertCount(2, $data['data'], 'Should return 2 debit transactions');

        foreach ($data['data'] as $transaction) {
            $this->assertEquals('debit', $transaction['transactionType']);
            $this->assertLessThan(0, $transaction['amount']);
        }
    }

    public function testGetTransactionsWithSearchFilter(): void
    {
        // When - Search for "Albert Heijn"
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/transactions',
            ['search' => 'Albert Heijn']
        );

        // Then - Only matching transactions
        $data = $this->assertJsonResponse(200);

        $this->assertCount(1, $data['data'], 'Should return 1 matching transaction');
        $this->assertStringContainsString('Albert Heijn', $data['data'][0]['description']);
    }

    public function testGetTransactionsWithSorting(): void
    {
        // When - Sort by date descending
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/transactions',
            [
                'sortBy' => 'date',
                'sortDirection' => 'DESC'
            ]
        );

        // Then - Transactions are sorted
        $data = $this->assertJsonResponse(200);

        $dates = array_map(fn($t) => $t['date'], $data['data']);
        $sortedDates = $dates;
        rsort($sortedDates);

        $this->assertEquals($sortedDates, $dates, 'Transactions should be sorted by date DESC');
    }

    public function testGetTransactionMonths(): void
    {
        // When - Get available months
        $this->client->request('GET', '/api/account/' . $this->account->getId() . '/transactions/months');

        // Then - Returns unique months
        $data = $this->assertJsonResponse(200);

        $this->assertIsArray($data);
        $this->assertContains('2024-01', $data);
    }

    public function testSetCategoryToTransaction(): void
    {
        // Given - Transaction without category
        $transaction = $this->getTransactionWithoutCategory();

        // When - Assign category
        $this->makeJsonRequest(
            'PATCH',
            '/api/account/' . $this->account->getId() . '/transactions/' . $transaction->getId() . '/category',
            ['categoryId' => $this->groceriesCategory->getId()]
        );

        // Then - Category is assigned
        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());

        // Verify in database
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->refresh($transaction);

        $this->assertNotNull($transaction->getCategory());
        $this->assertEquals($this->groceriesCategory->getId(), $transaction->getCategory()->getId());
    }

    public function testSetCategoryWithInvalidCategoryIdReturns404(): void
    {
        // Given - Transaction
        $transaction = $this->getTransactionWithoutCategory();

        // When - Assign non-existent category
        $this->makeJsonRequest(
            'PATCH',
            '/api/account/' . $this->account->getId() . '/transactions/' . $transaction->getId() . '/category',
            ['categoryId' => 99999]
        );

        // Then - Not found
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testBulkAssignCategory(): void
    {
        // Given - Only DEBIT transactions (matching groceries category type)
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        
        // Get all transactions first, then filter in PHP to avoid Doctrine field name issues
        $allTransactions = $entityManager->getRepository(Transaction::class)
            ->findBy(['account' => $this->account]);
        
        $transactions = array_filter($allTransactions, function(Transaction $t) {
            return $t->getTransactionType() === TransactionType::DEBIT;
        });
        
        $transactionIds = array_map(fn($t) => $t->getId(), $transactions);

        // When - Bulk assign category
        $this->makeJsonRequest(
            'POST',
            '/api/account/' . $this->account->getId() . '/transactions/bulk-assign-category',
            [
                'transactionIds' => $transactionIds,
                'categoryId' => $this->groceriesCategory->getId()
            ]
        );

        // Then - All DEBIT transactions have category
        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());

        // Verify in database
        foreach ($transactions as $transaction) {
            $entityManager->refresh($transaction);
            $this->assertEquals($this->groceriesCategory->getId(), $transaction->getCategory()->getId());
        }
    }

    public function testBulkRemoveCategory(): void
    {
        // Given - Transactions with categories
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $transactions = $entityManager->getRepository(Transaction::class)
            ->findBy(['account' => $this->account]);

        // Assign categories first
        foreach ($transactions as $transaction) {
            $transaction->setCategory($this->groceriesCategory);
        }
        $entityManager->flush();

        $transactionIds = array_map(fn($t) => $t->getId(), $transactions);

        // When - Bulk remove categories
        $this->makeJsonRequest(
            'POST',
            '/api/account/' . $this->account->getId() . '/transactions/bulk-remove-category',
            ['transactionIds' => $transactionIds]
        );

        // Then - Categories removed
        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());

        // Verify in database
        foreach ($transactions as $transaction) {
            $entityManager->refresh($transaction);
            $this->assertNull($transaction->getCategory());
        }
    }

    public function testGetMonthlyMedianStatistics(): void
    {
        // When - Get statistics
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/transactions/statistics/monthly-median',
            ['months' => '6']
        );

        // Then - Statistics returned
        $data = $this->assertJsonResponse(200);

        $this->assertResponseHasKeys([
            'median',
            'trimmedMean',
            'iqrMean',
            'weightedMedian',
            'plainAverage',
            'monthCount',
            'monthlyTotals'
        ], $data);

        $this->assertIsArray($data['monthlyTotals']);
    }

    public function testSummaryContainsCorrectTotals(): void
    {
        // When - Get transactions with summary
        $this->client->request('GET', '/api/account/' . $this->account->getId() . '/transactions');

        // Then - Summary has correct values
        $data = $this->assertJsonResponse(200);

        $summary = $data['summary'];

        // Should have 2 debit transactions totaling €44.25 (returned as negative)
        $this->assertEquals(-44.25, $summary['total_debit']);

        // Should have 1 credit transaction of €3000
        $this->assertEquals(3000.00, $summary['total_credit']);

        // Net should be positive (check both possible field names)
        $netAmount = $summary['net_amount'] ?? $summary['net_total'] ?? null;
        $this->assertNotNull($netAmount, 'Net amount field not found in summary');
        $this->assertGreaterThan(0, $netAmount);
    }

    private function createTestData(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create account with unique account number
        $this->account = new Account();
        $this->account->setName('Test Account')
            ->setAccountNumber('NL91TEST' . uniqid())
            ->setIsDefault(true);
        $entityManager->persist($this->account);

        // Create categories
        $this->groceriesCategory = new Category();
        $this->groceriesCategory->setName('Groceries')
            ->setColor('#22C55E')
            ->setIcon('shopping-cart')
            ->setAccount($this->account);
        $entityManager->persist($this->groceriesCategory);

        $this->salaryCategory = new Category();
        $this->salaryCategory->setName('Salary')
            ->setColor('#3B82F6')
            ->setIcon('dollar-sign')
            ->setAccount($this->account);
        $entityManager->persist($this->salaryCategory);

        // Create transactions
        $transactions = [
            ['amount' => -2550, 'date' => '2024-01-15', 'desc' => 'Albert Heijn', 'type' => TransactionType::DEBIT],
            ['amount' => 300000, 'date' => '2024-01-01', 'desc' => 'Salary', 'type' => TransactionType::CREDIT],
            ['amount' => -1875, 'date' => '2024-01-20', 'desc' => 'Jumbo', 'type' => TransactionType::DEBIT],
        ];

        $balance = Money::EUR(100000);

        foreach ($transactions as $txData) {
            $transaction = new Transaction();
            $amount = Money::EUR($txData['amount']);

            $transaction->setHash(md5($txData['desc'] . $txData['date']))
                ->setDate(new \DateTime($txData['date']))
                ->setDescription($txData['desc'])
                ->setAccount($this->account)
                ->setTransactionType($txData['type'])
                ->setAmount($amount)
                ->setTransactionCode($txData['type'] === TransactionType::CREDIT ? 'OV' : 'BA') // Required field
                ->setMutationType('Test')
                ->setNotes('Test transaction')
                ->setBalanceAfter($balance);

            if ($txData['type'] === TransactionType::CREDIT) {
                $balance = $balance->add($amount);
            } else {
                $balance = $balance->subtract($amount->absolute());
            }

            $entityManager->persist($transaction);
        }

        $entityManager->flush();
    }

    private function getTransactionWithoutCategory(): Transaction
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $transactions = $entityManager->getRepository(Transaction::class)
            ->findBy(['account' => $this->account, 'category' => null]);

        return $transactions[0] ?? $entityManager->getRepository(Transaction::class)
            ->findOneBy(['account' => $this->account]);
    }
}