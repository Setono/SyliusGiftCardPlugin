/**
 * Helpers for a customer with an account in the shop: creating one and signing in with it, and finding an order among
 * the customer's orders.
 *
 * The seeded channel requires a new account to be verified by email before it can sign in, and nobody reads the email
 * in the suite, so the account is created by an administrator instead, the way a shop sets one up for a customer.
 */

const { expect } = require('@playwright/test');
const { setChecked } = require('./admin');
const { clickAndWaitForPage } = require('./navigation');
const { shopPath } = require('./shop');

const FORM = 'form[name="sylius_customer"]';

/**
 * Creates a customer who can sign in to the shop with the given email and password
 *
 * @param {import('@playwright/test').Page} page an authenticated admin page
 * @param {string} email
 * @param {string} password
 */
async function createCustomerAccount(page, email, password) {
    await page.goto('/admin/customers/new');

    await page.locator(`${FORM} [name="sylius_customer[firstName]"]`).fill('Gift');
    await page.locator(`${FORM} [name="sylius_customer[lastName]"]`).fill('Customer');
    await page.locator(`${FORM} [name="sylius_customer[email]"]`).fill(email);
    await setChecked(page.locator(`${FORM} [name="sylius_customer[createUser]"]`), true);
    await page.locator(`${FORM} [name="sylius_customer[user][plainPassword]"]`).fill(password);
    await setChecked(page.locator(`${FORM} [name="sylius_customer[user][enabled]"]`), true);

    await clickAndWaitForPage(page, page.locator(`${FORM} button[type="submit"]`).first());

    const errors = await page.locator(`${FORM} .sylius-validation-error`).allInnerTexts();
    expect(errors, 'the customer form refused the customer').toEqual([]);
    await expect(page, 'creating the customer should have led to the customer').toHaveURL(/\/admin\/customers\/\d+/);
}

/**
 * Signs in to the shop through its login form
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} email
 * @param {string} password
 */
async function signInToShop(page, email, password) {
    await page.goto(await shopPath(page, 'login'));

    const form = page.locator('form').filter({ has: page.locator('[name="_password"]') });
    await form.locator('[name="_username"]').fill(email);
    await form.locator('[name="_password"]').fill(password);
    await clickAndWaitForPage(page, form.locator('button[type="submit"]').first());

    // Signed in, the account's pages open instead of sending the customer back to the login form
    await page.goto(await shopPath(page, 'account/dashboard'));
    await expect(page, `${email} could not sign in to the shop`).not.toHaveURL(/\/login$/);
}

/**
 * The row of the order with the given number in the grid of the customer's orders
 *
 * @param {import('@playwright/test').Page} page a page signed in as the customer
 * @param {string} number
 */
async function accountOrderRow(page, number) {
    await page.goto(await shopPath(page, 'account/orders/'));

    const row = page.locator('table tbody tr').filter({ hasText: `#${number}` });
    await expect(row, `the customer's orders should list #${number}`).toHaveCount(1);

    return row;
}

module.exports = { accountOrderRow, createCustomerAccount, signInToShop };
