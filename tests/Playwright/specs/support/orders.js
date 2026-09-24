/**
 * Helpers for the admin's order pages, where a shop journey ends: finding the order a customer placed, reading its
 * payments, and moving it on (completing a payment, cancelling the order) the way an administrator does.
 */

const { expect } = require('@playwright/test');
const { clickAndConfirm } = require('./admin');
const { clickAndWaitForPage } = require('./navigation');
const { moneyInCents } = require('./money');

/**
 * Opens the order the customer with the given email placed. The specs check out with an email of their own, so the
 * email picks exactly one order.
 *
 * @param {import('@playwright/test').Page} page an authenticated admin page
 * @param {string} email
 * @returns {Promise<{url: string, number: string}>}
 */
async function openOrderOf(page, email) {
    await page.goto('/admin/orders/');

    // Through the filter form: Sylius' order grid needs every one of its criteria in the query, so a hand built
    // query string with only the customer in it fails
    const filter = page.locator('input[name="criteria[customer][value]"]');
    await filter.fill(email);
    await Promise.all([page.waitForURL(/criteria/), filter.press('Enter')]);

    const rows = page.locator('table tbody tr').filter({ hasText: email });
    await expect(rows, `exactly one order should have been placed by ${email}`).toHaveCount(1);

    const url = await rows.locator('a[href^="/admin/orders/"]').first().getAttribute('href');
    expect(url, 'the order row links to no order').toMatch(/^\/admin\/orders\/\d+$/);

    await page.goto(url);
    // the page is titled after the order number, which the ledger of a gift card links the order by
    const number = (/#\s*(\d+)/.exec(await page.title()) ?? [])[1] ?? '';
    expect(number, 'the order page does not name the order number').toMatch(/^\d+$/);

    return { url, number };
}

/**
 * The payments of the order that is open, as the admin order page lists them
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<Array<{method: string, amount: number, state: string}>>}
 */
async function orderPayments(page) {
    const payments = [];
    for (const item of await page.locator('#sylius-payments > .item').all()) {
        payments.push({
            method: (await item.locator('.content .header').first().innerText()).trim(),
            amount: moneyInCents(await item.locator('.content .description').first().innerText()),
            state: (await item.locator('.right.floated .label').first().innerText()).trim(),
        });
    }

    return payments;
}

/**
 * Marks every payment of the open order that still waits for money as completed, the way an administrator records a
 * bank transfer or a payment on delivery
 *
 * @param {import('@playwright/test').Page} page
 */
async function completeOrderPayments(page) {
    const completeButtons = page.locator('#sylius-payments form[action$="/complete"] button[type="submit"]');
    expect(await completeButtons.count(), 'the order has no payment to complete').toBeGreaterThan(0);

    while (0 < (await completeButtons.count())) {
        await clickAndWaitForPage(page, completeButtons.first());
    }
}

/**
 * Cancels the open order through the button on its page
 *
 * @param {import('@playwright/test').Page} page
 */
async function cancelOrder(page) {
    const cancel = page.locator('form[action*="/cancel"] button[type="submit"]');
    await expect(cancel, 'the order can no longer be cancelled').toHaveCount(1);

    await clickAndConfirm(page, cancel);
}

module.exports = { cancelOrder, completeOrderPayments, openOrderOf, orderPayments };
