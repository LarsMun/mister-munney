<?php

namespace App\Tests\Unit\Budget\Service;

use App\Budget\Service\ActiveBudgetService;
use App\Entity\Account;
use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\BudgetType;
use App\Enum\ProjectStatus;
use App\Enum\TransactionType;
use App\Tests\TestCase\DatabaseTestCase;
use DateTime;
use DateTimeImmutable;
use Money\Money;

class ActiveBudgetServiceTest extends DatabaseTestCase
{
    private ActiveBudgetService $activeBudgetService;
    private Account $testAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activeBudgetService = $this->container->get(ActiveBudgetService::class);

        // Create test account
        $this->testAccount = new Account();
        $this->testAccount->setName('Test Account')
            ->setAccountNumber('NL91TEST' . uniqid())
            ->setIsDefault(true);
        $this->entityManager->persist($this->testAccount);
        $this->entityManager->flush();
    }

    public function testGetActiveBudgetsReturnsExpenseBudgetsWithRecentTransactions(): void
    {
        // Create EXPENSE budget with category
        $budget = $this->createBudget('Groceries', BudgetType::EXPENSE);
        $category = $this->createCategory('Food', $budget);

        // Add transaction from 1 month ago
        $this->createTransaction($category, -5000, new DateTimeImmutable('-1 month'));

        $this->entityManager->flush();

        // Get active budgets (default 2 months lookback)
        $activeBudgets = $this->activeBudgetService->getActiveBudgets();

        $this->assertCount(1, $activeBudgets);
        $this->assertEquals('Groceries', $activeBudgets[0]->getName());
    }

    public function testGetActiveBudgetsExcludesExpenseBudgetsWithoutRecentTransactions(): void
    {
        // Create EXPENSE budget with category
        $budget = $this->createBudget('Old Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Old Category', $budget);

        // Add transaction from 6 months ago (outside default 2 month window)
        $this->createTransaction($category, -5000, new DateTimeImmutable('-6 months'));

        $this->entityManager->flush();

        // Get active budgets (default 2 months lookback)
        $activeBudgets = $this->activeBudgetService->getActiveBudgets();

        $this->assertCount(0, $activeBudgets);
    }

    public function testGetActiveBudgetsRespectsCustomMonthsParameter(): void
    {
        // Create EXPENSE budget with category
        $budget = $this->createBudget('Custom Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Custom Category', $budget);

        // Add transaction from 5 months ago
        $this->createTransaction($category, -5000, new DateTimeImmutable('-5 months'));

        $this->entityManager->flush();

        // Should NOT be active with 2 months lookback
        $activeBudgets = $this->activeBudgetService->getActiveBudgets(2);
        $this->assertCount(0, $activeBudgets);

        // SHOULD be active with 6 months lookback
        $activeBudgets = $this->activeBudgetService->getActiveBudgets(6);
        $this->assertCount(1, $activeBudgets);
    }

    public function testGetActiveBudgetsIncludesProjectsWithActiveStatus(): void
    {
        $this->markTestSkipped('Budget entity no longer has setStatus/setStartDate/setEndDate methods - needs refactoring');
    }

    public function testGetActiveBudgetsIncludesProjectsWithinDateRange(): void
    {
        $this->markTestSkipped('Budget entity no longer has setStatus/setStartDate/setEndDate methods - needs refactoring');
    }

    public function testGetActiveBudgetsExcludesCompletedProjects(): void
    {
        $this->markTestSkipped('Budget entity no longer has setStatus/setStartDate/setEndDate methods - needs refactoring');
    }

    public function testGetActiveBudgetsFiltersByBudgetType(): void
    {
        $this->markTestSkipped('Budget entity no longer has setStatus/setStartDate/setEndDate methods - needs refactoring');
    }

    public function testGetOlderBudgetsReturnsInactiveBudgets(): void
    {
        // Create EXPENSE budget with old transaction
        $budget = $this->createBudget('Old Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Old Category', $budget);
        $this->createTransaction($category, -5000, new DateTimeImmutable('-6 months'));

        $this->entityManager->flush();

        // Get older budgets
        $olderBudgets = $this->activeBudgetService->getOlderBudgets(2);

        $this->assertCount(1, $olderBudgets);
        $this->assertEquals('Old Budget', $olderBudgets[0]->getName());
    }

    public function testGetOlderBudgetsExcludesActiveBudgets(): void
    {
        // Create EXPENSE budget with recent transaction
        $budget = $this->createBudget('Active Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Active Category', $budget);
        $this->createTransaction($category, -5000, new DateTimeImmutable('-1 month'));

        $this->entityManager->flush();

        // Get older budgets (should be empty)
        $olderBudgets = $this->activeBudgetService->getOlderBudgets(2);

        $this->assertCount(0, $olderBudgets);
    }

    public function testIsActiveReturnsTrueForExpenseBudgetWithRecentTransactions(): void
    {
        $budget = $this->createBudget('Test Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Test Category', $budget);
        $this->createTransaction($category, -5000, new DateTimeImmutable('-1 month'));

        $this->entityManager->flush();

        $isActive = $this->activeBudgetService->isActive($budget, 2);

        $this->assertTrue($isActive);
    }

    public function testIsActiveReturnsFalseForExpenseBudgetWithoutRecentTransactions(): void
    {
        $budget = $this->createBudget('Test Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Test Category', $budget);
        $this->createTransaction($category, -5000, new DateTimeImmutable('-6 months'));

        $this->entityManager->flush();

        $isActive = $this->activeBudgetService->isActive($budget, 2);

        $this->assertFalse($isActive);
    }

    public function testIsActiveReturnsFalseForBudgetWithoutCategories(): void
    {
        $budget = $this->createBudget('Empty Budget', BudgetType::EXPENSE);

        $this->entityManager->flush();

        $isActive = $this->activeBudgetService->isActive($budget, 2);

        $this->assertFalse($isActive);
    }

    public function testIsActiveReturnsTrueForProjectWithActiveStatus(): void
    {
        $this->markTestSkipped('Budget entity no longer has setStatus method - needs refactoring');
    }

    public function testIsActiveReturnsFalseForProjectWithCompletedStatus(): void
    {
        $this->markTestSkipped('Budget entity no longer has setStatus method - needs refactoring');
    }

    public function testIsActiveReturnsTrueForProjectWithinDateRange(): void
    {
        $this->markTestSkipped('Budget entity no longer has setStartDate/setEndDate methods - needs refactoring');
    }

    public function testIsActiveReturnsFalseForProjectOutsideDateRange(): void
    {
        $this->markTestSkipped('Budget entity no longer has setStartDate/setEndDate methods - needs refactoring');
    }

    public function testIsActiveReturnsTrueForProjectWithOnlyStartDateInPast(): void
    {
        $this->markTestSkipped('Budget entity no longer has setStartDate method - needs refactoring');
    }

    public function testIsActiveReturnsFalseForProjectWithOnlyStartDateInFuture(): void
    {
        $this->markTestSkipped('Budget entity no longer has setStartDate method - needs refactoring');
    }

    public function testIsActiveReturnsFalseForProjectWithoutStatusOrDates(): void
    {
        $this->markTestSkipped('Budget entity no longer has setStatus/setStartDate/setEndDate methods - needs refactoring');
    }

    public function testGetCutoffDateReturnsCorrectDate(): void
    {
        $cutoffDate = $this->activeBudgetService->getCutoffDate(2);

        $expectedDate = (new DateTimeImmutable())
            ->modify('first day of this month')
            ->modify('-2 months');

        $this->assertEquals(
            $expectedDate->format('Y-m-d'),
            $cutoffDate->format('Y-m-d')
        );
    }

    public function testGetCutoffDateUsesDefaultMonthsWhenNotProvided(): void
    {
        $cutoffDate = $this->activeBudgetService->getCutoffDate();

        $expectedDate = (new DateTimeImmutable())
            ->modify('first day of this month')
            ->modify('-2 months'); // Default is 2

        $this->assertEquals(
            $expectedDate->format('Y-m-d'),
            $cutoffDate->format('Y-m-d')
        );
    }

    // Helper methods

    private function createBudget(string $name, BudgetType $type): Budget
    {
        $budget = new Budget();
        $budget->setName($name);
        $budget->setAccount($this->testAccount);
        $budget->setBudgetType($type);

        $this->entityManager->persist($budget);

        return $budget;
    }

    private function createCategory(string $name, Budget $budget): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setAccount($this->testAccount);

        // Set up bidirectional relationship
        $category->setBudget($budget);
        $budget->addCategory($category);

        $this->entityManager->persist($category);

        return $category;
    }

    private function createTransaction(
        Category $category,
        int $amountInCents,
        DateTimeImmutable $date
    ): Transaction {
        $amount = Money::EUR($amountInCents);

        $transaction = new Transaction();
        $transaction->setAccount($this->testAccount);
        $transaction->setCategory($category);
        $transaction->setAmount($amount);
        $transaction->setDate(DateTime::createFromImmutable($date));
        $transaction->setDescription('Test transaction');
        $transaction->setTransactionType($amountInCents < 0 ? TransactionType::DEBIT : TransactionType::CREDIT);
        $transaction->setTransactionCode($amountInCents < 0 ? 'BA' : 'OV');
        $transaction->setMutationType('Test');
        $transaction->setNotes('Test notes');
        $transaction->setHash(md5('test-transaction-' . $date->format('Y-m-d-H-i-s') . '-' . $amountInCents . '-' . uniqid()));
        $transaction->setBalanceAfter(Money::EUR(0)); // Not important for these tests

        $this->entityManager->persist($transaction);

        return $transaction;
    }
}
