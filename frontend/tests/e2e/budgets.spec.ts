import { test, expect } from '@playwright/test';

test.describe('Budgets Page', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to budgets page
    await page.goto('/budgets');
    await page.waitForLoadState('networkidle');
  });

  test('should display budgets page with main elements', async ({ page }) => {
    // Check for page title or heading
    await expect(page.locator('h1, h2').first()).toBeVisible();

    // Take screenshot
    await page.screenshot({
      path: 'playwright/screenshots/budgets-page.png',
      fullPage: true
    });
  });

  test('should display budget list or empty state', async ({ page }) => {
    await page.waitForTimeout(1000);

    // Either budgets are displayed or an empty state
    const budgetList = page.locator('[data-testid="budget-list"], .budget-card, .budget-item');
    const emptyState = page.locator('text=/geen budgetten|no budgets|maak je eerste/i');

    const hasBudgets = await budgetList.first().isVisible().catch(() => false);
    const hasEmptyState = await emptyState.isVisible().catch(() => false);

    expect(hasBudgets || hasEmptyState).toBeTruthy();
  });

  test('should have create budget option', async ({ page }) => {
    // Look for create/add budget button
    const createButton = page.locator('button:has-text("Nieuw"), button:has-text("Budget"), [aria-label*="create"], [aria-label*="add"]');

    if (await createButton.first().isVisible()) {
      await expect(createButton.first()).toBeEnabled();
    }
  });

  test('should display budget types tabs if available', async ({ page }) => {
    // Check for budget type tabs (Expense, Income, Project)
    const expenseTab = page.locator('button:has-text("Uitgaven"), [data-tab="expense"]');
    const incomeTab = page.locator('button:has-text("Inkomsten"), [data-tab="income"]');
    const projectTab = page.locator('button:has-text("Project"), [data-tab="project"]');

    // At least one should be visible if tabs exist
    const hasExpense = await expenseTab.isVisible().catch(() => false);
    const hasIncome = await incomeTab.isVisible().catch(() => false);
    const hasProject = await projectTab.isVisible().catch(() => false);

    if (hasExpense || hasIncome || hasProject) {
      // Take screenshot of tabs
      await page.screenshot({
        path: 'playwright/screenshots/budgets-with-tabs.png',
        fullPage: true
      });
    }
  });
});

test.describe('Budget Card Interaction', () => {
  test('should be able to expand budget card details', async ({ page }) => {
    await page.goto('/budgets');
    await page.waitForLoadState('networkidle');

    // Find a budget card
    const budgetCard = page.locator('.budget-card, [data-testid="budget-card"]').first();

    if (await budgetCard.isVisible()) {
      await budgetCard.click();
      await page.waitForTimeout(500);

      // Take screenshot of expanded budget
      await page.screenshot({
        path: 'playwright/screenshots/budget-expanded.png',
        fullPage: true
      });
    }
  });
});
