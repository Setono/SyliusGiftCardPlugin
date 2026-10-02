/**
 * Helpers for Sylius' payment methods in the admin.
 */

const { expect } = require('@playwright/test');
const { clickAndWaitForPage } = require('./navigation');

const FORM = 'form[name="sylius_payment_method"]';
const INSTRUCTIONS = `${FORM} textarea[name$="[instructions]"]`;

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
    await page.goto('/admin/payment-methods/');
    const row = page.locator('table tbody tr').filter({ has: page.getByRole('cell', { name: code, exact: true }) });
    await expect(row, `the admin lists no payment method ${code}`).toHaveCount(1);

    const url = await row.locator('a[href$="/edit"]').first().getAttribute('href');
    expect(url, `the payment method ${code} has no edit link`).toMatch(/\/edit$/);

    await page.goto(/** @type {string} */ (url));
    const previous = await page.locator(INSTRUCTIONS).evaluateAll((fields) => fields.map((field) => field.value));
    await saveInstructions(page, previous.map(() => instructions));

    return async () => {
        await page.goto(/** @type {string} */ (url));
        await saveInstructions(page, previous);
    };
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

module.exports = { givePaymentMethodInstructions };
