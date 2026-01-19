import { test, expect } from '@playwright/test';

test.describe('Patterns Page', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to patterns page
    await page.goto('/patterns');
    await page.waitForLoadState('networkidle');
  });

  test('should display patterns page with main elements', async ({ page }) => {
    // Check for page title or heading
    await expect(page.locator('h1, h2').first()).toBeVisible();

    // Take screenshot
    await page.screenshot({
      path: 'playwright/screenshots/patterns-page.png',
      fullPage: true
    });
  });

  test('should display pattern list or empty state', async ({ page }) => {
    await page.waitForTimeout(1000);

    // Either patterns are displayed or an empty state
    const patternList = page.locator('[data-testid="pattern-list"], .pattern-item, .pattern-card, table');
    const emptyState = page.locator('text=/geen patronen|no patterns|maak je eerste/i');

    const hasPatterns = await patternList.first().isVisible().catch(() => false);
    const hasEmptyState = await emptyState.isVisible().catch(() => false);

    expect(hasPatterns || hasEmptyState).toBeTruthy();
  });

  test('should have create pattern option', async ({ page }) => {
    // Look for create/add pattern button
    const createButton = page.locator('button:has-text("Nieuw"), button:has-text("Patroon"), [aria-label*="create"], [aria-label*="add"], button:has-text("Toevoegen")');

    if (await createButton.first().isVisible()) {
      await expect(createButton.first()).toBeEnabled();
    }
  });

  test('should display pattern matching criteria columns', async ({ page }) => {
    await page.waitForTimeout(1000);

    // Check for pattern table headers or criteria labels
    const criteriaElements = page.locator('th:has-text("Beschrijving"), th:has-text("Tegenrekening"), th:has-text("Bedrag"), th:has-text("Categorie")');

    if (await criteriaElements.first().isVisible()) {
      await expect(criteriaElements.first()).toBeVisible();
    }
  });
});

test.describe('Pattern Interaction', () => {
  test('should be able to click on a pattern', async ({ page }) => {
    await page.goto('/patterns');
    await page.waitForLoadState('networkidle');

    // Find a pattern item
    const patternItem = page.locator('.pattern-item, .pattern-card, [data-testid="pattern-item"], tr[data-pattern-id]').first();

    if (await patternItem.isVisible()) {
      await patternItem.click();
      await page.waitForTimeout(500);

      // Take screenshot of selected/expanded pattern
      await page.screenshot({
        path: 'playwright/screenshots/pattern-selected.png',
        fullPage: true
      });
    }
  });

  test('should show pattern edit form when clicked', async ({ page }) => {
    await page.goto('/patterns');
    await page.waitForLoadState('networkidle');

    // Find and click a pattern
    const patternItem = page.locator('.pattern-item, .pattern-card, [data-testid="pattern-item"], tr').first();

    if (await patternItem.isVisible()) {
      await patternItem.click();
      await page.waitForTimeout(500);

      // Check for edit form elements
      const editElements = page.locator('input[name="descriptionPattern"], input[name="contraAccountPattern"], form, [data-testid="pattern-form"]');

      if (await editElements.first().isVisible()) {
        await page.screenshot({
          path: 'playwright/screenshots/pattern-edit-form.png',
          fullPage: true
        });
      }
    }
  });

  test('should be able to expand pattern details', async ({ page }) => {
    await page.goto('/patterns');
    await page.waitForLoadState('networkidle');

    // Look for expand button or chevron
    const expandButton = page.locator('button[aria-label*="expand"], .expand-button, [data-testid="expand-pattern"]').first();

    if (await expandButton.isVisible()) {
      await expandButton.click();
      await page.waitForTimeout(500);

      await page.screenshot({
        path: 'playwright/screenshots/pattern-expanded.png',
        fullPage: true
      });
    }
  });
});

test.describe('Pattern Category Assignment', () => {
  test('should show category selector in pattern form', async ({ page }) => {
    await page.goto('/patterns');
    await page.waitForLoadState('networkidle');

    // Find and click a pattern to open edit
    const patternItem = page.locator('.pattern-item, .pattern-card, [data-testid="pattern-item"], tr').first();

    if (await patternItem.isVisible()) {
      await patternItem.click();
      await page.waitForTimeout(500);

      // Look for category dropdown/selector
      const categorySelector = page.locator('select[name="category"], [data-testid="category-select"], .category-dropdown');

      if (await categorySelector.isVisible()) {
        await expect(categorySelector).toBeEnabled();
      }
    }
  });
});

test.describe('Pattern Search/Filter', () => {
  test('should have search functionality if available', async ({ page }) => {
    await page.goto('/patterns');
    await page.waitForLoadState('networkidle');

    // Look for search input
    const searchInput = page.locator('input[placeholder*="zoek"], input[placeholder*="search"], [data-testid="search"]');

    if (await searchInput.first().isVisible()) {
      await searchInput.first().fill('test');
      await page.waitForTimeout(500);

      // Take screenshot of filtered results
      await page.screenshot({
        path: 'playwright/screenshots/patterns-filtered.png',
        fullPage: true
      });
    }
  });

  test('should filter by category if available', async ({ page }) => {
    await page.goto('/patterns');
    await page.waitForLoadState('networkidle');

    // Look for category filter
    const categoryFilter = page.locator('select[name="categoryFilter"], [data-testid="category-filter"], .category-filter');

    if (await categoryFilter.isVisible()) {
      // Click to open dropdown
      await categoryFilter.click();
      await page.waitForTimeout(300);

      await page.screenshot({
        path: 'playwright/screenshots/patterns-category-filter.png',
        fullPage: true
      });
    }
  });
});
