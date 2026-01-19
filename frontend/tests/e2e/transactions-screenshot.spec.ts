import { test } from '@playwright/test';

test('take screenshot of transactions page', async ({ page }) => {
  // Navigate to transactions page
  await page.goto('/transactions');

  // Wait for page to load
  await page.waitForLoadState('networkidle');

  // Wait a bit for any animations/data to load
  await page.waitForTimeout(1000);

  // Take full page screenshot
  await page.screenshot({
    path: 'playwright/screenshots/transactions-page.png',
    fullPage: true
  });

  console.log('✓ Screenshot saved to playwright/screenshots/transactions-page.png');
});
