/**
 * Helpers for the shop cart that more than one shop spec needs: filling it and redeeming a gift card on it.
 */

const { expect } = require('@playwright/test');

const GIFT_CARD_FIELD = '[name="setono_sylius_gift_card_add_gift_card_to_order[giftCard]"]';

/**
 * Puts an ordinary product in the cart, the first one the home page links to.
 *
 * @param {import('@playwright/test').Page} page
 */
async function addSomethingToCart(page) {
    await page.goto('/en_US/');

    const href = await page.locator('a[href*="/products/"]').evaluateAll((links) => {
        const found = links
            .map((l) => l.getAttribute('href') ?? '')
            // a gift card product cannot be paid for with a gift card, so the cart needs an ordinary one
            .filter((h) => /\/products\//.test(h) && !h.includes('gift-card'));
        return found[0] ?? null;
    });
    expect(href, 'no ordinary product found in the shop').toBeTruthy();

    await page.goto(href);
    await page.locator('form[name="sylius_add_to_cart"] button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
}

/**
 * Submits the code through the cart's gift card form. Whether the card was accepted is left to the caller, since
 * some specs submit codes that must be rejected.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} code
 */
async function applyGiftCard(page, code) {
    await page.goto('/en_US/cart/');
    await page.locator(GIFT_CARD_FIELD).fill(code);

    // scoped to the gift card form, because the cart also carries an "Apply coupon" button
    const giftCardForm = page.locator('form').filter({ has: page.locator(GIFT_CARD_FIELD) });
    await giftCardForm.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
}

module.exports = { GIFT_CARD_FIELD, addSomethingToCart, applyGiftCard };
