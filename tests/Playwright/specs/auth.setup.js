const { test: setup, expect } = require('@playwright/test');
const fs = require('node:fs');
const { ADMIN_STORAGE_STATE } = require('./support/paths');

/**
 * Signs in once and stores the session, so the admin specs do not each pay for a login.
 *
 * The stored state is verified here rather than trusted: if it comes out without a session cookie, every
 * admin spec silently ends up on the login form and fails for reasons that look nothing like the cause.
 */
setup('authenticate as administrator', async ({ page }) => {
    await page.goto('/admin/login');

    await page.fill('input[name="_username"]', 'sylius');
    await page.fill('input[name="_password"]', 'sylius');
    await page.click('button[type="submit"]');

    await page.waitForURL('**/admin/');
    await expect(page.locator('input[name="_username"]')).toHaveCount(0);

    const state = await page.context().storageState({ path: ADMIN_STORAGE_STATE });

    expect(state.cookies.length, 'signing in produced no cookies to reuse').toBeGreaterThan(0);
    expect(fs.existsSync(ADMIN_STORAGE_STATE), `${ADMIN_STORAGE_STATE} was not written`).toBe(true);

    // Prove the saved state actually authenticates, instead of finding out once every admin spec fails
    const verifier = await page.context().browser().newContext({ storageState: ADMIN_STORAGE_STATE });
    const verifierPage = await verifier.newPage();
    const response = await verifierPage.goto('/admin/');
    expect(response?.url(), 'the stored session does not authenticate').not.toContain('/admin/login');
    await verifier.close();
});
