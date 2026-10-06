const { test, expect } = require('@playwright/test');
const { setChecked, signInAsAdministrator } = require('../support/admin');
const { checkOutAsGuest, uniqueEmail } = require('../support/checkout');
const { giftCardDetails, issueGiftCard } = require('../support/gift-cards');
const { clickAndWaitForPage } = require('../support/navigation');
const { addOrdinaryProductToCart, appliedGiftCardRow, redeemGiftCard, shopErrors, shopPath } = require('../support/shop');

/**
 * A gift card that stops being usable between the cart and "Place order".
 *
 * A cart the card fully covers skips the payment step, so nothing re-checks the card before the order is placed.
 * If an admin disables it in the meantime (or it is spent from another cart, or adjusted), the customer must not
 * be able to place the order as paid, nor be bounced between checkout steps or shown an error: the card is removed
 * from the cart, the customer is told why and lands on the cart to start over, this time paying by other means.
 *
 * The spec issues its own card through the admin rather than disabling the seeded one, which the other shop specs
 * rely on staying usable.
 */

test.describe('a gift card going stale during checkout', () => {
    // each journey issues a card in the admin, walks a guest through checkout and returns to the admin
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
     * Issues a card big enough to cover any cart, applies it to a cart of the customer's and takes the cart to the
     * complete step, which with the card covering everything skips the payment step
     *
     * @param {import('@playwright/test').Page} page
     */
    async function cartPaidByACardAtTheCompleteStep(page) {
        const cart = await shopPath(page, 'cart/');
        const card = await issueGiftCard(admin.page, { amount: 100_000 });

        await addOrdinaryProductToCart(page);
        await redeemGiftCard(page, card.code);
        const steps = await checkOutAsGuest(page, uniqueEmail('stale'));
        expect(steps.payment, 'the card covers the cart, so the payment step should be skipped').toBe(false);

        return { ...card, cart };
    }

    /**
     * Disables the card through its edit form in the admin
     *
     * @param {string} id
     */
    async function disableGiftCard(id) {
        await admin.page.goto(`/admin/gift-cards/${id}/edit`);
        await setChecked(admin.page.locator('form[name="setono_sylius_gift_card_gift_card"] [name$="[enabled]"]'), false);
        await clickAndWaitForPage(admin.page, admin.page.locator('form[name="setono_sylius_gift_card_gift_card"] button[type="submit"]').first());

        expect((await giftCardDetails(admin.page, id)).Enabled).toBe('Disabled');
    }

    /**
     * Checks the customer is back on the cart, told which card could not be used, and the card is gone from the cart
     *
     * @param {import('@playwright/test').Page} page
     * @param {{code: string, printedCode: string, cart: string}} card
     */
    async function expectBackOnTheCartWithoutTheCard(page, card) {
        await expect(page).toHaveURL((url) => url.pathname === card.cart);

        // the message names the card the way the customer knows it, grouped for reading
        const errors = await shopErrors(page);
        expect(errors.filter((error) => error.includes(card.printedCode)), 'the customer should be told which card could not be used').toHaveLength(1);

        // the card is gone from the cart and the order costs what it did, now to be paid by other means
        await expect(appliedGiftCardRow(page, card.code)).toHaveCount(0);
        await expect(page.locator('[data-test-gift-card-totals]')).toHaveCount(0);
    }

    test('placing the order sends the customer back to the cart without the card', async ({ page }) => {
        const card = await cartPaidByACardAtTheCompleteStep(page);

        await disableGiftCard(card.id);

        await clickAndWaitForPage(page, page.locator('form[name="sylius_checkout_complete"] button[type="submit"]').first());

        await expectBackOnTheCartWithoutTheCard(page, card);
    });

    test('revisiting the complete step sends the customer back to the cart rather than looping', async ({ page }) => {
        const card = await cartPaidByACardAtTheCompleteStep(page);

        await disableGiftCard(card.id);

        // Sylius' checkout resolver asks the state machine whether the step can be applied on every request to a
        // checkout page; with the guard saying no, it would redirect to the complete step forever
        const response = await page.goto(await shopPath(page, 'checkout/complete'));
        expect(response?.status()).toBe(200);

        await expectBackOnTheCartWithoutTheCard(page, card);
    });
});
