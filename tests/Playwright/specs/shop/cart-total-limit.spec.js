const { test, expect } = require('@playwright/test');
const { typedAmount } = require('../support/money');
const { clickAndWaitForPage } = require('../support/navigation');
const { GIFT_CARD_INFORMATION, addGiftCardToCart, cartFigure, giftCardProductPath, shopErrors } = require('../support/shop');

/**
 * Sylius keeps an order's totals in integer columns, which hold 21,474,836.47 of a two decimal currency. The customer
 * chooses what a gift card costs, and the seeded shop sets no maximum, so gift cards that each fit could take a cart
 * past that together: the database refused the cart, and the visitor got a server error. The shop refuses them as
 * invalid instead, when the cards are added and when a gift card line's quantity is raised.
 *
 * 20,000,000.00 fits a gift card and a cart, but two of them do not fit a cart.
 */
const LARGE_AMOUNT = 2000000000;

test.describe('shop cart total limit', () => {
    test('gift cards that do not fit the cart together are refused on the product page', async ({ page }) => {
        await page.goto(await giftCardProductPath(page));

        const form = page.locator('form[name="sylius_add_to_cart"]');
        await form.locator(`${GIFT_CARD_INFORMATION}[name*="[amount]"]`).first().fill(typedAmount(LARGE_AMOUNT));
        await form.locator('[data-test-quantity]').fill('2');

        const action = await form.getAttribute('action');
        const [response] = await Promise.all([
            page.waitForResponse((r) => 'POST' === r.request().method() && r.url().endsWith(action ?? '')),
            form.locator('[data-test-add-to-cart-button]').click(),
        ]);

        // refused as invalid, by the form itself: neither the amount nor the quantity is wrong on its own
        expect(response.status(), 'adding to the cart must be refused as invalid, not fail with a server error').toBe(400);
        const { errors } = await response.json();
        const formErrors = errors?.form?.errors?.errors ?? [];
        expect(formErrors, 'the refusal should be about the cart').toHaveLength(1);
        expect(errors?.form?.errors?.children?.giftCardInformation?.children?.amount?.errors ?? []).toHaveLength(0);

        // Sylius' add to cart script renders the 400 payload into this element
        const validationError = page.locator('[data-test-cart-validation-error]');
        await expect(validationError).toBeVisible();
        await expect(validationError).toContainText(formErrors[0]);
    });

    test('raising a gift card line past what the cart holds is refused on the line', async ({ page }) => {
        await addGiftCardToCart(page, { amount: LARGE_AMOUNT });
        expect(await cartFigure(page, 'Items total'), 'the cart should hold the one card').toBe(LARGE_AMOUNT);

        const productPage = await giftCardProductPath(page);
        const line = page.locator('[data-test-cart-items] tbody tr').filter({ has: page.locator(`a[href="${productPage}"]`) });
        await expect(line).toHaveCount(1);
        await line.locator('[data-test-cart-item-quantity-input]').fill('2');

        await clickAndWaitForPage(page, page.locator('[data-test-cart-update-button]'));

        // the error sits on the line whose quantity was raised, and Sylius says the cart was not recalculated
        const errors = line.locator('[data-test-validation-error]');
        await expect(errors).toHaveCount(1);
        await expect(errors).not.toBeEmpty();
        expect(await shopErrors(page)).toHaveLength(1);

        // the cart is left as it was
        expect(await cartFigure(page, 'Items total')).toBe(LARGE_AMOUNT);
    });
});
