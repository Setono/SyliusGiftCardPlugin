const { test, expect } = require('@playwright/test');
const { GRID_ROWS, signInAsAdministrator } = require('../support/admin');
const { checkOutAsGuest, checkoutFigure, placeOrder, uniqueEmail } = require('../support/checkout');
const { filterGiftCards, giftCardDetails, giftCardIds, giftCardTransactions, issueGiftCard } = require('../support/gift-cards');
const { moneyInCents, typedAmount } = require('../support/money');
const { clickAndWaitForPage } = require('../support/navigation');
const { completeOrderPayments, openOrderOf } = require('../support/orders');
const {
    GIFT_CARD_INFORMATION,
    GIFT_CARD_PRODUCT_PAGE,
    addGiftCardToCart,
    addOrdinaryProductToCart,
    cartFigure,
    giftCardProductPath,
    redeemGiftCard,
    shopPath,
    submitAddToCart,
    switchCurrency,
} = require('../support/shop');

/**
 * Gift cards in a display currency (#488).
 *
 * The seeded channel shows its prices in a currency besides its base currency, which Sylius keeps every order in and
 * converts what it shows from, at the exchange rate the shop keeps. A customer browsing in that currency buys a card
 * holding the amount they typed in it, and a card held in it pays an order shown in it: a card for 300.00 covers an
 * order shown as 300.00, whatever converting back and forth does to the last minor unit.
 *
 * The specs switch to the display currency with the shop's own currency switcher, and read which one it is off it, so
 * nothing depends on the currency or the rate the fixtures seed. The figures are compared in minor units, figure
 * against figure, except where a figure crosses the exchange rate: the shop shows the base currency's minor units
 * converted, so a figure that went through it may be a minor unit off the one typed.
 */
test.describe('gift cards in a display currency', () => {
    // each journey goes through the shop and the admin
    test.setTimeout(120_000);

    /** @type {{page: import('@playwright/test').Page, close: () => Promise<void>}} */
    let admin;

    test.beforeEach(async ({ browser }) => {
        admin = await signInAsAdministrator(browser);
    });

    test.afterEach(async () => {
        await admin?.close();
    });

    /**
     * Raising the quantity on the cart page and lowering it again drops the unit add to cart gave the card, as Sylius
     * drops units from the front of a line. The line keeps the card all the same, in the display currency
     */
    test('a card bought while browsing in a display currency holds the amount typed in it', async ({ page }) => {
        const currency = await switchCurrency(page);
        const email = uniqueEmail('display-currency');
        const amount = 4000;

        // the amount is typed in the display currency, which the live preview formats it in
        await page.goto(await giftCardProductPath(page));
        await expect(page.locator(GIFT_CARD_PRODUCT_PAGE)).toHaveAttribute('data-currency', currency);

        await addGiftCardToCart(page, { amount });

        // The line costs the amount in the base currency, which the cart shows converted back
        const line = page.locator('#sylius-cart-items tbody tr').first();
        const unitPrice = moneyInCents(await line.locator('.sylius-unit-price').innerText());
        expect(Math.abs(unitPrice - amount), `the line should cost about what was typed, not ${unitPrice}`).toBeLessThanOrEqual(1);

        for (const units of [2, 1]) {
            await line.locator('input[name$="[quantity]"]').fill(String(units));
            await clickAndWaitForPage(page, page.locator('[data-test-cart-update-button]'));
            expect(moneyInCents(await line.locator('.sylius-unit-price').innerText()), `unit price for ${units} units`).toBe(unitPrice);
        }

        await checkOutAsGuest(page, email);
        await placeOrder(page);

        await openOrderOf(admin.page, email);
        await completeOrderPayments(admin.page);

        // the card is held in the display currency, and holds exactly what was typed
        await filterGiftCards(admin.page, { customer: email, currencyCode: currency });
        const rows = admin.page.locator(GRID_ROWS).filter({ hasText: email });
        await expect(rows, `the card should be held in ${currency}`).toHaveCount(1);

        const [id] = await giftCardIds(rows);
        const details = await giftCardDetails(admin.page, id);
        expect(moneyInCents(details.Amount)).toBe(amount);
        expect(moneyInCents(details['Initial amount'])).toBe(amount);
    });

    /**
     * The page says which currency the amount was typed in, so switching the shop back to its base currency in another
     * tab before adding the card to the cart does not change what the card is worth: shown in the display currency
     * again, the line costs what was typed, not that amount in the base currency converted
     */
    test('a card is held in the currency its page was shown in, though the shop is switched in another tab', async ({ page }) => {
        const currency = await switchCurrency(page);
        const amount = 4000;

        await page.goto(await giftCardProductPath(page));
        await page.locator(`${GIFT_CARD_INFORMATION}[name*="[amount]"]`).first().fill(typedAmount(amount));

        const other = await page.context().newPage();
        const base = await switchCurrency(other);
        expect(base).not.toBe(currency);
        await other.close();

        await submitAddToCart(page);

        expect(await switchCurrency(page)).toBe(currency);
        await page.goto(await shopPath(page, 'cart/'));
        const unitPrice = moneyInCents(await page.locator('#sylius-cart-items tbody tr').first().locator('.sylius-unit-price').innerText());
        expect(Math.abs(unitPrice - amount), `the line should cost about what was typed, not ${unitPrice}`).toBeLessThanOrEqual(1);
    });

    /**
     * The card covers what the order shows, so nothing is left to pay, and its ledger gives exactly that. The order is
     * shipped, so what it shows grows by the shipping at the checkout, and the complete step shows what it costs in the
     * end
     */
    test('a card held in a display currency pays an order shown in it', async ({ page }) => {
        const currency = await switchCurrency(page);
        const card = await issueGiftCard(admin.page, { amount: 100_000, currency });

        await addOrdinaryProductToCart(page);
        await redeemGiftCard(page, card.code);

        expect(await cartFigure(page, 'Gift cards'), 'the card should cover what the cart shows').toBe(-(await cartFigure(page, 'Order total')));
        expect(await cartFigure(page, 'Remaining to pay')).toBe(0);

        const { payment } = await checkOutAsGuest(page, uniqueEmail('display-currency-redeemed'));
        expect(payment, 'the card covers the order, so the payment step should be skipped').toBe(false);

        const total = await checkoutFigure(page, 'Order total');
        expect(await checkoutFigure(page, 'Gift cards'), 'the card should cover what the order shows').toBe(-total);
        expect(await checkoutFigure(page, 'Remaining to pay')).toBe(0);
        await placeOrder(page);

        const redemptions = (await giftCardTransactions(admin.page, card.id)).filter(({ order }) => null !== order);
        expect(redemptions.map(({ amount }) => amount), 'the card should have given what the order showed').toEqual([-total]);
        expect(moneyInCents((await giftCardDetails(admin.page, card.id)).Amount)).toBe(100_000 - total);
    });

    /**
     * A smaller card gives its whole balance, which the cart shows by its own amount. What is left to pay is what the
     * customer is charged in the base currency, shown converted, so it may be a minor unit off the difference
     */
    test('a card held in a display currency pays part of an order shown in it', async ({ page }) => {
        const currency = await switchCurrency(page);

        await addOrdinaryProductToCart(page);
        await page.goto(await shopPath(page, 'cart/'));
        const total = await cartFigure(page, 'Order total');
        const amount = Math.floor(total / 2);

        const card = await issueGiftCard(admin.page, { amount, currency });
        await redeemGiftCard(page, card.code);

        expect(await cartFigure(page, 'Gift cards'), 'the card should give its whole balance').toBe(-amount);
        const remaining = await cartFigure(page, 'Remaining to pay');
        expect(Math.abs(remaining - (total - amount)), `what is left to pay should be the rest of the order, not ${remaining}`).toBeLessThanOrEqual(1);
    });
});
