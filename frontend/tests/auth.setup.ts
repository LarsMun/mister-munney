import { test as setup, expect } from '@playwright/test';
import * as path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(__dirname, '../playwright/.auth/user.json');

/**
 * Authentication setup that runs before all tests.
 * Logs in once and saves the authentication state (JWT token in localStorage).
 * All subsequent tests will reuse this authentication state.
 */
setup('authenticate', async ({ page }) => {
  // Navigate to the app
  await page.goto('http://localhost:5173');

  // Wait for auth screen to load
  await page.waitForSelector('input[type="email"]', { timeout: 10000 });

  // Fill in login credentials
  // TODO: Update these credentials or create a test user in the database
  await page.fill('input[type="email"]', 'test@test.com');
  await page.fill('input[type="password"]', 'test');

  // Click login button
  await page.click('button[type="submit"]:has-text("Inloggen")');

  // Wait for successful login - the URL should change or JWT should be in localStorage
  await page.waitForFunction(
    () => localStorage.getItem('token') !== null,
    { timeout: 10000 }
  );

  // Verify we're logged in by checking if we can see the dashboard
  await expect(page.locator('img[alt="Mister Munney"]')).toBeVisible();

  // Save the authentication state (includes localStorage with JWT token)
  await page.context().storageState({ path: authFile });

  console.log('✓ Authentication setup complete - state saved to', authFile);
});
