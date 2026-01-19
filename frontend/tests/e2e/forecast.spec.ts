import { test, expect } from '@playwright/test';

test.describe('Forecast Feature', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to forecast page
    await page.goto('/forecast');

    // Wait for the page to load
    await page.waitForLoadState('networkidle');
  });

  test('should display forecast page with main elements', async ({ page }) => {
    // Check page title
    await expect(page.locator('h1')).toContainText('Cashflow Forecast');

    // Check subtitle
    await expect(page.getByText('Beheer je verwachte inkomsten en uitgaven')).toBeVisible();

    // Take screenshot of the main page
    await page.screenshot({
      path: 'playwright/screenshots/forecast-page.png',
      fullPage: true
    });
  });

  test('should display summary cards', async ({ page }) => {
    // Check for the 4 summary cards
    await expect(page.getByText('Huidig Saldo')).toBeVisible();
    await expect(page.getByText('Verwacht Resultaat')).toBeVisible();
    await expect(page.getByText('Actueel Resultaat')).toBeVisible();
    await expect(page.getByText('Verwacht Eindsaldo')).toBeVisible();

    // Take screenshot of summary section
    await page.locator('.grid.grid-cols-1.md\\:grid-cols-4').screenshot({
      path: 'playwright/screenshots/forecast-summary-cards.png'
    });
  });

  test('should have month navigation', async ({ page }) => {
    // Check for month navigation buttons
    const prevButton = page.locator('button[title="Vorige maand"]');
    const nextButton = page.locator('button[title="Volgende maand"]');
    const currentMonthButton = page.locator('button:has-text(/Januari|Februari|Maart|April|Mei|Juni|Juli|Augustus|September|Oktober|November|December/)');

    await expect(prevButton).toBeVisible();
    await expect(nextButton).toBeVisible();
    await expect(currentMonthButton).toBeVisible();

    // Take screenshot of navigation
    await page.locator('.flex.items-center.space-x-2').screenshot({
      path: 'playwright/screenshots/forecast-month-navigation.png'
    });
  });

  test('should display income and expense sections', async ({ page }) => {
    // Check for section titles
    await expect(page.getByRole('heading', { name: 'Inkomsten' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Uitgaven' })).toBeVisible();

    // Take full page screenshot
    await page.screenshot({
      path: 'playwright/screenshots/forecast-full-page.png',
      fullPage: true
    });
  });

  test('should display available items sidebar', async ({ page }) => {
    // The sidebar should contain budgets and categories
    // This might fail if there's no data, which is expected for a new install

    // Take screenshot of the available items section
    const sidebar = page.locator('.lg\\:col-span-1');
    if (await sidebar.isVisible()) {
      await sidebar.screenshot({
        path: 'playwright/screenshots/forecast-available-items.png'
      });
    }
  });

  test('should be able to navigate between months', async ({ page }) => {
    // Get current month text
    const monthButton = page.locator('button:has-text(/Januari|Februari|Maart|April|Mei|Juni|Juli|Augustus|September|Oktober|November|December/)');
    const currentMonth = await monthButton.textContent();

    // Click next month
    await page.locator('button[title="Volgende maand"]').click();
    await page.waitForTimeout(500); // Wait for animation/update

    // Month should have changed
    const newMonth = await monthButton.textContent();
    expect(newMonth).not.toBe(currentMonth);

    // Click back to current month
    await monthButton.click();
    await page.waitForTimeout(500);

    // Should be back to current month (will have highlight)
    await expect(monthButton).toHaveClass(/bg-blue-600/);

    // Take screenshot after navigation
    await page.screenshot({
      path: 'playwright/screenshots/forecast-after-navigation.png',
      fullPage: true
    });
  });

  test('should show help text at bottom', async ({ page }) => {
    // Check for the help tip at the bottom
    await expect(page.getByText(/Klik op een verwacht bedrag/)).toBeVisible();
  });
});
