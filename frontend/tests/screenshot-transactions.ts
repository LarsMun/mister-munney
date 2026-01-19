import { chromium } from '@playwright/test';
import * as path from 'path';

/**
 * Simple script to take a screenshot of the transactions page
 */
async function takeScreenshot() {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    // Use the saved authentication state
    storageState: path.join(__dirname, '../playwright/.auth/user.json')
  });
  const page = await context.newPage();

  try {
    // Navigate to transactions page
    await page.goto('http://localhost:5173/transactions');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Wait a bit for any animations
    await page.waitForTimeout(1000);

    // Take screenshot
    const screenshotPath = path.join(__dirname, '../playwright/screenshots/transactions-page.png');
    await page.screenshot({
      path: screenshotPath,
      fullPage: true
    });

    console.log(`✓ Screenshot saved to: ${screenshotPath}`);
  } catch (error) {
    console.error('Error taking screenshot:', error);
  } finally {
    await browser.close();
  }
}

takeScreenshot();
