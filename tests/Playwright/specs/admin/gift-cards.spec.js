const { test, expect } = require('@playwright/test');
const { firstGiftCardId, firstDesignId } = require('../support/fixtures');

test.describe('admin gift cards', () => {
    test('the index renders and offers the plugin actions', async ({ page }) => {
        const response = await page.goto('/admin/gift-cards/');

        expect(response?.status()).toBe(200);
        await expect(page.locator('table tbody tr').first()).toBeVisible();

        // Designs and the balance report were moved out of the admin menu onto this page
        await expect(page.locator('a[href="/admin/gift-card-designs/"]')).toBeVisible();
        await expect(page.locator('a[href="/admin/gift-cards/balance"]')).toBeVisible();
    });

    test('the plugin takes a single admin menu entry', async ({ page }) => {
        await page.goto('/admin/gift-cards/');

        const menu = page.locator('.sylius-admin-menu');
        await expect(menu.locator('a[href="/admin/gift-cards/"]')).toHaveCount(1);
        // These two live on the gift cards page now, not in the menu
        await expect(menu.locator('a[href="/admin/gift-card-designs/"]')).toHaveCount(0);
        await expect(menu.locator('a[href="/admin/gift-cards/balance"]')).toHaveCount(0);
    });

    test('a gift card can be shown and edited', async ({ page }) => {
        const id = await firstGiftCardId(page);

        const show = await page.goto(`/admin/gift-cards/${id}`);
        expect(show?.status()).toBe(200);

        const edit = await page.goto(`/admin/gift-cards/${id}/edit`);
        expect(edit?.status()).toBe(200);
    });

    test('the balance report renders and leads back to gift cards', async ({ page }) => {
        const response = await page.goto('/admin/gift-cards/balance');

        expect(response?.status()).toBe(200);
        // The report is only reachable from the gift cards page, so the crumbs have to lead back
        await expect(page.locator('a[href="/admin/gift-cards/"]').first()).toBeVisible();
    });

    test('a gift card PDF can be downloaded', async ({ page }) => {
        const id = await firstGiftCardId(page);

        const response = await page.request.get(`/admin/gift-cards/${id}/pdf`);

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('application/pdf');
        expect((await response.body()).subarray(0, 5).toString()).toBe('%PDF-');
    });
});

test.describe('admin gift card designs', () => {
    test('the index renders and leads back to gift cards', async ({ page }) => {
        const response = await page.goto('/admin/gift-card-designs/');

        expect(response?.status()).toBe(200);
        await expect(page.locator('a[href="/admin/gift-cards/"]').first()).toBeVisible();
    });

    test('a design can be edited', async ({ page }) => {
        const id = await firstDesignId(page);

        const response = await page.goto(`/admin/gift-card-designs/${id}/edit`);

        expect(response?.status()).toBe(200);
    });

    test('a design preview PDF is generated', async ({ page }) => {
        const id = await firstDesignId(page);

        const response = await page.request.get(`/admin/gift-card-designs/${id}/preview-pdf`);

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('application/pdf');
        expect((await response.body()).subarray(0, 5).toString()).toBe('%PDF-');
    });
});
