const { test, expect } = require('@playwright/test');
const { signInAsAdministrator } = require('../support/admin');
const { checkOutAsGuest, placeOrder, uniqueEmail } = require('../support/checkout');
const { giftCardDetails, giftCardTransactions, issueGiftCard } = require('../support/gift-cards');
const { moneyInCents } = require('../support/money');
const { cancelOrder, openOrderOf, orderPayments } = require('../support/orders');
const { addGiftCardToCart, addOrdinaryProductToCart, cartFigure, redeemGiftCard, shopPath } = require('../support/shop');

/**
 * Paying for an order with a gift card, through the checkout to the order the admin sees.
 *
 * A redeemed gift card becomes a payment of its own against the order when the order is placed, and the balance it
 * pays with is taken off the card at that moment. The order total is left alone: the specs follow the money into the
 * order's payments and the card's ledger instead.
 *
 * Each spec issues the card it spends, so the seeded cards the other specs rely on keep their balance.
 */
test.describe('paying with a gift card', () => {
    // each journey issues a card in the admin, checks out in the shop and returns to the admin
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
     * Fills a cart with an ordinary product and returns what it costs
     *
     * @param {import('@playwright/test').Page} page
     */
    async function cartWithSomethingToPayFor(page) {
        await addOrdinaryProductToCart(page);
        await page.goto(await shopPath(page, 'cart/'));

        const total = await cartFigure(page, 'Order total');
        expect(total, 'the cart should cost something').toBeGreaterThan(0);

        return total;
    }

    test('an order the card covers in full skips the payment step and is paid by the card', async ({ page }) => {
        const email = uniqueEmail('paid-by-gift-card');
        const balance = 100_000;
        const card = await issueGiftCard(admin.page, { amount: balance });

        await cartWithSomethingToPayFor(page);
        await redeemGiftCard(page, card.code);
        expect(await cartFigure(page, 'Remaining to pay')).toBe(0);

        // With nothing left to pay there is no payment method to choose
        const steps = await checkOutAsGuest(page, email);
        expect(steps.payment, 'the payment step should be skipped').toBe(false);

        await placeOrder(page);

        const order = await openOrderOf(admin.page, email);
        const orderTotal = moneyInCents(await admin.page.locator('#total').innerText());
        const payments = await orderPayments(admin.page);
        expect(payments).toEqual([{ method: 'Gift card', amount: orderTotal, state: 'Completed' }]);

        // The card paid exactly the order total, and its ledger says which order it paid for
        expect(moneyInCents((await giftCardDetails(admin.page, card.id)).Amount)).toBe(balance - orderTotal);
        const redemption = (await giftCardTransactions(admin.page, card.id)).filter(({ order: number }) => null !== number);
        expect(redemption).toEqual([
            expect.objectContaining({ type: 'Redeemed', amount: -orderTotal, order: `#${order.number}`, orderHref: order.url }),
        ]);
    });

    test('an order the card covers in part is paid by the card and the method chosen for the rest', async ({ page }) => {
        const email = uniqueEmail('part-paid-by-gift-card');

        const total = await cartWithSomethingToPayFor(page);
        // Half of what the cart costs, so the card covers part of the order whichever shipping the checkout settles on
        const balance = Math.floor(total / 2);
        const card = await issueGiftCard(admin.page, { amount: balance });

        await redeemGiftCard(page, card.code);
        expect(await cartFigure(page, 'Order total'), 'redeeming should not change what the order costs').toBe(total);
        expect(await cartFigure(page, 'Remaining to pay')).toBe(total - balance);

        const steps = await checkOutAsGuest(page, email);
        expect(steps.payment, 'the rest has to be paid somehow, so the payment step should be shown').toBe(true);

        await placeOrder(page);

        await openOrderOf(admin.page, email);
        const orderTotal = moneyInCents(await admin.page.locator('#total').innerText());
        const payments = await orderPayments(admin.page);

        // The card pays what it holds right away; the rest waits for the method the customer chose
        const byCard = payments.filter(({ method }) => 'Gift card' === method);
        const byOther = payments.filter(({ method }) => 'Gift card' !== method);
        expect(byCard).toEqual([{ method: 'Gift card', amount: balance, state: 'Completed' }]);
        expect(byOther).toEqual([expect.objectContaining({ amount: orderTotal - balance, state: 'New' })]);

        expect(moneyInCents((await giftCardDetails(admin.page, card.id)).Amount)).toBe(0);
    });

    /**
     * A gift card is a way to pay for goods, not a way to turn one gift card into another, so the gift cards in the
     * cart are left for the customer to pay by other means however much the redeemed card holds
     */
    test('the card does not pay for gift cards bought in the same order', async ({ page }) => {
        const email = uniqueEmail('gift-card-for-gift-card');
        const giftCardLine = 3000;
        const card = await issueGiftCard(admin.page, { amount: 100_000 });

        await addGiftCardToCart(page, { amount: giftCardLine });
        await cartWithSomethingToPayFor(page);
        await redeemGiftCard(page, card.code);
        expect(await cartFigure(page, 'Remaining to pay')).toBe(giftCardLine);

        const steps = await checkOutAsGuest(page, email);
        expect(steps.payment, 'the gift card line has to be paid somehow, so the payment step should be shown').toBe(true);
        await placeOrder(page);

        await openOrderOf(admin.page, email);
        const orderTotal = moneyInCents(await admin.page.locator('#total').innerText());
        const payments = await orderPayments(admin.page);
        const byCard = payments.filter(({ method }) => 'Gift card' === method);
        const byOther = payments.filter(({ method }) => 'Gift card' !== method);
        expect(byCard).toEqual([{ method: 'Gift card', amount: orderTotal - giftCardLine, state: 'Completed' }]);
        expect(byOther).toEqual([expect.objectContaining({ amount: giftCardLine, state: 'New' })]);
    });

    /**
     * The balance is committed when the order is placed, so an order that is cancelled afterwards has to give it back,
     * and the ledger has to show both movements against the order
     */
    test('cancelling an order paid with the card gives the balance back', async ({ page }) => {
        const email = uniqueEmail('cancelled-gift-card-order');
        const balance = 100_000;
        const card = await issueGiftCard(admin.page, { amount: balance });

        await cartWithSomethingToPayFor(page);
        await redeemGiftCard(page, card.code);
        await checkOutAsGuest(page, email);
        await placeOrder(page);

        const order = await openOrderOf(admin.page, email);
        const orderTotal = moneyInCents(await admin.page.locator('#total').innerText());
        expect(moneyInCents((await giftCardDetails(admin.page, card.id)).Amount)).toBe(balance - orderTotal);

        await admin.page.goto(order.url);
        await cancelOrder(admin.page);

        expect(moneyInCents((await giftCardDetails(admin.page, card.id)).Amount)).toBe(balance);
        const movements = (await giftCardTransactions(admin.page, card.id))
            .filter(({ order: number }) => `#${order.number}` === number)
            .map(({ amount }) => amount);
        expect(movements).toEqual([-orderTotal, orderTotal]);
    });
});
