const { test, expect } = require('@playwright/test');
const { productIdsByKind } = require('../support/fixtures');

/**
 * Regression cover for the product edit page.
 *
 * The test application overrides Sylius' product details tab to render the gift card checkbox. That override
 * once dropped the remote_url and load_edit_url options Sylius passes to the options autocomplete, and since
 * the form theme reads remote_url unconditionally, every configurable product's edit page returned a 500 —
 * gift card products and ordinary ones alike. Nothing covered these pages, so it went unnoticed until someone
 * opened one by hand.
 */
test.describe('admin product edit', () => {
    /** @type {{simple: string|null, configurable: string|null, giftCard: string|null}} */
    let products;

    // Resolved through the signed in page fixture rather than beforeAll, because a page made straight from
    // the browser would not carry the stored admin session
    test.beforeEach(async ({ page }) => {
        products = await productIdsByKind(page);
    });

    test('renders for a configurable product', async ({ page }) => {
        expect(products.configurable, 'no configurable product was seeded').not.toBeNull();

        const response = await page.goto(`/admin/products/${products.configurable}/edit`);

        expect(response?.status()).toBe(200);
        await expect(page).toHaveTitle(/Edit product/);
        // The field whose missing options caused the 500
        await expect(page.locator('.sylius-autocomplete').first()).toBeVisible();
    });

    test('renders for a simple product', async ({ page }) => {
        expect(products.simple, 'no simple product was seeded').not.toBeNull();

        const response = await page.goto(`/admin/products/${products.simple}/edit`);

        expect(response?.status()).toBe(200);
        await expect(page).toHaveTitle(/Edit product/);
    });

    test('renders for a gift card product and exposes the gift card toggle', async ({ page }) => {
        expect(products.giftCard, 'no gift card product was seeded').not.toBeNull();

        const response = await page.goto(`/admin/products/${products.giftCard}/edit`);

        expect(response?.status()).toBe(200);
        await expect(page.locator('input[name*="[giftCard]"]')).toHaveCount(1);
    });

    test('the options autocomplete is given the url it needs', async ({ page }) => {
        expect(products.configurable, 'no configurable product was seeded').not.toBeNull();

        await page.goto(`/admin/products/${products.configurable}/edit`);

        // data-url is rendered from remote_url; empty means the option was dropped again and the page only
        // survives because Twig happened not to be in strict mode
        const dataUrl = await page.locator('.sylius-autocomplete').first().getAttribute('data-url');
        expect(dataUrl, 'the options autocomplete has no remote_url').toBeTruthy();
    });
});
