const { test, expect } = require('@playwright/test');
const { moneyInCents } = require('../support/money');
const { addGiftCardToCart, singleDeliveryTypeGiftCardProductPath, VARIANT_CHOICE } = require('../support/shop');

/**
 * A gift card product with a single delivery type leaves the customer nothing to choose. It has one variant and no
 * delivery option, so Sylius treats it as a simple product: its page shows no variant table, and the cart buys its one
 * variant. The test application seeds one, sold as virtual cards only, through the product fixture's delivery_types.
 */
test.describe('shop gift card product with a single delivery type', () => {
    test('its page has the gift card form and no variant choice', async ({ page }) => {
        const response = await page.goto(await singleDeliveryTypeGiftCardProductPath(page));

        expect(response?.status()).toBe(200);
        await expect(page.locator('[name*="giftCardInformation"][name*="[amount]"]')).toHaveCount(1);
        await expect(page.locator('#sylius-product-variants')).toHaveCount(0);
        await expect(page.locator(VARIANT_CHOICE)).toHaveCount(0);
    });

    test('it is added to the cart at the amount the customer chose', async ({ page }) => {
        const productPath = await singleDeliveryTypeGiftCardProductPath(page);
        const amount = 2500;

        await addGiftCardToCart(page, { amount, productPath });

        const lines = page.locator('#sylius-cart-items tbody tr');
        await expect(lines).toHaveCount(1);

        const line = lines.first();
        await expect(line.locator(`a[href="${productPath}"]`).first()).toBeVisible();
        expect(moneyInCents(await line.locator('.sylius-unit-price').innerText())).toBe(amount);

        // Without options the cart names the line by its variant, which says how the card is delivered
        await expect(line.locator('.sylius-product-options')).toHaveCount(0);
        await expect(line.locator('.sylius-product-variant-name')).toHaveText(/\S/);
    });
});
