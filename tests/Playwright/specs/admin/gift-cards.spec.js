const { test, expect } = require('@playwright/test');
const { firstGiftCardId, firstDesignId, channelCurrencyCodes } = require('../support/fixtures');

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
     * The currency denominates the balance and every ledger row, none of which carry a currency of their
     * own, so switching it on a live card silently revalues it. Like the channel it is chosen while the
     * card is being issued; afterwards it is shown, because the amounts mean nothing without it, but locked.
     */
    test('the currency can only be chosen while issuing a card', async ({ page }) => {
        const id = await firstGiftCardId(page);

        await page.goto(`/admin/gift-cards/${id}/edit`);
        const onEdit = page.locator('select[name*="[currencyCode]"]');
        await expect(onEdit).toHaveCount(1);
        await expect(onEdit).toBeDisabled();

        await page.goto('/admin/gift-cards/new');
        const onNew = page.locator('select[name*="[currencyCode]"]');
        await expect(onNew).toHaveCount(1);
        await expect(onNew).toBeEnabled();
    });

    /**
     * The list offers every currency the shop knows, but an order is only ever priced in one of its
     * channel's currencies, so a card issued outside them can never be redeemed. The form is the only
     * place where an admin can find that out before the customer does.
     */
    test('a currency the channel does not offer is rejected when issuing a card', async ({ page }) => {
        await page.goto('/admin/gift-cards/new');

        const channelCode = await page.locator('select[name*="[channel]"]').inputValue();
        const offered = await channelCurrencyCodes(page, channelCode);

        await page.goto('/admin/gift-cards/new');
        const currency = page.locator('select[name*="[currencyCode]"]');
        const codes = await currency.locator('option').evaluateAll((options) => options.map((o) => o.value));
        const foreign = codes.find((code) => '' !== code && !offered.includes(code));
        expect(foreign, `every currency on the form is one of ${channelCode}'s`).toBeTruthy();

        await currency.selectOption(foreign);
        await page.locator('input[name*="[amount]"]').fill('100');
        await page.getByRole('button', { name: /create/i }).first().click();

        // The message has to be translated, not a raw key: constraint messages resolve in the validators domain
        await expect(page.locator('.sylius-validation-error').first())
            .toContainText(/is not available in the channel/i);
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

        await page.goto(`/admin/gift-cards/${id}`);

        // the details table row labelled "Code"; the seeded codes are generated, so the value is discovered, not known
        const codeRow = page.locator('table tr').filter({ has: page.locator('td strong', { hasText: /^Code$/ }) });
        const displayed = (await codeRow.locator('td').nth(1).innerText()).trim();

        expect(displayed).toMatch(/^([A-Z0-9]{4}-)*[A-Z0-9]{1,4}$/);
    });

    /**
     * A card created disabled or expired is not emailed on creation, so the admin needs a way to send it once
     * it is usable — and a way to resend one the customer lost. Sending reaches the customer, so it may only
     * happen through a POST carrying a CSRF token: a link would be followed by a browser prefetch.
     */
    test('a gift card can be emailed from the show page', async ({ page }) => {
        const id = await firstGiftCardId(page);

        await page.goto(`/admin/gift-cards/${id}`);

        const form = page.locator(`form[action="/admin/gift-cards/${id}/send-email"]`);
        await expect(form).toHaveCount(1);
        await expect(form.locator('input[name="_csrf_token"]')).toHaveCount(1);

        // Whether the card reaches anybody depends on the card, so read the answer off the page rather than
        // assuming what the fixtures seeded
        const hasCustomer = 0 === await page.getByText('No customer', { exact: true }).count();

        await form.locator('button[type="submit"]').click();

        await expect(page).toHaveURL(new RegExp(`/admin/gift-cards/${id}$`));
        const flash = page.locator('.sylius-flash-message').first();
        await expect(flash).toBeVisible();
        await expect(flash).toContainText(hasCustomer ? /emailed to the customer/i : /no customer email address/i);
    });

    test('the grid offers sending as a POST, not a link', async ({ page }) => {
        await page.goto('/admin/gift-cards/');

        const forms = page.locator('form[action$="/send-email"]');
        await expect(forms.first()).toBeVisible();
        await expect(forms.first().locator('input[name="_csrf_token"]')).toHaveCount(1);
        // A link would let a prefetch send the card behind the admin's back
        await expect(page.locator('a[href$="/send-email"]')).toHaveCount(0);
    });

    test('a gift card is not emailed by a GET or without a CSRF token', async ({ page }) => {
        const id = await firstGiftCardId(page);

        const get = await page.request.get(`/admin/gift-cards/${id}/send-email`, { maxRedirects: 0 });
        expect(get.status()).toBe(405);

        const post = await page.request.post(`/admin/gift-cards/${id}/send-email`, {
            form: { _csrf_token: 'forged' },
            maxRedirects: 0,
        });
        expect(post.status()).toBe(403);
    });

    /**
     * The checkbox promises an email that a disabled or expired card never gets, so the form has to say when
     * the notification is actually sent
     */
    test('the notification checkbox explains when nothing is sent', async ({ page }) => {
        await page.goto('/admin/gift-cards/new');

        await expect(page.locator('[name*="[sendNotificationEmail]"]')).toHaveCount(1);
        await expect(page.getByText(/only sent when the gift card is usable/i)).toBeVisible();
    });

    /**
     * The amount is written into the gift card while the form is submitted, before validation runs, so a
     * blank amount used to reach the non nullable setter and end the request in a 500
     */
    test('issuing a card without an amount is a validation error, not a crash', async ({ page }) => {
        await page.goto('/admin/gift-cards/new');

        await page.locator('input[name$="[amount]"]').fill('');

        const [response] = await Promise.all([
            page.waitForResponse((r) => r.request().method() === 'POST'),
            page.locator('form[name="setono_sylius_gift_card_gift_card"] button[type="submit"]').first().click(),
        ]);

        expect(response.status()).toBeLessThan(500);
        await expect(page.locator('.sylius-validation-error').first()).toBeVisible();
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

    /**
     * The position too was written into a non nullable setter while the form was submitted, so saving a design
     * without a position ended in a 500. The setter now accepts null and validation reports the blank field
     */
    test('a design without a position is a validation error, not a crash', async ({ page }) => {
        const id = await firstDesignId(page);

        await page.goto(`/admin/gift-card-designs/${id}/edit`);
        await page.locator('input[name$="[position]"]').fill('');

        const [response] = await Promise.all([
            page.waitForResponse((r) => r.request().method() === 'POST'),
            page
                .locator('form[name="setono_sylius_gift_card_gift_card_design"] button[type="submit"]')
                .first()
                .click(),
        ]);

        expect(response.status()).toBeLessThan(500);
        await expect(page.locator('.sylius-validation-error').first()).toBeVisible();
    });

    test('a design preview PDF is generated', async ({ page }) => {
        const id = await firstDesignId(page);

        const response = await page.request.get(`/admin/gift-card-designs/${id}/preview-pdf`);

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('application/pdf');
        expect((await response.body()).subarray(0, 5).toString()).toBe('%PDF-');
    });
});
