const { test, expect } = require('@playwright/test');
const { clickAndConfirm, flashMessages, setChecked } = require('../support/admin');
const { giftCardDetails, giftCardRows, giftCardTransactions, issueGiftCard } = require('../support/gift-cards');
const { moneyInCents, typedAmount } = require('../support/money');
const { clickAndWaitForPage } = require('../support/navigation');

/**
 * Issuing, changing and removing gift cards in the admin.
 *
 * Every spec issues the card it works on, so it knows the card's balance and history exactly, and the cards the
 * fixtures seeded are left for the specs that only look at them.
 */

/**
 * The email of a customer the shop knows, read off the customers grid
 *
 * @param {import('@playwright/test').Page} page
 */
async function anyCustomerEmail(page) {
    await page.goto('/admin/customers/');

    const email = (await page.locator('table tbody tr td').allInnerTexts()).map((text) => text.trim()).find((text) => /^\S+@\S+$/.test(text));
    expect(email, 'the shop has no customer to issue a card to').toBeTruthy();

    return /** @type {string} */ (email);
}

/**
 * The outstanding balance report's figures per currency: how many usable cards there are and what they hold together
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<Map<string, {count: number, total: number}>>}
 */
async function outstandingBalances(page) {
    await page.goto('/admin/gift-cards/balance');

    const balances = new Map();
    for (const row of await page.locator('table tbody tr').all()) {
        const cells = await row.locator('td').allInnerTexts();
        if (4 === cells.length) {
            balances.set(cells[0].trim(), { count: Number(cells[1].trim()), total: moneyInCents(cells[2]) });
        }
    }

    return balances;
}

/**
 * Adjusts the balance through the adjust balance form and waits for the page it leads to
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} id
 * @param {number} amount in minor units, negative to deduct
 * @param {string} reason
 */
async function adjustBalance(page, id, amount, reason) {
    await page.goto(`/admin/gift-cards/${id}/adjust-balance`);
    await page.locator('input[name$="[amount]"]').fill(`${amount < 0 ? '-' : ''}${typedAmount(Math.abs(amount))}`);
    await page.locator('textarea[name$="[reason]"]').fill(reason);

    await clickAndWaitForPage(page, page.locator('form.ui.form button[type="submit"]').first());
    expect(await flashMessages(page)).toContainEqual(expect.stringMatching(/balance was adjusted/i));
}

test.describe('admin issuing gift cards', () => {
    /**
     * The customer field is an autocomplete backed by the plugin's own customer search endpoint, so this is the one
     * place that endpoint is exercised the way the admin uses it
     */
    test('a card issued to a customer picked through the autocomplete is listed with that customer', async ({ page }) => {
        const email = await anyCustomerEmail(page);

        const card = await issueGiftCard(page, { amount: 2500, customerEmail: email, customMessage: 'Thank you for your patience' });

        const details = await giftCardDetails(page, card.id);
        expect(details.Customer).toBe(email);
        expect(details['Custom message']).toBe('Thank you for your patience');
        expect(details.Enabled).toBe('Enabled');
        expect(moneyInCents(details.Amount)).toBe(2500);
        expect(moneyInCents(details['Initial amount'])).toBe(2500);
        // the customer row leads to the customer
        await expect(page.locator('table').first().locator('a', { hasText: email })).toHaveAttribute('href', /\/admin\/customers\/\d+$/);

        // The grid mails the customer from the email and opens the customer from the icon next to it
        const row = await giftCardRows(page, card.printedCode);
        await expect(row).toHaveCount(1);
        await expect(row.locator(`a[href="mailto:${email}"]`)).toHaveCount(1);
        await expect(row.getByRole('link', { name: `Open customer ${email} in a new tab` })).toHaveAttribute('href', /\/admin\/customers\/\d+$/);

        // Editing loads the chosen customer back into the autocomplete, so saving does not drop it
        await page.goto(`/admin/gift-cards/${card.id}/edit`);
        await expect(page.locator('[name$="[customer]"]')).toHaveValue(email);
        await expect(page.locator('.sylius-autocomplete').filter({ has: page.locator('[name$="[customer]"]') }).locator('> .text')).toHaveText(email);
    });

    test('an issued card records its opening balance and counts toward the outstanding balance', async ({ page }) => {
        const amount = 4321;

        const before = await outstandingBalances(page);
        const card = await issueGiftCard(page, { amount });

        expect((await giftCardTransactions(page, card.id)).map(({ type, amount: moved }) => [type, moved])).toEqual([['Issued', amount]]);

        // The report groups by currency, and the card was issued in the channel's base currency
        const { count, total } = before.get(card.currency) ?? { count: 0, total: 0 };
        expect((await outstandingBalances(page)).get(card.currency)).toEqual({ count: count + 1, total: total + amount });
    });

    /**
     * The balance and the currency are the ledger's business, so editing an issued card may change everything but
     * those two
     */
    test('editing a card saves what may be changed and leaves the balance alone', async ({ page }) => {
        const card = await issueGiftCard(page, { amount: 5000, customMessage: 'Before' });

        await page.goto(`/admin/gift-cards/${card.id}/edit`);
        await page.locator('[name$="[customMessage]"]').fill('After');
        await page.locator('[name$="[expiresAt]"]').fill('2031-01-31');
        await setChecked(page.locator('[name$="[enabled]"]'), false);
        await clickAndWaitForPage(page, page.locator('form[name="setono_sylius_gift_card_gift_card"] button[type="submit"]').first());
        expect(await flashMessages(page)).toContainEqual(expect.stringMatching(/successfully updated/i));

        const details = await giftCardDetails(page, card.id);
        expect(details['Custom message']).toBe('After');
        expect(details['Expires at']).toBe('2031-01-31');
        expect(details.Enabled).toBe('Disabled');
        expect(moneyInCents(details.Amount)).toBe(5000);
        // editing is no movement of the balance
        expect((await giftCardTransactions(page, card.id)).map(({ type }) => type)).toEqual(['Issued']);
    });

    test('adjusting the balance is recorded in the ledger with its reason', async ({ page }) => {
        const card = await issueGiftCard(page, { amount: 10_000 });

        await adjustBalance(page, card.id, 2500, 'Goodwill after a late delivery');
        await adjustBalance(page, card.id, -4000, 'Paid in the physical store');

        const details = await giftCardDetails(page, card.id);
        expect(moneyInCents(details.Amount)).toBe(8500);
        expect(moneyInCents(details['Initial amount'])).toBe(10_000);

        const ledger = (await giftCardTransactions(page, card.id)).map(({ type, amount, reason }) => [type, amount, reason]);
        expect(ledger).toEqual([
            ['Issued', 10_000, '-'],
            ['Manual adjustment', 2500, 'Goodwill after a late delivery'],
            ['Manual adjustment', -4000, 'Paid in the physical store'],
        ]);

        // The grid tells a card that has been spent from apart from a fresh one
        const amountCell = (await giftCardRows(page, card.printedCode)).locator('td').nth(2);
        expect(moneyInCents((await amountCell.innerText()).split('\n')[0])).toBe(8500);
        await expect(amountCell).toContainText(/Initial amount/i);
    });

    test('a card nothing has happened to can be deleted', async ({ page }) => {
        const card = await issueGiftCard(page, { amount: 1500 });

        const row = await giftCardRows(page, card.printedCode);
        await clickAndConfirm(page, row.getByRole('button', { name: /delete/i }));

        expect(await flashMessages(page)).toContainEqual(expect.stringMatching(/successfully deleted/i));
        await expect(await giftCardRows(page, card.printedCode)).toHaveCount(0);
        expect((await page.goto(`/admin/gift-cards/${card.id}`))?.status()).toBe(404);
    });

    /**
     * Once money has moved on a card, deleting it would erase the ledger that accounts for that money
     */
    test('a card whose balance has moved cannot be deleted', async ({ page }) => {
        const card = await issueGiftCard(page, { amount: 1500 });
        await adjustBalance(page, card.id, -500, 'Partly used');

        const row = await giftCardRows(page, card.printedCode);
        await clickAndConfirm(page, row.getByRole('button', { name: /delete/i }));

        expect(await flashMessages(page)).toContainEqual(expect.stringMatching(/cannot be removed/i));
        await expect(await giftCardRows(page, card.printedCode)).toHaveCount(1);
        expect((await page.goto(`/admin/gift-cards/${card.id}`))?.status()).toBe(200);
    });
});
