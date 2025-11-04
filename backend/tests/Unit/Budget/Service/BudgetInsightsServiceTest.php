<?php

namespace App\Tests\Unit\Budget\Service;

use App\Budget\Service\BudgetInsightsService;
use App\Entity\Account;
use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\BudgetType;
use App\Enum\TransactionType;
use App\Money\MoneyFactory;
use App\Tests\TestCase\DatabaseTestCase;
use DateTime;
use DateTimeImmutable;
use Money\Money;

class BudgetInsightsServiceTest extends DatabaseTestCase
{
    private BudgetInsightsService $insightsService;
    private MoneyFactory $moneyFactory;
    private Account $testAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insightsService = $this->container->get(BudgetInsightsService::class);
        $this->moneyFactory = $this->container->get(MoneyFactory::class);

        // Create test account
        $this->testAccount = new Account();
        $this->testAccount->setName('Test Account')
            ->setAccountNumber('NL91TEST' . uniqid())
            ->setIsDefault(true);
        $this->entityManager->persist($this->testAccount);
        $this->entityManager->flush();
    }

    public function testComputeNormalWithOddNumberOfMonthsReturnsMedian(): void
    {
        // Create budget with 5 months of data
        // Testing with 5 months window to get odd number
        $budget = $this->createBudget('Test Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Test Category', $budget);

        $this->createMonthlyTransactions($category, [
            '-5 months' => -10000,  // €100
            '-4 months' => -20000,  // €200
            '-3 months' => -30000,  // €300
            '-2 months' => -40000,  // €400
            '-1 month' => -50000,   // €500
        ]);

        $this->entityManager->flush();

        // Use 5 months window to match the 5 data points
        $normal = $this->insightsService->computeNormal($budget, 5);

        // Median of [-500, -400, -300, -200, -100] sorted = -300 (middle value)
        $this->assertEquals(-30000, $normal->getAmount());
    }

    public function testComputeNormalWithEvenNumberOfMonthsReturnsAverage(): void
    {
        // Create budget with 4 months of data
        $budget = $this->createBudget('Test Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Test Category', $budget);

        $this->createMonthlyTransactions($category, [
            '-4 months' => -10000,  // €100
            '-3 months' => -20000,  // €200
            '-2 months' => -30000,  // €300
            '-1 month' => -40000,   // €400
        ]);

        $this->entityManager->flush();

        // Use 4 months window to match the 4 data points
        $normal = $this->insightsService->computeNormal($budget, 4);

        // Median of [-400, -300, -200, -100] sorted = (-300 + -200) / 2 = -250
        $this->assertEquals(-25000, $normal->getAmount());
    }

    public function testComputeNormalWithNoDataReturnsZero(): void
    {
        $budget = $this->createBudget('Empty Budget', BudgetType::EXPENSE);
        $this->entityManager->flush();

        $normal = $this->insightsService->computeNormal($budget, 6);

        $this->assertTrue($normal->isZero());
    }

    public function testComputeBudgetInsightReturnsNullWhenNoBaseline(): void
    {
        // Budget with no historical data
        $budget = $this->createBudget('New Budget', BudgetType::EXPENSE);
        $this->entityManager->flush();

        $insight = $this->insightsService->computeBudgetInsight($budget);

        $this->assertNull($insight);
    }

    public function testComputeBudgetInsightWithStableSpending(): void
    {
        // Normal: €300, Current: €295 (delta = -1.67%, should be "stable")
        $budget = $this->createBudget('Stable Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Test Category', $budget);

        // Create 6 months of historical data (service default is 6 months)
        $this->createMonthlyTransactions($category, [
            '-6 months' => -30000,
            '-5 months' => -30000,
            '-4 months' => -30000,
            '-3 months' => -30000,
            '-2 months' => -30000,
            '-1 month' => -30000,
        ]);

        // Current month: €295
        $this->createTransaction($category, -29500, new DateTimeImmutable('now'));

        $this->entityManager->flush();

        $insight = $this->insightsService->computeBudgetInsight($budget);

        $this->assertNotNull($insight);
        $this->assertEquals('stable', $insight['level']);
        $this->assertStringContainsString('Stabiel', $insight['message']);
        $this->assertLessThan(10, abs($insight['deltaPercent']));
    }

    public function testComputeBudgetInsightWithSlightIncrease(): void
    {
        // Normal: €300, Current: €350 (delta = +16.67%, should be "slight")
        $budget = $this->createBudget('Slightly Higher Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Test Category', $budget);

        $this->createMonthlyTransactions($category, [
            '-6 months' => -30000,
            '-5 months' => -30000,
            '-4 months' => -30000,
            '-3 months' => -30000,
            '-2 months' => -30000,
            '-1 month' => -30000,
        ]);

        // Current month: €350
        $this->createTransaction($category, -35000, new DateTimeImmutable('now'));

        $this->entityManager->flush();

        $insight = $this->insightsService->computeBudgetInsight($budget);

        $this->assertNotNull($insight);
        $this->assertEquals('slight', $insight['level']);
        $this->assertStringContainsString('Iets hoger dan normaal', $insight['message']);
        $this->assertGreaterThanOrEqual(10, abs($insight['deltaPercent']));
        $this->assertLessThan(30, abs($insight['deltaPercent']));
    }

    public function testComputeBudgetInsightWithSlightDecrease(): void
    {
        // Normal: €300, Current: €250 (delta = -16.67%, should be "slight")
        $budget = $this->createBudget('Slightly Lower Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Test Category', $budget);

        $this->createMonthlyTransactions($category, [
            '-6 months' => -30000,
            '-5 months' => -30000,
            '-4 months' => -30000,
            '-3 months' => -30000,
            '-2 months' => -30000,
            '-1 month' => -30000,
        ]);

        // Current month: €250
        $this->createTransaction($category, -25000, new DateTimeImmutable('now'));

        $this->entityManager->flush();

        $insight = $this->insightsService->computeBudgetInsight($budget);

        $this->assertNotNull($insight);
        $this->assertEquals('slight', $insight['level']);
        $this->assertStringContainsString('Iets lager dan normaal', $insight['message']);
        $this->assertGreaterThanOrEqual(10, abs($insight['deltaPercent']));
        $this->assertLessThan(30, abs($insight['deltaPercent']));
    }

    public function testComputeBudgetInsightWithAnomalyIncrease(): void
    {
        // Normal: €300, Current: €450 (delta = +50%, should be "anomaly")
        $budget = $this->createBudget('Anomaly High Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Test Category', $budget);

        $this->createMonthlyTransactions($category, [
            '-6 months' => -30000,
            '-5 months' => -30000,
            '-4 months' => -30000,
            '-3 months' => -30000,
            '-2 months' => -30000,
            '-1 month' => -30000,
        ]);

        // Current month: €450
        $this->createTransaction($category, -45000, new DateTimeImmutable('now'));

        $this->entityManager->flush();

        $insight = $this->insightsService->computeBudgetInsight($budget);

        $this->assertNotNull($insight);
        $this->assertEquals('anomaly', $insight['level']);
        $this->assertStringContainsString('Opvallend hoger', $insight['message']);
        $this->assertGreaterThanOrEqual(30, abs($insight['deltaPercent']));
    }

    public function testComputeBudgetInsightWithAnomalyDecrease(): void
    {
        // Normal: €300, Current: €150 (delta = -50%, should be "anomaly")
        $budget = $this->createBudget('Anomaly Low Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Test Category', $budget);

        $this->createMonthlyTransactions($category, [
            '-6 months' => -30000,
            '-5 months' => -30000,
            '-4 months' => -30000,
            '-3 months' => -30000,
            '-2 months' => -30000,
            '-1 month' => -30000,
        ]);

        // Current month: €150
        $this->createTransaction($category, -15000, new DateTimeImmutable('now'));

        $this->entityManager->flush();

        $insight = $this->insightsService->computeBudgetInsight($budget);

        $this->assertNotNull($insight);
        $this->assertEquals('anomaly', $insight['level']);
        $this->assertStringContainsString('Opvallend lager', $insight['message']);
        $this->assertGreaterThanOrEqual(30, abs($insight['deltaPercent']));
    }

    public function testComputeInsightsSkipsProjectBudgets(): void
    {
        // Create PROJECT budget
        $projectBudget = $this->createBudget('Project', BudgetType::PROJECT);
        $projectCategory = $this->createCategory('Project Category', $projectBudget);

        $this->createMonthlyTransactions($projectCategory, [
            '-6 months' => -30000,
            '-5 months' => -30000,
            '-4 months' => -30000,
            '-3 months' => -30000,
            '-2 months' => -30000,
            '-1 month' => -30000,
        ]);

        // Create EXPENSE budget
        $expenseBudget = $this->createBudget('Expense', BudgetType::EXPENSE);
        $expenseCategory = $this->createCategory('Expense Category', $expenseBudget);

        $this->createMonthlyTransactions($expenseCategory, [
            '-6 months' => -30000,
            '-5 months' => -30000,
            '-4 months' => -30000,
            '-3 months' => -30000,
            '-2 months' => -30000,
            '-1 month' => -30000,
        ]);

        $this->createTransaction($expenseCategory, -45000, new DateTimeImmutable('now'));

        $this->entityManager->flush();

        $insights = $this->insightsService->computeInsights([$projectBudget, $expenseBudget]);

        // Should only return 1 insight (expense), not project
        $this->assertCount(1, $insights);
        $this->assertEquals('Expense', $insights[0]['budgetName']);
    }

    public function testComputeInsightsSortsByAbsoluteDeviation(): void
    {
        // Budget 1: +10% deviation
        $budget1 = $this->createBudget('Budget 1', BudgetType::EXPENSE);
        $category1 = $this->createCategory('Category 1', $budget1);

        $this->createMonthlyTransactions($category1, [
            '-6 months' => -30000,
            '-5 months' => -30000,
            '-4 months' => -30000,
            '-3 months' => -30000,
            '-2 months' => -30000,
            '-1 month' => -30000,
        ]);
        $this->createTransaction($category1, -33000, new DateTimeImmutable('now'));

        // Budget 2: +50% deviation (should be first)
        $budget2 = $this->createBudget('Budget 2', BudgetType::EXPENSE);
        $category2 = $this->createCategory('Category 2', $budget2);

        $this->createMonthlyTransactions($category2, [
            '-6 months' => -20000,
            '-5 months' => -20000,
            '-4 months' => -20000,
            '-3 months' => -20000,
            '-2 months' => -20000,
            '-1 month' => -20000,
        ]);
        $this->createTransaction($category2, -30000, new DateTimeImmutable('now'));

        $this->entityManager->flush();

        $insights = $this->insightsService->computeInsights([$budget1, $budget2]);

        $this->assertCount(2, $insights);
        // Budget 2 should be first (higher deviation)
        $this->assertEquals('Budget 2', $insights[0]['budgetName']);
        $this->assertEquals('Budget 1', $insights[1]['budgetName']);
    }

    public function testComputeInsightsRespectsLimit(): void
    {
        // Create 5 budgets
        $budgets = [];
        for ($i = 1; $i <= 5; $i++) {
            $budget = $this->createBudget("Budget $i", BudgetType::EXPENSE);
            $category = $this->createCategory("Category $i", $budget);

            $this->createMonthlyTransactions($category, [
                '-6 months' => -30000,
                '-5 months' => -30000,
                '-4 months' => -30000,
                '-3 months' => -30000,
                '-2 months' => -30000,
                '-1 month' => -30000,
            ]);
            $this->createTransaction($category, -35000, new DateTimeImmutable('now'));

            $budgets[] = $budget;
        }

        $this->entityManager->flush();

        $insights = $this->insightsService->computeInsights($budgets, 3);

        $this->assertCount(3, $insights);
    }

    public function testGetSparklineDataReturnsFloatArray(): void
    {
        $budget = $this->createBudget('Sparkline Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Test Category', $budget);

        $this->createMonthlyTransactions($category, [
            '-6 months' => 0,        // No data
            '-5 months' => -10000,  // €100
            '-4 months' => -20000,  // €200
            '-3 months' => -30000,  // €300
            '-2 months' => -40000,  // €400
            '-1 month' => -50000,   // €500
        ]);

        $this->createTransaction($category, -60000, new DateTimeImmutable('now'));

        $this->entityManager->flush();

        $sparkline = $this->insightsService->getSparklineData($budget, 6);

        $this->assertCount(6, $sparkline);
        $this->assertIsFloat($sparkline[0]);
        // Should include current month (negative for expenses)
        $this->assertEquals(-600.0, $sparkline[5]); // -€600
    }

    public function testComputeBudgetInsightIncludesAllFields(): void
    {
        $budget = $this->createBudget('Complete Budget', BudgetType::EXPENSE);
        $category = $this->createCategory('Test Category', $budget);

        $this->createMonthlyTransactions($category, [
            '-3 months' => -30000,
            '-2 months' => -30000,
            '-1 month' => -30000,
        ]);

        $this->createTransaction($category, -35000, new DateTimeImmutable('now'));

        $this->entityManager->flush();

        $insight = $this->insightsService->computeBudgetInsight($budget);

        $this->assertNotNull($insight);
        $this->assertArrayHasKey('budgetId', $insight);
        $this->assertArrayHasKey('budgetName', $insight);
        $this->assertArrayHasKey('current', $insight);
        $this->assertArrayHasKey('normal', $insight);
        $this->assertArrayHasKey('delta', $insight);
        $this->assertArrayHasKey('deltaPercent', $insight);
        $this->assertArrayHasKey('message', $insight);
        $this->assertArrayHasKey('level', $insight);
        $this->assertArrayHasKey('sparkline', $insight);

        $this->assertEquals($budget->getId(), $insight['budgetId']);
        $this->assertEquals('Complete Budget', $insight['budgetName']);
        $this->assertIsString($insight['current']);
        $this->assertIsString($insight['normal']);
        $this->assertIsString($insight['delta']);
        $this->assertIsFloat($insight['deltaPercent']);
        $this->assertIsArray($insight['sparkline']);
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
        $transaction->setBalanceAfter(Money::EUR(0));

        $this->entityManager->persist($transaction);

        return $transaction;
    }

    /**
     * Create multiple transactions spread across different months
     *
     * @param Category $category
     * @param array<string, int> $monthlyAmounts Key: relative date string, Value: amount in cents
     */
    private function createMonthlyTransactions(Category $category, array $monthlyAmounts): void
    {
        foreach ($monthlyAmounts as $relativeMonth => $amountInCents) {
            $date = new DateTimeImmutable($relativeMonth);
            // Place transaction on 15th of the month to avoid date edge cases
            $monthStart = $date->modify('first day of this month');
            $midMonth = $monthStart->modify('+14 days'); // 15th day
            $this->createTransaction($category, $amountInCents, $midMonth);
        }
    }
}
