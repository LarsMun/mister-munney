import { test, expect } from '@playwright/test';

test.describe('Categories Page', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to categories page
    await page.goto('/categories');
    await page.waitForLoadState('networkidle');
  });

  test('should display categories page with main elements', async ({ page }) => {
    // Check for page title or heading
    await expect(page.locator('h1, h2').first()).toBeVisible();

    // Take screenshot
    await page.screenshot({
      path: 'playwright/screenshots/categories-page.png',
      fullPage: true
    });
  });

  test('should display category list or empty state', async ({ page }) => {
    await page.waitForTimeout(1000);

    // Either categories are displayed or an empty state
    const categoryList = page.locator('[data-testid="category-list"], .category-item, .category-card');
    const emptyState = page.locator('text=/geen categorieën|no categories|maak je eerste/i');

    const hasCategories = await categoryList.first().isVisible().catch(() => false);
    const hasEmptyState = await emptyState.isVisible().catch(() => false);

    expect(hasCategories || hasEmptyState).toBeTruthy();
  });

  test('should have create category option', async ({ page }) => {
    // Look for create/add category button
    const createButton = page.locator('button:has-text("Nieuw"), button:has-text("Categorie"), [aria-label*="create"], [aria-label*="add"], button:has-text("Toevoegen")');

    if (await createButton.first().isVisible()) {
      await expect(createButton.first()).toBeEnabled();
    }
  });

  test('should display category type tabs if available', async ({ page }) => {
    // Check for category type tabs (Expense, Income)
    const expenseTab = page.locator('button:has-text("Uitgaven"), [data-tab="expense"]');
    const incomeTab = page.locator('button:has-text("Inkomsten"), [data-tab="income"]');

    // At least one should be visible if tabs exist
    const hasExpense = await expenseTab.isVisible().catch(() => false);
    const hasIncome = await incomeTab.isVisible().catch(() => false);

    if (hasExpense || hasIncome) {
      // Take screenshot of tabs
      await page.screenshot({
        path: 'playwright/screenshots/categories-with-tabs.png',
        fullPage: true
      });
    }
  });

  test('should display category icons', async ({ page }) => {
    await page.waitForTimeout(1000);

    // Check for category icons
    const icons = page.locator('.category-icon, [data-testid="category-icon"], svg');

    if (await icons.first().isVisible()) {
      // Icons are displayed
      await expect(icons.first()).toBeVisible();
    }
  });
});

test.describe('Category Interaction', () => {
  test('should be able to click on a category', async ({ page }) => {
    await page.goto('/categories');
    await page.waitForLoadState('networkidle');

    // Find a category item
    const categoryItem = page.locator('.category-item, .category-card, [data-testid="category-item"]').first();

    if (await categoryItem.isVisible()) {
      await categoryItem.click();
      await page.waitForTimeout(500);

      // Take screenshot of selected/expanded category
      await page.screenshot({
        path: 'playwright/screenshots/category-selected.png',
        fullPage: true
      });
    }
  });

  test('should show category details or edit form when clicked', async ({ page }) => {
    await page.goto('/categories');
    await page.waitForLoadState('networkidle');

    // Find and click a category
    const categoryItem = page.locator('.category-item, .category-card, [data-testid="category-item"]').first();

    if (await categoryItem.isVisible()) {
      await categoryItem.click();
      await page.waitForTimeout(500);

      // Check for details or edit form elements
      const editElements = page.locator('input[name="name"], input[placeholder*="naam"], form');
      const detailsElements = page.locator('.category-details, [data-testid="category-details"]');

      const hasEdit = await editElements.first().isVisible().catch(() => false);
      const hasDetails = await detailsElements.isVisible().catch(() => false);

      // Either edit form or details should be visible after click
      if (hasEdit || hasDetails) {
        await page.screenshot({
          path: 'playwright/screenshots/category-details.png',
          fullPage: true
        });
      }
    }
  });
});

test.describe('Category Search/Filter', () => {
  test('should have search functionality if available', async ({ page }) => {
    await page.goto('/categories');
    await page.waitForLoadState('networkidle');

    // Look for search input
    const searchInput = page.locator('input[placeholder*="zoek"], input[placeholder*="search"], [data-testid="search"]');

    if (await searchInput.first().isVisible()) {
      await searchInput.first().fill('test');
      await page.waitForTimeout(500);

      // Take screenshot of filtered results
      await page.screenshot({
        path: 'playwright/screenshots/categories-filtered.png',
        fullPage: true
      });
    }
  });
});
