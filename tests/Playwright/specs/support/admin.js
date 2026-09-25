/**
 * Helpers for driving Sylius' admin: signing in from a spec that is not signed in already, and the few widgets every
 * admin form and grid shares.
 */

const { expect } = require('@playwright/test');
const { clickAndWaitForPage } = require('./navigation');

/**
 * An administrator signed in through the login form, in a browser context of its own.
 *
 * A customer journey in the shop often ends in the admin (a payment is completed, an order cancelled, a card looked
 * up). The shop specs run anonymously and without the setup project, so they sign in here rather than reuse the
 * session it stores, and the customer's session (and with it the cart) stays apart from the administrator's.
 *
 * @param {import('@playwright/test').Browser} browser
 * @returns {Promise<{page: import('@playwright/test').Page, close: () => Promise<void>}>}
 */
async function signInAsAdministrator(browser) {
    const context = await browser.newContext();

    // Every admin page asks Sylius whether a newer version exists. That request has been seen leaving without the
    // session cookie right after signing in; it is then redirected to the login form, whose fresh session cookie
    // replaces the signed in one and logs the administrator out halfway through a spec. The answer only feeds the
    // "new version available" bell, so it is answered here without asking the application
    await context.route('**/admin/ajax/get-version', (route) => route.fulfill({ json: {} }));

    const page = await context.newPage();

    await page.goto('/admin/login');
    await page.fill('input[name="_username"]', 'sylius');
    await page.fill('input[name="_password"]', 'sylius');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin/');
    await page.waitForLoadState('networkidle');

    // Proven rather than assumed, so a session that did not survive shows up here and not as a failure elsewhere
    await page.goto('/admin/gift-cards/');
    await expect(page.locator('input[name="_username"]'), 'the administrator session did not survive signing in').toHaveCount(0);

    return { page, close: () => context.close() };
}

/**
 * Semantic UI lays its own label over a checkbox and toggles the input itself when that label is clicked, so a click
 * on the input races the label's handler; the state is set on the input instead
 *
 * @param {import('@playwright/test').Locator} checkbox
 * @param {boolean} checked
 */
async function setChecked(checkbox, checked) {
    await checkbox.evaluate((input, value) => {
        input.checked = value;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }, checked);

    await expect(checkbox).toBeChecked({ checked });
}

/**
 * Clicks a button that asks for confirmation through the admin's modal (Sylius' delete and state transition buttons,
 * and the plugin's POST actions), confirms, and waits for the page the action leads to.
 *
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').Locator} button
 */
async function clickAndConfirm(page, button) {
    await button.click();
    await expect(page.locator('#confirmation-modal')).toBeVisible();

    await clickAndWaitForPage(page, page.locator('#confirmation-button'));
}

/**
 * The flash messages the page shows, as one string per message
 *
 * @param {import('@playwright/test').Page} page
 */
async function flashMessages(page) {
    return (await page.locator('.sylius-flash-message').allInnerTexts()).map((text) => text.replace(/\s+/g, ' ').trim());
}

module.exports = { clickAndConfirm, flashMessages, setChecked, signInAsAdministrator };
