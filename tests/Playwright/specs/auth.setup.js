const { test: setup, expect } = require('@playwright/test');

const ADMIN_STATE = '.auth/admin.json';

/**
 * Signs in once and stores the session, so the admin specs do not each pay for a login.
 */
setup('authenticate as administrator', async ({ page }) => {
    await page.goto('/admin/login');

    await page.fill('input[name="_username"]', 'sylius');
    await page.fill('input[name="_password"]', 'sylius');
    await page.click('button[type="submit"]');

    await page.waitForURL('**/admin/');
    // Guard against a redirect back to the login form being mistaken for a successful sign in
    await expect(page.locator('input[name="_username"]')).toHaveCount(0);

    await page.context().storageState({ path: ADMIN_STATE });
});
