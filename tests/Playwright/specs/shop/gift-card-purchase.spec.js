const { test, expect } = require('@playwright/test');
const { signInAsAdministrator } = require('../support/admin');
const { checkOutAsGuest, placeOrder, uniqueEmail } = require('../support/checkout');
const { giftCardDetails, giftCardIds, giftCardRows, giftCardTransactions } = require('../support/gift-cards');
const { moneyInCents } = require('../support/money');
const { cancelOrder, completeOrderPayments, openOrderOf, orderPayments } = require('../support/orders');
const { addGiftCardToCart, addOrdinaryProductToCart, cartFigure, giftCardVariantCount, redeemGiftCard, shopPath } = require('../support/shop');

/**
 * Buying a gift card in the shop, from the product page to the card the customer ends up with.
 *
 * A card is created, disabled, when the gift card is put in the cart, and only becomes usable once the order is paid.
 * Until then the admin's gift card list leaves it out, so the specs look the card up after the payment, by the email
 * the customer checked out with.
 */
test.describe('buying a gift card', () => {
    // each journey checks out in the shop and then works through the admin
    test.setTimeout(120_000);

    /** @type {{page: import('@playwright/test').Page, close: () => Promise<void>}} */
    let admin;

    test.beforeEach(async ({ browser }) => {
        admin = await signInAsAdministrator(browser);
    });

    test.afterEach(async () => {
        await admin?.close();
    });

    test('the card is issued to the customer once the order is paid, and can then be redeemed', async ({ page, browser }) => {
        const email = uniqueEmail('buyer');
        const amount = 7550;
        const message = 'Happy birthday!';

        const { design } = await addGiftCardToCart(page, { amount, message });

        // The customer chose the amount, so that is what the line costs, whatever price the variant carries
        const line = page.locator('#sylius-cart-items tbody tr').first();
        expect(moneyInCents(await line.locator('.sylius-unit-price').innerText())).toBe(amount);

        await checkOutAsGuest(page, email);
        await placeOrder(page);

        // Not paid yet: the card exists, but it is pending and kept out of the list
        await expect(await giftCardRows(admin.page, email)).toHaveCount(0);

        await openOrderOf(admin.page, email);
        await completeOrderPayments(admin.page);

        const rows = await giftCardRows(admin.page, email);
        await expect(rows).toHaveCount(1);
        const [id] = await giftCardIds(rows);

        const details = await giftCardDetails(admin.page, id);
        expect(details.Enabled).toBe('Enabled');
        expect(details.Customer).toBe(email);
        expect(moneyInCents(details.Amount)).toBe(amount);
        expect(moneyInCents(details['Initial amount'])).toBe(amount);
        expect(details['Custom message']).toBe(message);
        expect(details.Design).toBe(design);

        // Issuance is recorded when the order is paid, the first moment the balance is final
        const transactions = await giftCardTransactions(admin.page, id);
        expect(transactions.map(({ type, amount: moved }) => [type, moved])).toEqual([['Issued', amount]]);

        // The card now pays for something else, in a cart of a customer of its own
        const code = details.Code.replace(/-/g, '');
        const redeemer = await browser.newContext();
        try {
            const shop = await redeemer.newPage();
            await addOrdinaryProductToCart(shop);
            await shop.goto(await shopPath(shop, 'cart/'));
            const total = await cartFigure(shop, 'Order total');

            await redeemGiftCard(shop, code);

            // A gift card pays for the order rather than discounting it, so the total stays and what is left to pay drops
            expect(await cartFigure(shop, 'Order total')).toBe(total);
            expect(await cartFigure(shop, 'Remaining to pay')).toBe(Math.max(0, total - amount));
        } finally {
            await redeemer.close();
        }
    });

    /**
     * Whether a card is physical or virtual follows from whether the variant it was bought as is shipped, so a
     * customer who is sent the plastic card gets a physical one and everybody else an emailed one
     */
    test('the delivery type follows whether the variant bought is shipped', async ({ browser }) => {
        const shop = await browser.newContext();
        const variants = await giftCardVariantCount(await shop.newPage());
        await shop.close();
        expect(variants, 'the gift card product should offer a variant per delivery type').toBeGreaterThanOrEqual(2);

        const seen = new Set();
        for (let variant = 0; variant < variants; variant++) {
            const customer = await browser.newContext();
            try {
                const page = await customer.newPage();
                const email = uniqueEmail(`delivery-${variant}`);

                await addGiftCardToCart(page, { amount: 2000, variant });
                // A cart that holds nothing to ship skips the shipping step
                const { shipping } = await checkOutAsGuest(page, email);
                await placeOrder(page);

                await openOrderOf(admin.page, email);
                await completeOrderPayments(admin.page);

                const rows = await giftCardRows(admin.page, email);
                await expect(rows, 'the paid order should have issued one card').toHaveCount(1);
                const [id] = await giftCardIds(rows);
                const expected = shipping ? 'Physical' : 'Virtual';
                expect((await giftCardDetails(admin.page, id))['Delivery type'], `variant #${variant + 1}`).toBe(expected);
                seen.add(expected);
            } finally {
                await customer.close();
            }
        }

        expect([...seen].sort(), 'the variants should cover both delivery types').toEqual(['Physical', 'Virtual']);
    });

    /**
     * A card is created per unit when the gift card is put in the cart. When the customer changes the quantity in the
     * cart afterwards, checking out reconciles the cards with the units: one card per unit, each for the amount chosen
     */
    test('changing the quantity in the cart issues one card per unit', async ({ page }) => {
        const email = uniqueEmail('quantity');
        const amount = 3000;

        await addGiftCardToCart(page, { amount });

        const line = page.locator('#sylius-cart-items tbody tr').first();
        const quantity = line.locator('input[name$="[quantity]"]');

        // up first, then down again, so reconciliation has both a unit to add a card for and a card to drop
        for (const units of [3, 2]) {
            await quantity.fill(String(units));
            await page.locator('#sylius-cart-update').click();
            await page.waitForLoadState();

            expect(moneyInCents(await line.locator('.sylius-unit-price').innerText()), `unit price for ${units} units`).toBe(amount);
            expect(moneyInCents(await line.locator('.sylius-total').innerText()), `line total for ${units} units`).toBe(units * amount);
        }

        await checkOutAsGuest(page, email);
        await placeOrder(page);

        await openOrderOf(admin.page, email);
        await completeOrderPayments(admin.page);

        const rows = await giftCardRows(admin.page, email);
        await expect(rows).toHaveCount(2);
        for (const id of await giftCardIds(rows)) {
            const details = await giftCardDetails(admin.page, id);
            expect(details.Enabled).toBe('Enabled');
            expect(moneyInCents(details.Amount)).toBe(amount);
        }
    });

    /**
     * An order that is cancelled after it was paid must not leave the cards it bought spendable. The order also holds
     * an ordinary product, so it is still open for cancelling once paid (an order with nothing to ship is fulfilled as
     * soon as it is paid)
     */
    test('cancelling a paid order disables the cards it bought', async ({ page }) => {
        const email = uniqueEmail('cancelled-purchase');

        await addGiftCardToCart(page, { amount: 4000 });
        await addOrdinaryProductToCart(page);
        await checkOutAsGuest(page, email);
        await placeOrder(page);

        await openOrderOf(admin.page, email);
        await completeOrderPayments(admin.page);
        expect((await orderPayments(admin.page)).map(({ state }) => state)).toEqual(['Completed']);

        const rows = await giftCardRows(admin.page, email);
        await expect(rows, 'the paid order should have issued one card').toHaveCount(1);
        const [id] = await giftCardIds(rows);
        expect((await giftCardDetails(admin.page, id)).Enabled).toBe('Enabled');

        await openOrderOf(admin.page, email);
        await cancelOrder(admin.page);

        expect((await giftCardDetails(admin.page, id)).Enabled).toBe('Disabled');
    });
});
