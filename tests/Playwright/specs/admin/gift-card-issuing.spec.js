const { test, expect } = require('@playwright/test');
const { clickAndConfirm, flashMessages, setChecked } = require('../support/admin');
const { addSomethingToCart, applyGiftCard } = require('../support/cart');
const { adjustBalance, giftCardDetails, giftCardRows, giftCardTransactions, issueGiftCard } = require('../support/gift-cards');
const { moneyInCents } = require('../support/money');
const { anyCustomerEmail, channelBaseCurrencyCode } = require('../support/fixtures');
const { clickAndWaitForPage } = require('../support/navigation');

/**
 * Issuing, changing and removing gift cards in the admin.
 *
 * Every spec issues the card it works on, so it knows the card's balance and history exactly, and the cards the
 * fixtures seeded are left for the specs that only look at them.
 */

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

test.describe('admin issuing gift cards', () => {
    /**
     * An admin issuing a card to hand over in the store writes down the code the form shows. The form used to show a
     * code it then threw away, saving the card under a second, freshly generated one
     */
    test('the card is saved with the code the create form shows', async ({ page }) => {
        const card = await issueGiftCard(page, { amount: 1000 });

        // generated, so only its shape is known
        expect(card.shownCode).toMatch(/^[A-Z0-9]+$/);
        expect(card.code).toBe(card.shownCode);

        // Once issued the code is what the customer was given, so the edit form shows it but no longer takes a new one
        await page.goto(`/admin/gift-cards/${card.id}/edit`);
        await expect(page.locator('[name$="[code]"]')).toHaveValue(card.shownCode);
        await expect(page.locator('[name$="[code]"]')).toBeDisabled();
    });

    /**
     * The cart looks a code up the way customers type it, in capitals and without the dashes and spaces it is printed
     * or written down with, so a card has to be stored that way too, or its code could never be redeemed
     */
    test('a code the admin types is saved the way customers enter it and can be redeemed as typed', async ({ page, browser }) => {
        // codes are unique, so every run types a new one; written the way people do: lowercase, a dash and a space
        const stamp = Date.now();
        const typed = `summer-${stamp} xyz`;

        const card = await issueGiftCard(page, { amount: 1000, code: typed });

        expect(card.code).toBe(`SUMMER${stamp}XYZ`);
        await expect(await giftCardRows(page, card.printedCode)).toHaveCount(1);

        // a customer of the shop, not the signed in administrator
        const shop = await browser.newContext({ storageState: { cookies: [], origins: [] } });
        const customer = await shop.newPage();
        await addSomethingToCart(customer);
        await applyGiftCard(customer, typed);
        // the cart lists the code grouped in fours, so the card is found through its remove form, which carries the stored code
        await expect(customer.locator(`form[action*="/gift-cards/${card.code}/remove"]`)).toBeVisible();
        await shop.close();
    });

    /**
     * A code is a bearer token, and a short one can be guessed at the redemption form, so a code the admin types is
     * held to minimum_code_length (12 by default) like a generated one. It is counted once normalized: the code typed
     * here is 14 characters, 11 without its dashes
     */
    test('a code the admin types has to be long enough not to be guessed', async ({ page }) => {
        const form = 'form[name="setono_sylius_gift_card_gift_card"]';

        await page.goto('/admin/gift-cards/new');
        const channel = await page.locator(`${form} select[name$="[channel]"]`).inputValue();
        const baseCurrency = await channelBaseCurrencyCode(page, channel);

        await page.goto('/admin/gift-cards/new');
        const codeField = page.locator(`${form} .field`, { has: page.locator('[name$="[code]"]') });
        // the help under the field says so before anything is submitted
        await expect(codeField).toContainText('at least 12 letters and digits');

        await page.locator(`${form} [name$="[code]"]`).fill('abcd-efgh-jkm');
        await page.locator(`${form} select[name$="[currencyCode]"]`).selectOption(baseCurrency);
        await page.locator(`${form} [name$="[amount]"]`).fill('10');

        const [response] = await Promise.all([
            page.waitForResponse((r) => r.request().method() === 'POST'),
            page.locator(`${form} button[type="submit"]`).first().click(),
        ]);
        expect(response.status()).toBeLessThan(500);

        await expect(codeField.locator('.sylius-validation-error')).toHaveText(/at least 12 letters and digits/);
        await expect(await giftCardRows(page, 'ABCD-EFGH-JKM')).toHaveCount(0);
    });

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
        expect(details.Status).toBe('Usable');
        expect(moneyInCents(details.Amount)).toBe(2500);
        expect(moneyInCents(details['Initial amount'])).toBe(2500);
        // the customer row does what the grid's customer column does: the email mails them, the icon opens them
        const customerRow = page.locator('table').first().locator('tr').filter({ hasText: 'Customer' });
        await expect(customerRow.locator(`a[href="mailto:${email}"]`)).toHaveText(email);
        await expect(customerRow.getByRole('link', { name: `Open customer ${email} in a new tab` })).toHaveAttribute('href', /\/admin\/customers\/\d+$/);

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

    /**
     * The card shows the message with the line breaks it was written with, so the show page does too. The message is
     * the customer's text, so it is shown as typed rather than read as markup
     */
    test('the show page keeps the line breaks of the message', async ({ page }) => {
        const message = 'Happy birthday,\nlove from <b>Anna</b>\nand Bob';
        const card = await issueGiftCard(page, { amount: 1500, customMessage: message });

        await page.goto(`/admin/gift-cards/${card.id}`);
        const shown = page.locator('table').first().locator('tbody tr').filter({ hasText: 'Custom message' }).locator('td').nth(1);

        expect(await shown.innerText()).toBe(message);
        await expect(shown.locator('b')).toHaveCount(0);
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
        expect(details.Status).toBe('Disabled');
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
     * Once money has moved on a card, deleting it would erase the ledger that accounts for that money, so its row no
     * longer offers to. The server refuses as well, for a button on a page opened before (GiftCardAdminResourceTest)
     */
    test('a card whose balance has moved offers no delete button', async ({ page }) => {
        const card = await issueGiftCard(page, { amount: 1500 });
        const untouched = await issueGiftCard(page, { amount: 1500 });
        await adjustBalance(page, card.id, -500, 'Partly used');

        await expect((await giftCardRows(page, card.printedCode)).getByRole('button', { name: /delete/i })).toHaveCount(0);
        // the rest of the row's actions are still there, and so is the button on a card that may be deleted
        await expect((await giftCardRows(page, card.printedCode)).getByRole('link', { name: /adjust balance/i })).toHaveCount(1);
        const untouchedRow = await giftCardRows(page, untouched.printedCode);
        await expect(untouchedRow.getByRole('button', { name: /delete/i })).toHaveCount(1);

        // An untouched card can be deleted, which keeps it from topping the grid for other specs
        await clickAndConfirm(page, untouchedRow.getByRole('button', { name: /delete/i }));
        expect(await flashMessages(page)).toContainEqual(expect.stringMatching(/successfully deleted/i));
    });

    /**
     * The ledger the adjustment is written to is on the card's show page, so that is where saving the adjustment
     * leads, and where the way back from the form leads too
     */
    test('adjusting the balance leads to the show page with the ledger', async ({ page }) => {
        const card = await issueGiftCard(page, { amount: 3000 });

        await page.goto(`/admin/gift-cards/${card.id}/adjust-balance`);
        const showPage = `/admin/gift-cards/${card.id}`;
        await expect(page.locator('.breadcrumb').getByRole('link', { name: card.printedCode })).toHaveAttribute('href', showPage);
        await expect(page.locator('form.ui.form').getByRole('link', { name: 'Cancel' })).toHaveAttribute('href', showPage);

        await adjustBalance(page, card.id, -1000, 'Paid at the till');

        expect(new URL(page.url()).pathname).toBe(showPage);
        await expect(page.locator('table').filter({ has: page.locator('thead') }).getByText('Paid at the till')).toBeVisible();
    });
});
