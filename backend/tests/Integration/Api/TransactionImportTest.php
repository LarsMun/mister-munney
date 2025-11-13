<?php

namespace App\Tests\Integration\Api;

use App\Enum\TransactionType;
use App\Tests\Fixtures\CsvTestFixtures;
use App\Tests\TestCase\ApiTestCase;
use App\Entity\Account;
use App\Entity\Transaction;

class TransactionImportTest extends ApiTestCase
{
    private string $tempCsvFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsUser();
    }

    protected function tearDown(): void
    {
        if (isset($this->tempCsvFile)) {
            CsvTestFixtures::cleanup($this->tempCsvFile);
        }
        parent::tearDown();
    }

    public function testImportValidCsvCreatesAccountAndTransactions(): void
    {
        // Given - Valid CSV file
        $csvData = CsvTestFixtures::getValidRabobankCsv();
        $this->tempCsvFile = CsvTestFixtures::saveCsvToFile($csvData);

        // When - Upload CSV
        $this->uploadFile('/api/transactions/import-first', 'file', $this->tempCsvFile);

        // Then - Response is successful
        $response = $this->assertJsonResponse(201);

        // Verify account was created
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $accountRepo = $entityManager->getRepository(Account::class);
        $account = $accountRepo->findOneBy(['accountNumber' => 'NL91INGB0123456789']);

        $this->assertNotNull($account, 'Account should be created from CSV');
        $this->assertEquals('NL91INGB0123456789', $account->getAccountNumber());

        // Verify transactions were imported
        $transactionRepo = $entityManager->getRepository(Transaction::class);
        $transactions = $transactionRepo->findBy(['account' => $account]);

        $this->assertCount(3, $transactions, 'Should import 3 transactions');

        // Verify transaction details (amounts are always positive, type determines debit/credit)
        $debitTransactions = array_filter($transactions, fn($t) => $t->getTransactionType() === TransactionType::DEBIT);
        $creditTransactions = array_filter($transactions, fn($t) => $t->getTransactionType() === TransactionType::CREDIT);

        $this->assertCount(2, $debitTransactions, 'Should have 2 debit transactions');
        $this->assertCount(1, $creditTransactions, 'Should have 1 credit transaction');
    }

    public function testImportDuplicateTransactionsAreIgnored(): void
    {
        // Given - CSV with duplicate transaction
        $csvData = CsvTestFixtures::getCsvWithDuplicates();
        $this->tempCsvFile = CsvTestFixtures::saveCsvToFile($csvData);

        // When - Upload CSV
        $this->uploadFile('/api/transactions/import-first', 'file', $this->tempCsvFile);

        // Then - Response is successful
        $this->assertJsonResponse(201);

        // Verify only unique transactions are imported
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $transactionRepo = $entityManager->getRepository(Transaction::class);
        $transactions = $transactionRepo->findAll();

        // Should be 3 unique transactions, not 4 (duplicate ignored)
        $this->assertCount(3, $transactions, 'Duplicate transaction should be ignored');
    }

    public function testImportInvalidCsvReturns400(): void
    {
        // Given - Invalid CSV format
        $csvData = CsvTestFixtures::getInvalidCsv();
        $this->tempCsvFile = CsvTestFixtures::saveCsvToFile($csvData);

        // When - Upload CSV
        $this->uploadFile('/api/transactions/import-first', 'file', $this->tempCsvFile);

        // Then - Bad request response
        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testImportWithoutFileReturns400(): void
    {
        // When - POST without file
        $this->makeJsonRequest('POST', '/api/transactions/import-first', []);

        // Then - Bad request response
        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testImportEmptyCsvReturns400(): void
    {
        // Given - Empty CSV (header only)
        $csvData = CsvTestFixtures::getEmptyCsv();
        $this->tempCsvFile = CsvTestFixtures::saveCsvToFile($csvData);

        // When - Upload CSV
        $this->uploadFile('/api/transactions/import-first', 'file', $this->tempCsvFile);

        // Then - Bad request response
        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testImportCreatesCorrectMoneyValues(): void
    {
        // Given - Valid CSV
        $csvData = CsvTestFixtures::getValidRabobankCsv();
        $this->tempCsvFile = CsvTestFixtures::saveCsvToFile($csvData);

        // When - Upload CSV
        $this->uploadFile('/api/transactions/import-first', 'file', $this->tempCsvFile);
        $this->assertJsonResponse(201);

        // Then - Verify Money objects are correct
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $transactionRepo = $entityManager->getRepository(Transaction::class);
        $transactions = $transactionRepo->findAll();

        // Find the -25,50 transaction
        $albertHeijnTx = array_values(array_filter($transactions, function($t) {
            return str_contains($t->getDescription(), 'Albert Heijn');
        }))[0];

        $this->assertEquals(2550, $albertHeijnTx->getAmount()->getAmount(), 'Amount should be 25.50 EUR in cents (positive)');
        $this->assertEquals(TransactionType::DEBIT, $albertHeijnTx->getTransactionType(), 'Should be DEBIT type');

        // Find the 3000,00 salary transaction
        $salaryTx = array_values(array_filter($transactions, function($t) {
            return str_contains($t->getDescription(), 'Salaris');
        }))[0];

        $this->assertEquals(300000, $salaryTx->getAmount()->getAmount(), 'Amount should be 3000.00 EUR in cents (positive)');
        $this->assertEquals(TransactionType::CREDIT, $salaryTx->getTransactionType(), 'Should be CREDIT type');
    }
}