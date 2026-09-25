/**
 * Helpers for the admin's gift card pages: issuing a card through the form, and reading a card's details, its ledger
 * and its row in the grid back.
 *
 * Specs that change a card issue one of their own rather than borrow a seeded one, so they neither depend on what the
 * fixtures generated nor leave a card behind that other specs expect to find untouched.
 */

const { expect } = require('@playwright/test');
const { setChecked } = require('./admin');
const { channelBaseCurrencyCode } = require('./fixtures');
const { moneyInCents, typedAmount } = require('./money');
const { clickAndWaitForPage } = require('./navigation');

const FORM = 'form[name="setono_sylius_gift_card_gift_card"]';

/** @type {Map<string, string>} */
const baseCurrencies = new Map();

/**
 * Picks a customer in the gift card form's customer autocomplete, the way an admin does: typing part of the email and
 * choosing the match the search endpoint returns.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} email
 */
async function pickCustomer(page, email) {
    const autocomplete = page.locator(`${FORM} .sylius-autocomplete`).filter({ has: page.locator('[name$="[customer]"]') });

    // The part before the @ is what an admin remembers; the dropdown asks the plugin's search endpoint for it
    const phrase = email.split('@')[0];
    const [search] = await Promise.all([
        page.waitForResponse((response) => response.url().includes('/customer/search') && response.url().includes('phrase=')),
        autocomplete.locator('input.search').fill(phrase),
    ]);
    expect(search.status(), 'the customer search endpoint failed').toBe(200);

    const match = autocomplete.locator('.menu .item').filter({ hasText: email }).first();
    await expect(match, `the customer search did not offer ${email}`).toBeVisible();
    await match.click();
    await expect(page.locator(`${FORM} [name$="[customer]"]`)).toHaveValue(email);
}

/**
 * Issues a gift card through the admin's create form and returns it. The card is issued in the base currency of the
 * channel the form proposes, the only currency a card may be issued in.
 *
 * @param {import('@playwright/test').Page} page an authenticated admin page
 * @param {{amount: number, enabled?: boolean, customerEmail?: string|null, customMessage?: string|null, expiresAt?: string|null}} card
 *        amount in minor units; expiresAt as YYYY-MM-DD
 * @returns {Promise<{id: string, code: string, printedCode: string, currency: string}>} the code as stored, without
 *          separators, and as printed (the show page, the grid, the PDF and the emails group it, e.g. ABCD-EFGH-JKMN-PQRS)
 */
async function issueGiftCard(page, { amount, enabled = true, customerEmail = null, customMessage = null, expiresAt = null }) {
    await page.goto('/admin/gift-cards/new');
    const channel = await page.locator(`${FORM} select[name$="[channel]"]`).inputValue();
    if (!baseCurrencies.has(channel)) {
        baseCurrencies.set(channel, await channelBaseCurrencyCode(page, channel));
        await page.goto('/admin/gift-cards/new');
    }

    const currency = /** @type {string} */ (baseCurrencies.get(channel));
    await page.locator(`${FORM} select[name$="[currencyCode]"]`).selectOption(currency);
    await page.locator(`${FORM} [name$="[amount]"]`).fill(typedAmount(amount));
    await setChecked(page.locator(`${FORM} [name$="[enabled]"]`), enabled);
    // Nobody reads the email in the suite, and a card without a customer has nobody to send it to anyway
    await setChecked(page.locator(`${FORM} [name$="[sendNotificationEmail]"]`), false);

    if (null !== customerEmail) {
        await pickCustomer(page, customerEmail);
    }
    if (null !== customMessage) {
        await page.locator(`${FORM} [name$="[customMessage]"]`).fill(customMessage);
    }
    if (null !== expiresAt) {
        await page.locator(`${FORM} [name$="[expiresAt]"]`).fill(expiresAt);
    }

    await clickAndWaitForPage(page, page.locator(`${FORM} button[type="submit"]`).first());

    const errors = await page.locator(`${FORM} .sylius-validation-error`).allInnerTexts();
    expect(errors, 'the gift card form refused the card').toEqual([]);

    // Creating redirects to the index naming the new card's id, or to a page of the card itself; either names the id
    const landed = new URL(page.url());
    const id = landed.searchParams.get('id') ?? /\/admin\/gift-cards\/(\d+)(?:\/edit)?$/.exec(landed.pathname)?.[1] ?? null;
    expect(id, `issuing the gift card should have led to a page naming it, not ${page.url()}`).toMatch(/^\d+$/);

    // The code is generated when the card is saved, so it is read back from the card rather than from the form
    const printedCode = (await giftCardDetails(page, id)).Code;
    const code = printedCode.replace(/-/g, '');

    return { id, code, printedCode, currency };
}

/**
 * The details table of a card's show page, keyed by the label of each row, e.g. `details.Amount`
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} id
 * @returns {Promise<Record<string, string>>}
 */
async function giftCardDetails(page, id) {
    await page.goto(`/admin/gift-cards/${id}`);

    const rows = page.locator('table').first().locator('tbody tr');
    const details = {};
    for (const row of await rows.all()) {
        const cells = await row.locator('td').allInnerTexts();
        details[cells[0].trim()] = (cells[1] ?? '').replace(/\s+/g, ' ').trim();
    }

    return details;
}

/**
 * The card's ledger as its show page lists it, oldest first
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} id
 * @returns {Promise<Array<{type: string, amount: number, reason: string, order: string|null, orderHref: string|null}>>}
 */
async function giftCardTransactions(page, id) {
    await page.goto(`/admin/gift-cards/${id}`);

    const table = page.locator('table').filter({ has: page.locator('thead') });
    const transactions = [];
    for (const row of await table.locator('tbody tr').all()) {
        const cells = await row.locator('td').allInnerTexts();
        const orderLink = row.locator('td').nth(4).locator('a');
        const hasOrder = 0 < (await orderLink.count());

        transactions.push({
            type: cells[1].trim(),
            amount: moneyInCents(cells[2]),
            reason: cells[3].trim(),
            order: hasOrder ? (await orderLink.innerText()).trim() : null,
            orderHref: hasOrder ? await orderLink.getAttribute('href') : null,
        });
    }

    return transactions;
}

/**
 * The rows of the gift card grid that mention the given text, e.g. a customer's email or a card's printed code (the
 * grid shows a code grouped, like the show page). New cards come first, as the grid lists the most recently created at
 * the top.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} text
 */
async function giftCardRows(page, text) {
    await page.goto('/admin/gift-cards/');

    return page.locator('table tbody tr').filter({ hasText: text });
}

/**
 * The ids of the cards in the given grid rows, taken from their show links
 *
 * @param {import('@playwright/test').Locator} rows
 * @returns {Promise<string[]>}
 */
async function giftCardIds(rows) {
    const hrefs = await rows.locator('a').evaluateAll((links) => links.map((link) => link.getAttribute('href') ?? ''));

    return [...new Set(hrefs.map((href) => /^\/admin\/gift-cards\/(\d+)$/.exec(href)?.[1]).filter((id) => undefined !== id))];
}

module.exports = { giftCardDetails, giftCardIds, giftCardRows, giftCardTransactions, issueGiftCard, pickCustomer };
