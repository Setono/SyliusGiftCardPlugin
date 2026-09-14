const { test, expect } = require('@playwright/test');
const { firstGiftCardId, firstDesignId, giftCardCode } = require('../support/fixtures');
const { pdfPageCount, pdfText } = require('../support/pdf');

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

    /**
     * Deducting more than the card holds used to reach the balance operator, which asserts and returns a 500.
     * It is ordinary user error, so it has to come back as a field error on a rendered form
     */
    test('deducting more than the balance is a validation error, not a crash', async ({ page }) => {
        const id = await firstGiftCardId(page);

        await page.goto(`/admin/gift-cards/${id}/adjust-balance`);
        await page.locator('input[name$="[amount]"]').fill('-99999');
        await page.locator('textarea[name$="[reason]"]').fill('trying to overdraw');
        await page.getByRole('button', { name: /save|adjust/i }).first().click();

        await expect(page.locator('.sylius-validation-error').first()).toBeVisible();
        // The message has to be translated, not a raw key: constraint messages resolve in the validators domain
        await expect(page.locator('.sylius-validation-error').first())
            .toContainText(/Deducting more than the gift card holds/i);
    });

    /**
     * Codes are grouped in fours for reading wherever they are shown (GiftCardCodeNormalizer::format()), so the
     * show page must not print the stored code as one unbroken run
     */
    test('the show page groups the code for reading', async ({ page }) => {
        const id = await firstGiftCardId(page);

        // the seeded codes are generated, so the value is discovered, not known
        expect(await giftCardCode(page, id)).toMatch(/^([A-Z0-9]{4}-)*[A-Z0-9]{1,4}$/);
    });

    /**
     * The back of the card is the only place the customer finds the code again, so it is not enough that the
     * endpoint answers with a valid PDF: the code and the redemption copy have to be drawn on it
     */
    test('a gift card PDF can be downloaded', async ({ page }) => {
        const id = await firstGiftCardId(page);
        const code = await giftCardCode(page, id);

        const response = await page.request.get(`/admin/gift-cards/${id}/pdf`);

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('application/pdf');

        const body = await response.body();
        expect(body.subarray(0, 5).toString()).toBe('%PDF-');

        // front and back, drawn on the configured page size (A6 landscape by default)
        expect(pdfPageCount(body)).toBe(2);
        expect(body.toString('latin1')).toContain('/MediaBox [0.000 0.000 419.530 297.640]');

        const text = pdfText(body);
        expect(text).toContain('REDEMPTION CODE');
        expect(text).toContain(code);
        // the card is redeemed in this shop's checkout, nowhere else
        expect(text).not.toContain('in store');
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

    /**
     * The seeded design brings a back image, which used to be the whole back of the card: no code, no
     * redemption copy, no terms. The preview has to show the same back a customer would get
     */
    test('a design preview PDF is generated', async ({ page }) => {
        const id = await firstDesignId(page);

        const response = await page.request.get(`/admin/gift-card-designs/${id}/preview-pdf`);

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('application/pdf');

        const body = await response.body();
        expect(body.subarray(0, 5).toString()).toBe('%PDF-');
        expect(pdfPageCount(body)).toBe(2);

        const text = pdfText(body);
        expect(text).toContain('REDEMPTION CODE');
        expect(text).toContain('HOW TO REDEEM');
        // the preview card's code is generated, so only its shape is known
        expect(text).toMatch(/[A-Z0-9]{4}-[A-Z0-9]{4}/);
    });
});
