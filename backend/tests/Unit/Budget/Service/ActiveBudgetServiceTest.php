<?php

namespace App\Tests\Unit\Budget\Service;

use App\Budget\Service\ActiveBudgetService;
use App\Entity\Account;
use App\Entity\Budget;
use App\Entity\Category;
use App\Enum\BudgetType;
use App\Tests\TestCase\DatabaseTestCase;

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

    public function testGetActiveBudgetsReturnsActiveBudgets(): void
    {
        $budget = $this->createBudget('Groceries', BudgetType::EXPENSE, true);
        $this->entityManager->flush();

        $activeBudgets = $this->activeBudgetService->getActiveBudgets();

        $this->assertCount(1, $activeBudgets);
        $this->assertEquals('Groceries', $activeBudgets[0]->getName());
    }

    public function testGetActiveBudgetsExcludesInactiveBudgets(): void
    {
        $this->createBudget('Inactive Budget', BudgetType::EXPENSE, false);
        $this->entityManager->flush();

        $activeBudgets = $this->activeBudgetService->getActiveBudgets();

        $this->assertCount(0, $activeBudgets);
    }

    public function testGetActiveBudgetsExcludesProjectBudgets(): void
    {
        $this->createBudget('Active Project', BudgetType::PROJECT, true);
        $this->entityManager->flush();

        $activeBudgets = $this->activeBudgetService->getActiveBudgets();

        $this->assertCount(0, $activeBudgets);
    }

    public function testGetActiveBudgetsFiltersByType(): void
    {
        $this->createBudget('Expense Budget', BudgetType::EXPENSE, true);
        $this->createBudget('Income Budget', BudgetType::INCOME, true);
        $this->entityManager->flush();

        $expenseBudgets = $this->activeBudgetService->getActiveBudgets(BudgetType::EXPENSE);
        $this->assertCount(1, $expenseBudgets);
        $this->assertEquals('Expense Budget', $expenseBudgets[0]->getName());

        $incomeBudgets = $this->activeBudgetService->getActiveBudgets(BudgetType::INCOME);
        $this->assertCount(1, $incomeBudgets);
        $this->assertEquals('Income Budget', $incomeBudgets[0]->getName());
    }

    public function testGetOlderBudgetsReturnsInactiveBudgets(): void
    {
        $this->createBudget('Inactive Budget', BudgetType::EXPENSE, false);
        $this->entityManager->flush();

        $olderBudgets = $this->activeBudgetService->getOlderBudgets();

        $this->assertCount(1, $olderBudgets);
        $this->assertEquals('Inactive Budget', $olderBudgets[0]->getName());
    }

    public function testGetOlderBudgetsExcludesActiveBudgets(): void
    {
        $this->createBudget('Active Budget', BudgetType::EXPENSE, true);
        $this->entityManager->flush();

        $olderBudgets = $this->activeBudgetService->getOlderBudgets();

        $this->assertCount(0, $olderBudgets);
    }

    public function testGetOlderBudgetsExcludesProjectBudgets(): void
    {
        $this->createBudget('Inactive Project', BudgetType::PROJECT, false);
        $this->entityManager->flush();

        $olderBudgets = $this->activeBudgetService->getOlderBudgets();

        $this->assertCount(0, $olderBudgets);
    }

    public function testMixedActiveBudgetsSeparateCorrectly(): void
    {
        $this->createBudget('Active 1', BudgetType::EXPENSE, true);
        $this->createBudget('Active 2', BudgetType::INCOME, true);
        $this->createBudget('Inactive 1', BudgetType::EXPENSE, false);
        $this->createBudget('Inactive 2', BudgetType::INCOME, false);
        $this->entityManager->flush();

        $activeBudgets = $this->activeBudgetService->getActiveBudgets();
        $this->assertCount(2, $activeBudgets);

        $olderBudgets = $this->activeBudgetService->getOlderBudgets();
        $this->assertCount(2, $olderBudgets);
    }

    public function testNewBudgetsAreActiveByDefault(): void
    {
        $budget = new Budget();
        $budget->setName('New Budget');
        $budget->setAccount($this->testAccount);
        $budget->setBudgetType(BudgetType::EXPENSE);
        $this->entityManager->persist($budget);
        $this->entityManager->flush();

        $activeBudgets = $this->activeBudgetService->getActiveBudgets();

        $this->assertCount(1, $activeBudgets);
        $this->assertTrue($budget->isActive());
    }

    // Helper methods

    private function createBudget(string $name, BudgetType $type, bool $isActive = true): Budget
    {
        $budget = new Budget();
        $budget->setName($name);
        $budget->setAccount($this->testAccount);
        $budget->setBudgetType($type);
        $budget->setIsActive($isActive);

        $this->entityManager->persist($budget);

        return $budget;
    }
}
