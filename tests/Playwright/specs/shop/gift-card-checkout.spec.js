const { test, expect } = require('@playwright/test');
const { accountOrderRow, createCustomerAccount, signInToShop } = require('../support/account');
const { signInAsAdministrator } = require('../support/admin');
const { checkOutAsCustomer, checkOutAsGuest, payablePayments, payWith, placeOrder, uniqueEmail } = require('../support/checkout');
const { giftCardDetails, giftCardTransactions, issueGiftCard } = require('../support/gift-cards');
const { moneyInCents } = require('../support/money');
const { clickAndWaitForPage } = require('../support/navigation');
const { cancelOrder, completeOrderPayments, openOrderOf, orderPayments, orderPaymentState } = require('../support/orders');
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

    /**
     * Checks that the page of a placed order the card paid in part lets the customer pay the rest, and only the rest:
     * the card's payment is completed, so it can neither be paid again nor paid another way. Returns the payment
     * that is left
     *
     * @param {import('@playwright/test').Page} page the order's page in the shop
     */
    async function expectOnlyTheRestToBePayable(page) {
        await expect(page.getByText('You can no longer change payment method of this order')).toHaveCount(0);
        await expect(page.locator('#sylius-pay-link')).toBeEnabled();

        const payments = await payablePayments(page);
        expect(payments, 'the rest should be the one payment left to pay').toHaveLength(1);
        expect(payments[0].methods, 'the gift card is no way to pay the rest').not.toContain('Gift card');
        expect(payments[0].methods.length, 'the rest should be payable some way').toBeGreaterThan(0);

        return payments[0];
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
     * The shop only lets a customer pay for an order, or change how to pay it, while the order awaits payment. An
     * order the card paid in part has to keep awaiting payment of the rest, then: from the thank you page's "Change
     * payment method" a guest reaches the order's page, and that is also where Sylius sends a customer whose payment
     * at the payment provider did not go through
     */
    test('a guest whose card paid part of the order can choose how to pay the rest, and pay it', async ({ page }) => {
        const email = uniqueEmail('part-paid-guest');

        const total = await cartWithSomethingToPayFor(page);
        const balance = Math.floor(total / 2);
        const card = await issueGiftCard(admin.page, { amount: balance });

        await redeemGiftCard(page, card.code);
        await checkOutAsGuest(page, email);
        await placeOrder(page);

        await clickAndWaitForPage(page, page.locator('#payment-method-page'));
        const rest = await expectOnlyTheRestToBePayable(page);

        // Another method than the one chosen at checkout, so the method of the rest is shown to change
        const method = rest.methods.find((offered) => offered !== rest.chosen) ?? /** @type {string} */ (rest.chosen);
        await payWith(page, method);

        await openOrderOf(admin.page, email);
        const orderTotal = moneyInCents(await admin.page.locator('#total').innerText());
        expect(await orderPaymentState(admin.page), 'the order should wait for the rest to be paid').toBe('Awaiting payment');

        const payments = await orderPayments(admin.page);
        expect(payments.filter(({ method: paidBy }) => 'Gift card' === paidBy)).toEqual([
            { method: 'Gift card', amount: balance, state: 'Completed' },
        ]);
        expect(payments.filter(({ method: paidBy }) => 'Gift card' !== paidBy)).toEqual([
            { method, amount: orderTotal - balance, state: 'New' },
        ]);

        // The money for the rest comes in, and that pays the order
        await completeOrderPayments(admin.page);
        expect(await orderPaymentState(admin.page)).toBe('Paid');
    });

    test('a signed in customer whose card paid part of the order can pay the rest from their account', async ({ page }) => {
        test.setTimeout(180_000);

        const email = uniqueEmail('part-paid-customer');
        const password = 'gift-card-customer';
        await createCustomerAccount(admin.page, email, password);
        await signInToShop(page, email, password);

        const total = await cartWithSomethingToPayFor(page);
        const card = await issueGiftCard(admin.page, { amount: Math.floor(total / 2) });

        await redeemGiftCard(page, card.code);
        await checkOutAsCustomer(page);
        await placeOrder(page);

        // The thank you page sends a signed in customer to the order in their account, which offers to pay it
        await clickAndWaitForPage(page, page.locator('#sylius-show-order-in-account'));
        const number = /\/account\/orders\/(\d+)$/.exec(new URL(page.url()).pathname)?.[1] ?? '';
        expect(number, `the thank you page should have led to the order in the account, not ${page.url()}`).toMatch(/^\d+$/);

        const payFromOrder = page.locator('a[href*="/order/"]').filter({ hasText: 'Pay' });
        await expect(payFromOrder, 'the order in the account should offer to pay it').toHaveCount(1);
        const orderPage = await payFromOrder.getAttribute('href');

        // and so does the list of the customer's orders, leading to the same page
        const payFromList = (await accountOrderRow(page, number)).locator('a').filter({ hasText: 'Pay' });
        await expect(payFromList, "the customer's orders should offer to pay the order").toHaveAttribute('href', /** @type {string} */ (orderPage));

        await clickAndWaitForPage(page, payFromList);
        const rest = await expectOnlyTheRestToBePayable(page);
        await payWith(page, /** @type {string} */ (rest.chosen));
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
