/**
 * Helpers for Sylius' payment methods in the admin.
 */

const { expect } = require('@playwright/test');
const { GRID_ROWS, setChecked } = require('./admin');
const { clickAndWaitForPage } = require('./navigation');

const FORM = 'form[name="sylius_payment_method"]';
const INSTRUCTIONS = `${FORM} textarea[name$="[instructions]"]`;
const ENABLED = `${FORM} input[name="sylius_payment_method[enabled]"]`;

/**
 * The code of the payment method gift card payments are made with: the redemption.payment_method_code setting, which the
 * test application leaves at gift_card, and which the plugin's fixture gives the method it seeds
 */
const GIFT_CARD_PAYMENT_METHOD_CODE = 'gift_card';

/**
 * Gives the payment method of the given code the given instructions, in every locale, so the shop shows them whichever
 * locale it is browsed in. The seeded payment methods have none.
 *
 * Every spec of a shard shares one database, so the instructions are taken away again with the function this returns,
 * which puts back what the method had before
 *
 * @param {import('@playwright/test').Page} page an authenticated admin page
 * @param {string} code
 * @param {string} instructions
 * @returns {Promise<() => Promise<void>>}
 */
async function givePaymentMethodInstructions(page, code, instructions) {
    const url = await paymentMethodEditUrl(page, code);

    await page.goto(url);
    const previous = await page.locator(INSTRUCTIONS).evaluateAll((fields) => fields.map((field) => field.value));
    await saveInstructions(page, previous.map(() => instructions));

    return async () => {
        await page.goto(url);
        await saveInstructions(page, previous);
    };
}

/**
 * Enables or disables the payment method of the given code through its edit form.
 *
 * Every spec of a shard shares one database, so the state is put back with the function this returns, which gives the
 * method the state it had before
 *
 * @param {import('@playwright/test').Page} page an authenticated admin page
 * @param {string} code
 * @param {boolean} enabled
 * @returns {Promise<() => Promise<void>>}
 */
async function setPaymentMethodEnabled(page, code, enabled) {
    const url = await paymentMethodEditUrl(page, code);

    await page.goto(url);
    const previous = await page.locator(ENABLED).isChecked();
    await saveEnabled(page, enabled);

    return async () => {
        await page.goto(url);
        await saveEnabled(page, previous);
    };
}

/**
 * Ticks or unticks Enabled on the payment method whose form is open, and saves it
 *
 * @param {import('@playwright/test').Page} page
 * @param {boolean} enabled
 */
async function saveEnabled(page, enabled) {
    await setChecked(page.locator(ENABLED), enabled);
    await clickAndWaitForPage(page, page.locator(`${FORM} button[type="submit"]`).first());
    await expect(page.locator(ENABLED), 'saving the payment method should have kept the state').toBeChecked({ checked: enabled });
}

/**
 * The edit page of the payment method of the given code, found through Sylius' payment methods grid
 *
 * @param {import('@playwright/test').Page} page an authenticated admin page
 * @param {string} code
 * @returns {Promise<string>}
 */
async function paymentMethodEditUrl(page, code) {
    await page.goto('/admin/payment-methods/');
    const row = page.locator(GRID_ROWS).filter({ has: page.getByRole('cell', { name: code, exact: true }) });
    await expect(row, `the admin lists no payment method ${code}`).toHaveCount(1);

    return editUrlOf(row, `the payment method ${code}`);
}

/**
 * The edit page of a payment method other than the one of the given code, the first one Sylius' payment methods grid
 * lists
 *
 * @param {import('@playwright/test').Page} page an authenticated admin page
 * @param {string} code
 * @returns {Promise<string>}
 */
async function otherPaymentMethodEditUrl(page, code) {
    await page.goto('/admin/payment-methods/');
    const row = page.locator(GRID_ROWS).filter({ hasNot: page.getByRole('cell', { name: code, exact: true }) }).first();
    await expect(row, `the admin lists no payment method but ${code}`).toHaveCount(1);

    return editUrlOf(row, `the first payment method that is not ${code}`);
}

/**
 * @param {import('@playwright/test').Locator} row a row of the payment methods grid
 * @param {string} description
 * @returns {Promise<string>}
 */
async function editUrlOf(row, description) {
    const url = await row.locator('a[href$="/edit"]').first().getAttribute('href');
    expect(url, `${description} has no edit link`).toMatch(/\/edit$/);

    return /** @type {string} */ (url);
}

/**
 * Fills the instructions of the payment method whose form is open, one value per locale in the order of the form, and
 * saves it
 *
 * @param {import('@playwright/test').Page} page
 * @param {string[]} values
 */
async function saveInstructions(page, values) {
    const fields = page.locator(INSTRUCTIONS);
    await expect(fields).toHaveCount(values.length);

    // The form folds every locale but the first one away, so the fields are filled without being shown
    await fields.evaluateAll((textareas, texts) => {
        textareas.forEach((textarea, index) => {
            textarea.value = texts[index];
        });
    }, values);

    await clickAndWaitForPage(page, page.locator(`${FORM} button[type="submit"]`).first());
    expect(await page.locator(INSTRUCTIONS).evaluateAll((textareas) => textareas.map((textarea) => textarea.value))).toEqual(values);
}

module.exports = {
    GIFT_CARD_PAYMENT_METHOD_CODE,
    givePaymentMethodInstructions,
    otherPaymentMethodEditUrl,
    paymentMethodEditUrl,
    saveEnabled,
    setPaymentMethodEnabled,
};
