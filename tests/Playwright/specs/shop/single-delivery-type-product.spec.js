const { test, expect } = require('@playwright/test');
const { moneyInCents } = require('../support/money');
const {
    GIFT_CARD_INFORMATION,
    GIFT_CARD_PRODUCT_PAGE,
    VARIANT_CHOICE,
    addGiftCardToCart,
    singleDeliveryTypeGiftCardProductPath,
} = require('../support/shop');

/**
 * A gift card product with a single delivery type leaves the customer nothing to choose. It has one variant and no
 * delivery option, so Sylius treats it as a simple product: its page shows no delivery choice, and the cart buys its
 * one variant. The test application seeds one, sold as virtual cards only, through the product fixture's
 * delivery_types, and the spec finds it among the gift card products the home page links to as the one without a
 * delivery choice.
 */
test.describe('shop gift card product with a single delivery type', () => {
    test('its page is the gift card product page, without a delivery choice', async ({ page }) => {
        const response = await page.goto(await singleDeliveryTypeGiftCardProductPath(page));

        expect(response?.status()).toBe(200);
        await expect(page.locator(GIFT_CARD_PRODUCT_PAGE)).toBeVisible();
        await expect(page.locator('[data-test-gift-card-preview]')).toBeVisible();
        await expect(page.locator(`${GIFT_CARD_INFORMATION}[name*="[amount]"]`)).toBeVisible();
        await expect(page.locator('[data-test-gift-card-delivery]')).toHaveCount(0);
        await expect(page.locator('[data-test-product-variants]')).toHaveCount(0);
        await expect(page.locator(VARIANT_CHOICE)).toHaveCount(0);
    });

    test('it is added to the cart at the amount the customer chose', async ({ page }) => {
        const productPage = await singleDeliveryTypeGiftCardProductPath(page);
        const amount = 2500;

        await addGiftCardToCart(page, { amount, productPath: productPage });

        // The cart holds one line, for this product, at the amount chosen
        const lines = page.locator('[data-test-cart-items] tbody tr');
        await expect(lines).toHaveCount(1);
        const line = lines.filter({ has: page.locator(`a[href="${productPage}"]`) });
        await expect(line).toHaveCount(1);
        expect(moneyInCents(await line.locator('[data-test-cart-product-unit-price]').innerText())).toBe(amount);

        // Without options the cart names the line by its variant, which says how the card is delivered
        await expect(line.locator('[data-test-product-options]')).toHaveCount(0);
        await expect(line.locator('[data-test-product-variant-name]')).toHaveText(/\S/);
    });
});
