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

    /**
     * The balance is the ledger's business: it may only be moved through the adjust balance action, which
     * records a transaction. Exposing it on the edit form let an admin move it leaving no trace of why.
     */
    test('the balance can only be set while issuing a card', async ({ page }) => {
        const id = await firstGiftCardId(page);

        await page.goto(`/admin/gift-cards/${id}/edit`);
        await expect(page.locator('[name*="[amount]"]')).toHaveCount(0);

        await page.goto('/admin/gift-cards/new');
        await expect(page.locator('[name*="[amount]"]')).toHaveCount(1);
    });

    /**
     * The ledger is meant to account for the whole balance. Before issuance was recorded, a card that
     * demonstrably held money showed an empty transactions list, so the panel explained nothing.
     */
    test('the transactions list explains the balance', async ({ page }) => {
        const id = await firstGiftCardId(page);

        await page.goto(`/admin/gift-cards/${id}`);

        const rows = page.locator('table tbody tr');
        await expect(rows.first()).toBeVisible();
        await expect(page.getByText('Issued', { exact: false }).first()).toBeVisible();
    });

    test('the adjust balance form is themed', async ({ page }) => {
        const id = await firstGiftCardId(page);

        const response = await page.goto(`/admin/gift-cards/${id}/adjust-balance`);
        expect(response?.status()).toBe(200);

        // Semantic UI scopes its field styling under .ui.form; without the class the form renders unstyled
        await expect(page.locator('form.ui.form')).toHaveCount(1);
        await expect(page.locator('form.ui.form .field')).not.toHaveCount(0);
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
