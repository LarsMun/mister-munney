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