<?php

namespace App\Tests\Integration\Api;

use App\Entity\Account;
use App\Entity\SavingsAccount;
use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Tests\TestCase\ApiTestCase;
use Money\Money;

class SavingsAccountManagementTest extends ApiTestCase
{
    private Account $account;
    private SavingsAccount $vacationSavings;
    private SavingsAccount $bufferSavings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    public function testGetAllSavingsAccountsReturnsEmptyArrayInitially(): void
    {
        // Given - Clean database
        $this->entityManager->clear();
        $this->cleanDatabase();
        $account = $this->createAccount();

        // When - Get savings accounts
        $this->client->request('GET', '/api/account/' . $account->getId() . '/savings-accounts');

        // Then - Empty array returned
        $data = $this->assertJsonResponse(200);
        $this->assertIsArray($data);
        $this->assertEmpty($data, 'Should return empty array when no savings accounts exist');
    }

    public function testGetAllSavingsAccountsReturnsMultipleAccounts(): void
    {
        // When - Get all savings accounts
        $this->client->request('GET', '/api/account/' . $this->account->getId() . '/savings-accounts');

        // Then - All savings accounts returned
        $data = $this->assertJsonResponse(200);

        $this->assertIsArray($data);
        $this->assertCount(2, $data, 'Should return 2 savings accounts');
        
        // Verify structure
        $this->assertArrayItemsHaveKeys(['id', 'name', 'accountId'], $data);
        
        // Verify data
        $names = array_column($data, 'name');
        $this->assertContains('Vacation', $names);
        $this->assertContains('Emergency Buffer', $names);
    }

    public function testCreateSavingsAccount(): void
    {
        // When - Create savings account
        $this->makeJsonRequest(
            'POST',
            '/api/account/' . $this->account->getId() . '/savings-accounts',
            [
                'name' => 'New Car',
                'targetAmount' => 15000,
                'color' => '#FF5733'
            ]
        );

        // Then - Savings account created
        $data = $this->assertJsonResponse(201);

        $this->assertResponseHasKeys(['id', 'name', 'accountId'], $data);
        $this->assertEquals('New Car', $data['name']);
        $this->assertEquals($this->account->getId(), $data['accountId']);
        
        // Target amount is returned as float string
        if (isset($data['targetAmount'])) {
            $this->assertEquals('15000.00', $data['targetAmount']);
        }
    }

    public function testCreateSavingsAccountWithoutTargetAmount(): void
    {
        // When - Create without target amount (optional field)
        $this->makeJsonRequest(
            'POST',
            '/api/account/' . $this->account->getId() . '/savings-accounts',
            [
                'name' => 'General Savings',
                'color' => '#CCCCCC'
            ]
        );

        // Then - Savings account created
        $data = $this->assertJsonResponse(201);

        $this->assertEquals('General Savings', $data['name']);
        $this->assertNull($data['targetAmount'] ?? null);
    }

    public function testCreateSavingsAccountWithoutNameReturns400(): void
    {
        // When - Create without name
        $this->makeJsonRequest(
            'POST',
            '/api/account/' . $this->account->getId() . '/savings-accounts',
            ['color' => '#CCCCCC']
        );

        // Then - Bad request
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateSavingsAccountWithoutColorReturns400(): void
    {
        // When - Create without color (required field)
        $this->makeJsonRequest(
            'POST',
            '/api/account/' . $this->account->getId() . '/savings-accounts',
            ['name' => 'Test Savings']
        );

        // Then - Bad request
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    public function testGetSavingsAccountById(): void
    {
        // When - Get specific savings account
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/savings-accounts/' . $this->vacationSavings->getId()
        );

        // Then - Savings account returned with patterns
        $data = $this->assertJsonResponse(200);

        $this->assertResponseHasKeys(['id', 'name', 'account'], $data);
        $this->assertEquals($this->vacationSavings->getId(), $data['id']);
        $this->assertEquals('Vacation', $data['name']);
        
        // Should include patterns array (even if empty)
        $this->assertArrayHasKey('patterns', $data);
        $this->assertIsArray($data['patterns']);
    }

    public function testGetNonExistentSavingsAccountReturns404(): void
    {
        // When - Get non-existent savings account
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/savings-accounts/99999'
        );

        // Then - Not found
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testGetSavingsAccountsWithDetails(): void
    {
        // When - Get detailed list
        $this->client->request('GET', '/api/account/' . $this->account->getId() . '/savings-accounts/details');

        // Then - Detailed list returned
        $data = $this->assertJsonResponse(200);

        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        
        // Each item should have account and patterns
        foreach ($data as $savingsAccount) {
            $this->assertArrayHasKey('account', $savingsAccount);
            $this->assertArrayHasKey('patterns', $savingsAccount);
            $this->assertIsArray($savingsAccount['patterns']);
        }
    }

    public function testUpdateSavingsAccountName(): void
    {
        // When - Update name
        $this->makeJsonRequest(
            'PUT',
            '/api/account/' . $this->account->getId() . '/savings-accounts/' . $this->vacationSavings->getId(),
            ['name' => 'Summer Vacation']
        );

        // Then - Savings account updated
        $data = $this->assertJsonResponse(200);

        $this->assertEquals('Summer Vacation', $data['name']);

        // Verify in database
        $this->entityManager->refresh($this->vacationSavings);
        $this->assertEquals('Summer Vacation', $this->vacationSavings->getName());
    }

    public function testUpdateSavingsAccountTargetAmount(): void
    {
        // When - Update target amount
        $this->makeJsonRequest(
            'PUT',
            '/api/account/' . $this->account->getId() . '/savings-accounts/' . $this->vacationSavings->getId(),
            [
                'name' => 'Vacation',
                'targetAmount' => 5000
            ]
        );

        // Then - Target amount updated
        $data = $this->assertJsonResponse(200);

        // Target amount should be 5000 (accepting both float and string format)
        $actualAmount = $data['targetAmount'];
        $this->assertTrue(
            $actualAmount == 5000.00 || $actualAmount === '5000.00' || $actualAmount === 5000.0,
            "Target amount should be 5000, got: " . var_export($actualAmount, true)
        );
    }

    public function testUpdateNonExistentSavingsAccountReturns404(): void
    {
        // When - Update non-existent savings account
        $this->makeJsonRequest(
            'PUT',
            '/api/account/' . $this->account->getId() . '/savings-accounts/99999',
            ['name' => 'New Name']
        );

        // Then - Not found
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testDeleteSavingsAccount(): void
    {
        // Given - Savings account ID
        $savingsId = $this->vacationSavings->getId();

        // When - Delete savings account
        $this->client->request(
            'DELETE',
            '/api/account/' . $this->account->getId() . '/savings-accounts/' . $savingsId
        );

        // Then - Deleted successfully
        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());

        // Verify in database
        $deleted = $this->entityManager->getRepository(SavingsAccount::class)->find($savingsId);
        $this->assertNull($deleted, 'Savings account should be deleted');
    }

    public function testDeleteNonExistentSavingsAccountReturns404(): void
    {
        // When - Delete non-existent savings account
        $this->client->request(
            'DELETE',
            '/api/account/' . $this->account->getId() . '/savings-accounts/99999'
        );

        // Then - Not found
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testAssignByPattern(): void
    {
        // Given - Transactions that could match patterns
        $this->createTransactionsForPatternMatching();

        // When - Assign by pattern
        $this->makeJsonRequest(
            'POST',
            '/api/account/' . $this->account->getId() . '/savings-accounts/assign-by-pattern',
            [
                'startDate' => '2024-01-01',
                'endDate' => '2024-01-31'
            ]
        );

        // Then - Response indicates matches/conflicts
        $data = $this->assertJsonResponse(200);

        // Should have a message and counts
        $this->assertArrayHasKey('message', $data);
        
        // May have matched, conflicts, skipped arrays/counts
        $this->assertTrue(
            isset($data['matched']) || 
            isset($data['conflicts']) || 
            isset($data['skipped_no_match']),
            'Response should contain match/conflict/skip information'
        );
    }

    public function testAssignByPatternWithoutDatesReturns400(): void
    {
        // When - Assign without dates
        $this->makeJsonRequest(
            'POST',
            '/api/account/' . $this->account->getId() . '/savings-accounts/assign-by-pattern',
            []
        );

        // Then - Bad request
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    private function createTestData(): void
    {
        // Create account
        $this->account = $this->createAccount();

        // Create savings accounts
        $this->vacationSavings = new SavingsAccount();
        $this->vacationSavings->setName('Vacation')
            ->setAccount($this->account)
            ->setTargetAmount('3000.00'); // €3000 as decimal string
        $this->entityManager->persist($this->vacationSavings);

        $this->bufferSavings = new SavingsAccount();
        $this->bufferSavings->setName('Emergency Buffer')
            ->setAccount($this->account)
            ->setTargetAmount('1000.00'); // €1000 as decimal string
        $this->entityManager->persist($this->bufferSavings);

        $this->entityManager->flush();
    }

    private function createAccount(): Account
    {
        $account = new Account();
        $account->setName('Test Account')
            ->setAccountNumber('NL91INGB' . uniqid())
            ->setIsDefault(true);
        
        $this->entityManager->persist($account);
        $this->entityManager->flush();
        
        return $account;
    }

    private function createTransactionsForPatternMatching(): void
    {
        // Create some test transactions
        $transaction = new Transaction();
        $transaction->setHash(md5('test' . uniqid()))
            ->setDate(new \DateTime('2024-01-15'))
            ->setDescription('Test Transaction for Pattern Matching')
            ->setAccount($this->account)
            ->setTransactionType(TransactionType::DEBIT)
            ->setAmount(Money::EUR(5000))
            ->setTransactionCode('BA')
            ->setMutationType('Test')
            ->setNotes('Test')
            ->setBalanceAfter(Money::EUR(100000));

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();
    }
}
