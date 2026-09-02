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

    /**
     * The action scaffolds a product through the same factory the fixtures use, so a merchant lands on a
     * ready-to-edit gift card product instead of assembling the option and both variants by hand
     */
    test('a gift card product can be scaffolded from the gift cards index', async ({ page }) => {
        await page.goto('/admin/gift-cards/');
        await page.getByRole('link', { name: /gift card product/i }).click();

        await expect(page).toHaveURL(/\/admin\/products\/\d+\/edit/);
        await expect(page).toHaveTitle(/Edit product/);

        // Created disabled so the merchant reviews it before it goes live, and flagged as a gift card
        await expect(page.locator('input[name*="[giftCard]"]')).toBeChecked();
        await expect(page.locator('input[name="sylius_product[enabled]"]')).not.toBeChecked();

        // One variant per delivery type, so the customer can pick virtual or physical straight away
        const productId = /\/admin\/products\/(\d+)\/edit/.exec(page.url())[1];
        await page.goto(`/admin/products/${productId}/variants/`);
        await expect(page.locator('table tbody tr')).toHaveCount(2);
    });
});
