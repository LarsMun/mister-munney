<?php

namespace App\Tests\Integration\Api;

use App\Entity\Account;
use App\Tests\TestCase\ApiTestCase;

class AccountManagementTest extends ApiTestCase
{
    private Account $account1;
    private Account $account2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestAccounts();
    }

    public function testGetAllAccountsReturnsEmptyArrayInitially(): void
    {
        // Given - Clean database (setUp already cleared it)
        $this->entityManager->clear();
        $this->cleanDatabase();

        // When - Get all accounts
        $this->client->request('GET', '/api/accounts');

        // Then - Empty array returned
        $data = $this->assertJsonResponse(200);
        $this->assertIsArray($data);
        $this->assertEmpty($data, 'Should return empty array when no accounts exist');
    }

    public function testGetAllAccountsReturnsMultipleAccounts(): void
    {
        // When - Get all accounts
        $this->client->request('GET', '/api/accounts');

        // Then - All accounts returned
        $data = $this->assertJsonResponse(200);

        $this->assertIsArray($data);
        $this->assertCount(2, $data, 'Should return 2 accounts');
        
        // Verify structure
        $this->assertArrayItemsHaveKeys(['id', 'name', 'accountNumber'], $data);
        
        // Verify data
        $accountNames = array_column($data, 'name');
        $this->assertContains('Main Account', $accountNames);
        $this->assertContains('Secondary Account', $accountNames);
    }

    public function testGetAccountById(): void
    {
        // When - Get specific account
        $this->client->request('GET', '/api/accounts/' . $this->account1->getId());

        // Then - Account returned
        $data = $this->assertJsonResponse(200);

        $this->assertResponseHasKeys(['id', 'name', 'accountNumber'], $data);
        $this->assertEquals($this->account1->getId(), $data['id']);
        $this->assertEquals('Main Account', $data['name']);
        $this->assertEquals($this->account1->getAccountNumber(), $data['accountNumber']);
    }

    public function testGetNonExistentAccountReturns404(): void
    {
        // When - Get non-existent account
        $this->client->request('GET', '/api/accounts/99999');

        // Then - Not found
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testUpdateAccountName(): void
    {
        // When - Update account name
        $this->makeJsonRequest(
            'PUT',
            '/api/accounts/' . $this->account1->getId(),
            ['name' => 'Updated Main Account']
        );

        // Then - Account updated
        $data = $this->assertJsonResponse(200);

        $this->assertEquals('Updated Main Account', $data['name']);
        $this->assertEquals($this->account1->getId(), $data['id']);

        // Verify in database
        $this->entityManager->refresh($this->account1);
        $this->assertEquals('Updated Main Account', $this->account1->getName());
    }

    public function testUpdateAccountWithEmptyNameReturns400(): void
    {
        // When - Update with empty name
        $this->makeJsonRequest(
            'PUT',
            '/api/accounts/' . $this->account1->getId(),
            ['name' => '']
        );

        // Then - Bad request
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    public function testUpdateAccountWithoutNameReturns400(): void
    {
        // When - Update without name field
        $this->makeJsonRequest(
            'PUT',
            '/api/accounts/' . $this->account1->getId(),
            []
        );

        // Then - Bad request
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    public function testUpdateNonExistentAccountReturns404(): void
    {
        // When - Update non-existent account
        $this->makeJsonRequest(
            'PUT',
            '/api/accounts/99999',
            ['name' => 'New Name']
        );

        // Then - Not found
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testAccountNumberIsReadOnly(): void
    {
        // Given - Original account number
        $originalAccountNumber = $this->account1->getAccountNumber();

        // When - Try to update account number (should be ignored)
        $this->makeJsonRequest(
            'PUT',
            '/api/accounts/' . $this->account1->getId(),
            [
                'name' => 'New Name',
                'accountNumber' => 'NL99FAKE0000000000'
            ]
        );

        // Then - Name updated but account number unchanged
        $data = $this->assertJsonResponse(200);
        $this->assertEquals('New Name', $data['name']);
        $this->assertEquals($originalAccountNumber, $data['accountNumber']);

        // Verify in database
        $this->entityManager->refresh($this->account1);
        $this->assertEquals($originalAccountNumber, $this->account1->getAccountNumber());
    }

    public function testGetAccountIncludesAllRequiredFields(): void
    {
        // When - Get account
        $this->client->request('GET', '/api/accounts/' . $this->account1->getId());

        // Then - Response includes all required fields
        $data = $this->assertJsonResponse(200);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('accountNumber', $data);
        
        // Verify types
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['name']);
        $this->assertIsString($data['accountNumber']);
    }

    private function createTestAccounts(): void
    {
        // Account 1
        $this->account1 = new Account();
        $this->account1->setName('Main Account')
            ->setAccountNumber('NL91INGB' . uniqid())
            ->setIsDefault(true);
        $this->entityManager->persist($this->account1);

        // Account 2
        $this->account2 = new Account();
        $this->account2->setName('Secondary Account')
            ->setAccountNumber('NL92INGB' . uniqid())
            ->setIsDefault(false);
        $this->entityManager->persist($this->account2);

        $this->entityManager->flush();
    }
}
