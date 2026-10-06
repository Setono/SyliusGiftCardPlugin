const { test, expect } = require('@playwright/test');
const { CHECKOUT_FIGURES, checkOutAsGuest, checkoutFigure, uniqueEmail } = require('../support/checkout');
const { addGiftCardToCart, addOrdinaryProductToCart, cartFigure, redeemGiftCard } = require('../support/shop');

/**
 * What the applied gift cards cover and what remains to pay, on every checkout step up to placing the order.
 *
 * The cart shows these figures below its order total, and the checkout has to keep showing them. The gift cards pay
 * for the order rather than discount it, so the order total every step shows stays what the order costs, and without
 * the figures the customer would be shown that full amount as though no card had been applied. That is most
 * confusing when the cards cover the whole order: the payment step is skipped then, so nothing else in the checkout
 * says that the cards pay for it.
 *
 * The orders are never placed, so the seeded card is applied but never spent. Each step's figures are compared with
 * the order total that step shows rather than with the cart's, as the address given at checkout may change the
 * shipping and the taxes.
 */

// Seeded with a known code and a balance far larger than a cart, from the test application's fixtures
const GIFT_CARD_CODE = 'E2EREDEMPTION01';

/**
 * Checks that the gift card figures on the current step are rows of a table, below the order total the step shows
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} step
 */
async function expectFiguresBelowTheOrderTotal(page, step) {
    const layout = await page.evaluate((orderTotalSelector) => {
        const follows = (later, earlier) =>
            null !== later && null !== earlier && 0 !== (earlier.compareDocumentPosition(later) & Node.DOCUMENT_POSITION_FOLLOWING);

        const orderTotal = document.querySelector(orderTotalSelector);
        const giftCards = document.querySelector('[data-test-gift-cards-total]');
        const remaining = document.querySelector('[data-test-gift-cards-remaining-total]');

        return {
            giftCardsFollowOrderTotal: follows(giftCards, orderTotal),
            remainingFollowsGiftCards: follows(remaining, giftCards),
            rowsOfATable: [giftCards, remaining].every((row) => null !== row && 'TR' === row.tagName && null !== row.closest('table')),
        };
    }, CHECKOUT_FIGURES['Order total']);

    expect(layout, `the gift card figures on the ${step} step`).toEqual({
        giftCardsFollowOrderTotal: true,
        remainingFollowsGiftCards: true,
        rowsOfATable: true,
    });
}

test.describe('the gift card figures at checkout', () => {
    test('every step shows that a card covering the whole order leaves nothing to pay', async ({ page }) => {
        // a cart filled and a card redeemed, then the whole guest checkout with the figures read on every step, the
        // address step waiting out the province field's reload: a busy runner has taken that past the default
        // timeout (#468), and CI does not retry
        test.slow();

        await addOrdinaryProductToCart(page);
        await redeemGiftCard(page, GIFT_CARD_CODE);
        expect(await cartFigure(page, 'Remaining to pay'), 'precondition: the card covers the whole cart').toBe(0);

        /** @type {string[]} */
        const visited = [];
        const steps = await checkOutAsGuest(page, uniqueEmail('checkout-figures-in-full'), async (step) => {
            visited.push(step);

            const total = await checkoutFigure(page, 'Order total');
            expect(total, `the ${step} step should show the full order total, which the card leaves as it is`).toBeGreaterThan(0);
            expect(await checkoutFigure(page, 'Gift cards'), `the ${step} step should show the card covering the order total`).toBe(-total);
            expect(await checkoutFigure(page, 'Remaining to pay'), `the ${step} step should show that nothing remains to pay`).toBe(0);
            await expectFiguresBelowTheOrderTotal(page, step);
        });

        // With nothing left to pay the payment step is skipped, so the other steps are where the customer learns it
        expect(steps.payment, 'the payment step should be skipped').toBe(false);
        expect(visited).toEqual(steps.shipping ? ['address', 'select_shipping', 'complete'] : ['address', 'complete']);
    });

    /**
     * A gift card cannot pay for another gift card, so a cart that also buys one leaves that line to pay however much
     * the redeemed card holds: the seeded card covers part of the order without anything being spent
     */
    test('every step shows what remains to pay when the card covers part of the order', async ({ page }) => {
        // the walk of the test above, with a gift card line added first and the payment step to go through as well
        test.slow();

        const giftCardLine = 3000;
        await addGiftCardToCart(page, { amount: giftCardLine });
        await addOrdinaryProductToCart(page);
        await redeemGiftCard(page, GIFT_CARD_CODE);
        expect(await cartFigure(page, 'Remaining to pay'), 'precondition: the card leaves the gift card line to pay').toBe(giftCardLine);

        /** @type {string[]} */
        const visited = [];
        const steps = await checkOutAsGuest(page, uniqueEmail('checkout-figures-in-part'), async (step) => {
            visited.push(step);

            const total = await checkoutFigure(page, 'Order total');
            expect(await checkoutFigure(page, 'Gift cards'), `the ${step} step should show the card covering all but the gift card line`).toBe(
                -(total - giftCardLine),
            );
            expect(await checkoutFigure(page, 'Remaining to pay'), `the ${step} step should show the gift card line remaining to pay`).toBe(giftCardLine);
            await expectFiguresBelowTheOrderTotal(page, step);
        });

        expect(steps.payment, 'the rest has to be paid somehow, so the payment step should be shown').toBe(true);
        expect(visited).toEqual(
            steps.shipping ? ['address', 'select_shipping', 'select_payment', 'complete'] : ['address', 'select_payment', 'complete'],
        );
    });
});
