/**
 * Walking a guest through Sylius' checkout.
 *
 * Which steps a checkout takes is part of what the specs check: a cart without anything to ship skips the shipping
 * step, and a cart the applied gift cards cover in full skips the payment step. So the walk follows wherever the shop
 * sends it and reports the steps it went through, instead of expecting a fixed sequence.
 */

const { expect } = require('@playwright/test');
const { clickAndWaitForPage } = require('./navigation');
const { shopPath } = require('./shop');

const BILLING = 'sylius_checkout_address[billingAddress]';
const SHIPPING_METHOD = 'input[name^="sylius_checkout_select_shipping[shipments]"][name$="[method]"]';

/**
 * Fills in the address step for a guest and submits it
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} email
 * @param {string} country
 */
async function submitAddress(page, email, country) {
    await page.goto(await shopPath(page, 'checkout/address'));

    // Once an address has been submitted the order has a customer, and the step stops asking for the email
    const emailField = page.locator('[name="sylius_checkout_address[customer][email]"]');
    if (0 < (await emailField.count())) {
        await emailField.fill(email);
    }
    await page.locator(`[name="${BILLING}[firstName]"]`).fill('Gift');
    await page.locator(`[name="${BILLING}[lastName]"]`).fill('Tester');
    await page.locator(`[name="${BILLING}[street]"]`).fill('1 Main Street');
    await page.locator(`select[name="${BILLING}[countryCode]"]`).selectOption(country);
    await page.locator(`[name="${BILLING}[city]"]`).fill('Springfield');
    await page.locator(`[name="${BILLING}[postcode]"]`).fill('12345');

    // An address the shop refuses renders the step again, so the next page is waited for, not a URL
    await clickAndWaitForPage(page, page.locator('#next-step'));
}

/**
 * Takes the cart through the checkout as a guest, up to the complete step, choosing whatever the shop proposes.
 *
 * @param {import('@playwright/test').Page} page a page whose session holds the cart
 * @param {string} email the guest's email; unique per spec, so the order can be found again in the admin
 * @returns {Promise<{shipping: boolean, payment: boolean}>} whether the checkout asked for a shipping method and for a
 *          payment method
 */
async function checkOutAsGuest(page, email) {
    await page.goto(await shopPath(page, 'checkout/address'));
    const countries = await page
        .locator(`select[name="${BILLING}[countryCode]"] option[value]:not([value=""])`)
        .evaluateAll((options) => options.map((option) => option.value));
    expect(countries.length, 'the channel offers no country to ship to').toBeGreaterThan(0);

    const steps = { shipping: false, payment: false };

    // Sylius' fixtures give every shipping method a random zone, so no country is sure to be shipped to. Try the
    // offered countries in turn until the shipping step lists a method, or the cart turns out to need no shipping
    let addressed = false;
    for (const country of countries) {
        await submitAddress(page, email, country);

        const step = new URL(page.url()).pathname;
        if (step.endsWith('/checkout/address')) {
            // the shop refused the address in this country (one that requires a province, say), so try the next
            continue;
        }
        if (!step.endsWith('/checkout/select-shipping')) {
            addressed = true;
            break;
        }

        steps.shipping = true;
        // the method choices, not the form's hidden token, which is there even when nothing ships to the address
        if (0 < (await page.locator(SHIPPING_METHOD).count())) {
            await clickAndWaitForPage(page, page.locator('#next-step'));
            addressed = true;
            break;
        }
    }
    expect(addressed, `no seeded shipping method ships to any of the countries the channel offers (${countries.join(', ')})`).toBe(true);

    if (page.url().includes('/checkout/select-payment')) {
        steps.payment = true;
        // the shop preselects a method, which is as good as any other here
        await clickAndWaitForPage(page, page.locator('#next-step'));
    }

    await expect(page, 'the checkout should have reached the complete step').toHaveURL(/\/checkout\/complete$/);

    return steps;
}

/**
 * Places the order from the complete step
 *
 * @param {import('@playwright/test').Page} page
 */
async function placeOrder(page) {
    await clickAndWaitForPage(page, page.locator('form[name="sylius_checkout_complete"] button[type="submit"]').first());

    await expect(page, 'placing the order should have led to the thank you page').toHaveURL(/\/order\/thank-you$/);
}

/**
 * An email address nobody else checks out with, so the order it places can be found in the admin
 *
 * @param {string} purpose
 */
function uniqueEmail(purpose) {
    return `${purpose}-${Date.now()}-${Math.floor(Math.random() * 1e6)}@example.com`;
}

module.exports = { checkOutAsGuest, placeOrder, uniqueEmail };
