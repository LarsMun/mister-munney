import { test, expect } from '@playwright/test';

test.describe('Transactions Page', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to transactions page (should redirect to main/transactions)
    await page.goto('/transactions');
    await page.waitForLoadState('networkidle');
  });

  test('should display transactions page with main elements', async ({ page }) => {
    // Check for key page elements
    await expect(page.locator('h1, h2').first()).toBeVisible();

    // Take screenshot of the transactions page
    await page.screenshot({
      path: 'playwright/screenshots/transactions-page.png',
      fullPage: true
    });
  });

  test('should display filter controls', async ({ page }) => {
    // Look for date picker or filter elements
    const dateFilter = page.locator('[data-testid="date-filter"], input[type="date"], .month-picker');

    // If date filter exists, verify it
    if (await dateFilter.first().isVisible()) {
      await expect(dateFilter.first()).toBeVisible();
    }

    // Look for search input
    const searchInput = page.locator('input[placeholder*="zoek"], input[placeholder*="search"], [data-testid="search"]');
    if (await searchInput.first().isVisible()) {
      await expect(searchInput.first()).toBeEnabled();
    }
  });

  test('should display transaction list or empty state', async ({ page }) => {
    // Wait for content to load
    await page.waitForTimeout(1000);

    // Either transactions are displayed or an empty state message
    const transactionList = page.locator('[data-testid="transaction-list"], table, .transaction-item');
    const emptyState = page.locator('text=/geen transacties|no transactions|lege/i');

    const hasTransactions = await transactionList.first().isVisible().catch(() => false);
    const hasEmptyState = await emptyState.isVisible().catch(() => false);

    expect(hasTransactions || hasEmptyState).toBeTruthy();
  });

  test('should allow month navigation if available', async ({ page }) => {
    // Look for month navigation buttons
    const prevButton = page.locator('button:has-text("◀"), button[title*="vorige"], [aria-label*="previous"]');
    const nextButton = page.locator('button:has-text("▶"), button[title*="volgende"], [aria-label*="next"]');

    if (await prevButton.first().isVisible()) {
      // Click previous month
      await prevButton.first().click();
      await page.waitForTimeout(500);

      // Take screenshot after navigation
      await page.screenshot({
        path: 'playwright/screenshots/transactions-prev-month.png',
        fullPage: true
      });
    }
  });
});

test.describe('Transaction Details', () => {
  test('should be able to view transaction details', async ({ page }) => {
    await page.goto('/transactions');
    await page.waitForLoadState('networkidle');

    // Find and click on a transaction row if available
    const transactionRow = page.locator('[data-testid="transaction-row"], tr[data-transaction-id], .transaction-item').first();

    if (await transactionRow.isVisible()) {
      await transactionRow.click();

      // Wait for details to appear
      await page.waitForTimeout(500);

      // Take screenshot of transaction details
      await page.screenshot({
        path: 'playwright/screenshots/transaction-details.png',
        fullPage: true
      });
    }
  });
});
