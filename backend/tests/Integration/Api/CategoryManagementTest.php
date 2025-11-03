<?php

namespace App\Tests\Integration\Api;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Tests\TestCase\ApiTestCase;
use Money\Money;

class CategoryManagementTest extends ApiTestCase
{
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createTestAccount();
    }

    public function testGetAllCategoriesReturnsEmptyArrayInitially(): void
    {
        // When - Get categories for new account
        $this->client->request('GET', '/api/account/' . $this->account->getId() . '/categories');

        // Then - Empty array returned
        $data = $this->assertJsonResponse(200);
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function testCreateCategoryWithRequiredFields(): void
    {
        // When - Create category
        $this->makeJsonRequest(
            'POST',
            '/api/account/' . $this->account->getId() . '/categories',
            [
                'name' => 'Groceries'
            ]
        );

        // Then - Category created
        $data = $this->assertJsonResponse(201);

        $this->assertResponseHasKeys(['id', 'name', 'accountId'], $data);
        $this->assertEquals('Groceries', $data['name']);
        $this->assertEquals($this->account->getId(), $data['accountId']);
    }

    public function testCreateCategoryWithOptionalFields(): void
    {
        // When - Create category with icon and color
        $this->makeJsonRequest(
            'POST',
            '/api/account/' . $this->account->getId() . '/categories',
            [
                'name' => 'Groceries',
                'icon' => 'shopping-cart',
                'color' => '#22C55E'
            ]
        );

        // Then - Category created with all fields
        $data = $this->assertJsonResponse(201);

        $this->assertStringEndsWith('shopping-cart', $data['icon']); // Icon includes path prefix
        $this->assertEquals('#22C55E', $data['color']);
    }

    public function testCreateCategoryWithoutNameReturns400(): void
    {
        // When - Create category without name
        $this->makeJsonRequest(
            'POST',
            '/api/account/' . $this->account->getId() . '/categories',
            []
        );

        // Then - Bad request
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    public function testGetCategoryById(): void
    {
        // Given - Existing category
        $category = $this->createCategory('Groceries', TransactionType::DEBIT);

        // When - Get category
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/categories/' . $category->getId()
        );

        // Then - Category returned
        $data = $this->assertJsonResponse(200);

        $this->assertEquals($category->getId(), $data['id']);
        $this->assertEquals('Groceries', $data['name']);
    }

    public function testGetNonExistentCategoryReturns404(): void
    {
        // When - Get non-existent category
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/categories/99999'
        );

        // Then - Not found
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testUpdateCategoryName(): void
    {
        // Given - Existing category
        $category = $this->createCategory('Groceries', TransactionType::DEBIT);

        // When - Update name
        $this->makeJsonRequest(
            'PUT',
            '/api/account/' . $this->account->getId() . '/categories/' . $category->getId(),
            ['name' => 'Supermarket']
        );

        // Then - Category updated
        $data = $this->assertJsonResponse(200);

        $this->assertEquals('Supermarket', $data['name']);
    }

    public function testUpdateCategoryIconAndColor(): void
    {
        // Given - Existing category
        $category = $this->createCategory('Groceries', TransactionType::DEBIT);

        // When - Update icon and color
        $this->makeJsonRequest(
            'PUT',
            '/api/account/' . $this->account->getId() . '/categories/' . $category->getId(),
            [
                'name' => 'Groceries',
                'icon' => 'cart',
                'color' => '#FF0000'
            ]
        );

        // Then - Category updated
        $data = $this->assertJsonResponse(200);

        $this->assertStringEndsWith('cart', $data['icon']); // Icon includes path prefix
        $this->assertEquals('#FF0000', $data['color']);
    }

    public function testUpdateCategoryPreservesOtherFields(): void
    {
        // Given - Category with icon and color
        $category = $this->createCategory('Groceries', TransactionType::DEBIT);
        $this->entityManager->refresh($category);
        $category->setIcon('cart');
        $category->setColor('#FF0000');
        $this->entityManager->flush();

        // When - Update only name
        $this->makeJsonRequest(
            'PUT',
            '/api/account/' . $this->account->getId() . '/categories/' . $category->getId(),
            [
                'name' => 'Supermarket'
            ]
        );

        // Then - Other fields preserved
        $data = $this->assertJsonResponse(200);
        $this->assertEquals('Supermarket', $data['name']);
        $this->assertStringEndsWith('cart', $data['icon']);
        $this->assertEquals('#FF0000', $data['color']);
    }

    public function testDeleteCategory(): void
    {
        // Given - Existing category
        $category = $this->createCategory('Groceries', TransactionType::DEBIT);
        $categoryId = $category->getId();

        // When - Delete category
        $this->client->request(
            'DELETE',
            '/api/account/' . $this->account->getId() . '/categories/' . $categoryId
        );

        // Then - Category deleted
        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());

        // Verify in database
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $deletedCategory = $entityManager->getRepository(Category::class)->find($categoryId);
        $this->assertNull($deletedCategory);
    }

    public function testDeleteCategoryWithTransactionsReturns409(): void
    {
        // Given - Category with transactions
        $category = $this->createCategory('Groceries', TransactionType::DEBIT);
        $this->createTransactionWithCategory($category, -2550, 'Albert Heijn');

        // When - Try to delete category
        $this->client->request(
            'DELETE',
            '/api/account/' . $this->account->getId() . '/categories/' . $category->getId()
        );

        // Then - Conflict error
        $this->assertEquals(409, $this->client->getResponse()->getStatusCode());

        // Verify category still exists
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $stillExists = $entityManager->getRepository(Category::class)->find($category->getId());
        $this->assertNotNull($stillExists);
    }

    public function testPreviewDeleteWithoutTransactions(): void
    {
        // Given - Category without transactions
        $category = $this->createCategory('Groceries', TransactionType::DEBIT);

        // When - Preview delete
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/categories/' . $category->getId() . '/preview-delete'
        );

        // Then - Can delete
        $data = $this->assertJsonResponse(200);

        $this->assertTrue($data['canDelete']);
        $this->assertEquals(0, $data['transactionCount']);
        $this->assertArrayHasKey('message', $data);
    }

    public function testPreviewDeleteWithTransactions(): void
    {
        // Given - Category with transactions
        $category = $this->createCategory('Groceries', TransactionType::DEBIT);
        $this->createTransactionWithCategory($category, -2550, 'Albert Heijn');
        $this->createTransactionWithCategory($category, -1875, 'Jumbo');

        // When - Preview delete
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/categories/' . $category->getId() . '/preview-delete'
        );

        // Then - Cannot delete
        $data = $this->assertJsonResponse(200);

        $this->assertFalse($data['canDelete']);
        $this->assertEquals(2, $data['transactionCount']);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('2 linked transaction', $data['message']);
    }

    public function testGetCategoryWithTransactions(): void
    {
        // Given - Category with transactions
        $category = $this->createCategory('Groceries', TransactionType::DEBIT);
        $this->createTransactionWithCategory($category, -2550, 'Albert Heijn');
        $this->createTransactionWithCategory($category, -1875, 'Jumbo');

        // When - Get category with transactions
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/categories/' . $category->getId() . '/with_transactions'
        );

        // Then - Category with transactions returned
        $data = $this->assertJsonResponse(200);

        $this->assertResponseHasKeys(['id', 'name', 'transactions'], $data);
        $this->assertCount(2, $data['transactions']);
        $this->assertArrayItemsHaveKeys(['id', 'description', 'amount'], $data['transactions']);
    }

    public function testGetCategoryStatistics(): void
    {
        // Given - Categories with transactions
        $groceries = $this->createCategory('Groceries', TransactionType::DEBIT);
        $this->createTransactionWithCategory($groceries, -2550, 'Albert Heijn');
        $this->createTransactionWithCategory($groceries, -1875, 'Jumbo');

        // When - Get statistics
        $this->client->request(
            'GET',
            '/api/account/' . $this->account->getId() . '/categories/statistics/by-category'
        );

        // Then - Statistics returned
        $data = $this->assertJsonResponse(200);

        $this->assertIsArray($data);
        // Statistics structure depends on your implementation
    }

    public function testGetAllCategoriesReturnsMultipleCategories(): void
    {
        // Given - Multiple categories
        $this->createCategory('Groceries', TransactionType::DEBIT);
        $this->createCategory('Transport', TransactionType::DEBIT);
        $this->createCategory('Salary', TransactionType::CREDIT);

        // When - Get all categories
        $this->client->request('GET', '/api/account/' . $this->account->getId() . '/categories');

        // Then - All categories returned
        $data = $this->assertJsonResponse(200);

        $this->assertCount(3, $data);
        $this->assertArrayItemsHaveKeys(['id', 'name'], $data);
    }

    public function testGetAllCategoriesIncludesOptionalFields(): void
    {
        // Given - Categories with and without optional fields
        $cat1 = $this->createCategory('Groceries', TransactionType::DEBIT);
        $cat1->setIcon('cart');
        $cat1->setColor('#FF0000');
        $this->entityManager->flush();

        $this->createCategory('Transport', TransactionType::DEBIT);

        // When - Get all categories
        $this->client->request('GET', '/api/account/' . $this->account->getId() . '/categories');

        // Then - Categories returned with optional fields
        $data = $this->assertJsonResponse(200);

        $this->assertCount(2, $data);

        // Find the groceries category
        $groceries = array_filter($data, fn($c) => $c['name'] === 'Groceries')[0] ?? null;
        $this->assertNotNull($groceries);
        $this->assertStringEndsWith('cart', $groceries['icon']);
        $this->assertEquals('#FF0000', $groceries['color']);
    }

    private function createTestAccount(): Account
    {
        $account = new Account();
        $account->setName('Test Account')
            ->setAccountNumber('TEST' . uniqid()) // UNIEKE NUMMER!
            ->setIsDefault(true);

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $this->account = $account;
    }

    private function createCategory(string $name, TransactionType $type): Category
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $category = new Category();
        $category->setName($name)
            ->setAccount($this->account);

        $entityManager->persist($category);
        $entityManager->flush();

        return $category;
    }

    public function testPreviewMerge(): void
    {
        // Given - Two categories with transactions
        $source = $this->createCategory('Boodschappen oud', TransactionType::DEBIT);
        $target = $this->createCategory('Boodschappen', TransactionType::DEBIT);

        $this->createTransactionWithCategory($source, -2550, 'Albert Heijn');
        $this->createTransactionWithCategory($source, -1875, 'Jumbo');
        $this->createTransactionWithCategory($target, -3200, 'Lidl');

        // When - Preview merge
        $this->client->request(
            'GET',
            sprintf(
                '/api/account/%d/categories/%d/merge-preview/%d',
                $this->account->getId(),
                $source->getId(),
                $target->getId()
            )
        );

        // Then - Preview data returned
        $data = $this->assertJsonResponse(200);

        $this->assertArrayHasKey('sourceCategory', $data);
        $this->assertArrayHasKey('targetCategory', $data);
        $this->assertEquals(2, $data['transactionsToMove']);
        $this->assertEquals(1, $data['targetCurrentTransactionCount']);
        $this->assertEquals(3, $data['targetNewTransactionCount']);
        $this->assertArrayHasKey('totalAmount', $data);
        $this->assertArrayHasKey('dateRange', $data);
    }

    public function testMergeCategoriesSuccessfully(): void
    {
        // Given - Two categories with transactions
        $source = $this->createCategory('Boodschappen oud', TransactionType::DEBIT);
        $target = $this->createCategory('Boodschappen', TransactionType::DEBIT);

        $trans1 = $this->createTransactionWithCategory($source, -2550, 'Albert Heijn');
        $trans2 = $this->createTransactionWithCategory($source, -1875, 'Jumbo');
        $trans3 = $this->createTransactionWithCategory($target, -3200, 'Lidl');

        $sourceId = $source->getId();

        // When - Merge categories
        $this->client->request(
            'POST',
            sprintf(
                '/api/account/%d/categories/%d/merge/%d',
                $this->account->getId(),
                $sourceId,
                $target->getId()
            )
        );

        // Then - Merge successful
        $data = $this->assertJsonResponse(200);

        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['transactionsMoved']);
        $this->assertTrue($data['sourceDeleted']);
        $this->assertArrayHasKey('message', $data);

        // Verify source category is deleted
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->clear(); // Clear cache to force fresh query

        $deletedSource = $entityManager->getRepository(Category::class)->find($sourceId);
        $this->assertNull($deletedSource);

        // Verify all transactions now belong to target
        $refreshedTarget = $entityManager->getRepository(Category::class)->find($target->getId());
        $this->assertNotNull($refreshedTarget);

        $targetTransactions = $entityManager->getRepository(Transaction::class)
            ->findBy(['category' => $refreshedTarget]);
        $this->assertCount(3, $targetTransactions);
    }

    public function testMergeCategoryIntoItselfReturns400(): void
    {
        // Given - One category
        $category = $this->createCategory('Boodschappen', TransactionType::DEBIT);

        // When - Try to merge into itself
        $this->client->request(
            'POST',
            sprintf(
                '/api/account/%d/categories/%d/merge/%d',
                $this->account->getId(),
                $category->getId(),
                $category->getId()
            )
        );

        // Then - Bad request
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    public function testMergeNonExistentCategoryReturns404(): void
    {
        // Given - One category
        $category = $this->createCategory('Boodschappen', TransactionType::DEBIT);

        // When - Try to merge with non-existent category
        $this->client->request(
            'POST',
            sprintf(
                '/api/account/%d/categories/%d/merge/%d',
                $this->account->getId(),
                $category->getId(),
                99999
            )
        );

        // Then - Not found
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testMergeEmptySourceCategory(): void
    {
        // Given - Source without transactions, target with transactions
        $source = $this->createCategory('Lege categorie', TransactionType::DEBIT);
        $target = $this->createCategory('Boodschappen', TransactionType::DEBIT);
        $this->createTransactionWithCategory($target, -3200, 'Lidl');

        $sourceId = $source->getId();

        // When - Merge empty source
        $this->client->request(
            'POST',
            sprintf(
                '/api/account/%d/categories/%d/merge/%d',
                $this->account->getId(),
                $sourceId,
                $target->getId()
            )
        );

        // Then - Merge successful
        $data = $this->assertJsonResponse(200);

        $this->assertTrue($data['success']);
        $this->assertEquals(0, $data['transactionsMoved']);
        $this->assertTrue($data['sourceDeleted']);

        // Verify source is deleted
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();

        $deletedSource = $entityManager->getRepository(Category::class)->find($sourceId);
        $this->assertNull($deletedSource);
    }

    private function createTransactionWithCategory(
        Category $category,
        int $amountInCents,
        string $description
    ): Transaction {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $transaction = new Transaction();
        $transaction->setHash(md5($description . rand()))
        ->setDate(new \DateTime())
        ->setDescription($description)
        ->setAccount($this->account)
        ->setTransactionType($amountInCents < 0 ? TransactionType::DEBIT : TransactionType::CREDIT)
        ->setAmount(Money::EUR($amountInCents))
        ->setTransactionCode('BA') // Required field
        ->setMutationType('Test')
        ->setNotes('Test')
        ->setBalanceAfter(Money::EUR(100000))
                ->setCategory($category);

        $entityManager->persist($transaction);
        $entityManager->flush();

        return $transaction;
    }
}