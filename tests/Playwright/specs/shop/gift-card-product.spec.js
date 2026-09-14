const { test, expect } = require('@playwright/test');

/**
 * The gift card form is added to the add to cart form by a form type extension, so it has to appear on gift
 * card products and stay away from every other product.
 */
test.describe('shop gift card product', () => {
    const GIFT_CARD_SLUG = 'gift-card';

    test('the product page renders the gift card form', async ({ page }) => {
        const response = await page.goto(`/en_US/products/${GIFT_CARD_SLUG}`);

        expect(response?.status()).toBe(200);

        const giftCardFields = page.locator('[name*="giftCardInformation"]');
        await expect(giftCardFields.first()).toBeVisible();

        // amount, message and design are what the customer fills in
        await expect(page.locator('[name*="giftCardInformation"][name*="[amount]"]')).toHaveCount(1);
        await expect(page.locator('[name*="giftCardInformation"][name*="[customMessage]"]')).toHaveCount(1);
        // the design picker is only rendered when the channel has designs, which the fixtures seed
        await expect(page.locator('[data-js-gift-card-design-picker]')).toBeVisible();
        expect(await page.locator('[name*="giftCardInformation"][name*="[design]"]').count()).toBeGreaterThan(0);
    });

    /**
     * The design picker is a radio group. A `<label for>` can only ever point at a single control, so the group
     * is named by a legend instead, and which design is picked has to be visible on the choice itself.
     */
    test('the design picker is a named group with a visible selection', async ({ page }) => {
        await page.goto(`/en_US/products/${GIFT_CARD_SLUG}`);

        const fieldset = page.locator('fieldset').filter({ has: page.locator('[data-js-gift-card-design-picker]') });
        await expect(fieldset).toHaveCount(1);

        // The legend carries whatever the form type labels the field with, so it is read rather than assumed
        const legend = (await fieldset.locator('legend').first().innerText()).trim();
        expect(legend, 'the design group needs a legend to name it').not.toBe('');
        await expect(page.getByRole('group', { name: legend })).toHaveCount(1);

        // One design is preselected by the form type, and the choice holding it must look different
        const selected = fieldset.locator('.setono-gift-card-design-choice:has(input:checked)');
        await expect(selected).toHaveCount(1);
        const borderColor = await selected.evaluate((el) => getComputedStyle(el).borderColor);
        expect(borderColor, 'the selected design needs a visible state beyond the radio dot').not.toBe('rgba(0, 0, 0, 0)');
    });

    /**
     * `<img src="">` resolves to the page itself, so the browser downloads the whole document again as an
     * image. The preview image is filled in by the design picker and starts out without a src at all.
     *
     * Asserted against the response rather than the live DOM: by the time the page has settled the picker has
     * already set a src, while the wasted request happens as the document is parsed.
     */
    test('the live preview does not render an empty image source', async ({ page }) => {
        const response = await page.request.get(`/en_US/products/${GIFT_CARD_SLUG}`);
        const html = await response.text();

        // the element itself still has to be there: the picker sets its src when a design with an image is chosen
        expect(html).toContain('class="ssgc-card__bg"');
        expect(html, 'an empty img src makes the browser fetch the page again as an image').not.toMatch(/<img[^>]*src=""/);
    });

    test('an ordinary product does not render the gift card form', async ({ page }) => {
        // Take the first product off the homepage that is not the gift card
        await page.goto('/en_US/');
        const hrefs = await page.locator('a[href*="/products/"]').evaluateAll((links) =>
            links.map((l) => l.getAttribute('href') ?? ''),
        );
        const other = hrefs.find((h) => !h.includes(GIFT_CARD_SLUG));
        expect(other, 'no non gift card product found in the shop').toBeTruthy();

        const response = await page.goto(other);

        expect(response?.status()).toBe(200);
        await expect(page.locator('[name*="giftCardInformation"]')).toHaveCount(0);
    });

    test('the page renders in every locale the channel offers', async ({ page }) => {
        await page.goto(`/en_US/products/${GIFT_CARD_SLUG}`);

        // Discovered from the locale switcher rather than hardcoded, because which locales a channel has
        // depends on how the application was seeded
        const locales = await page.locator('a[href^="/"]').evaluateAll((links) => {
            const found = links
                .map((l) => /^\/([a-z]{2}_[A-Z]{2})\//.exec(l.getAttribute('href') ?? ''))
                .filter((m) => null !== m)
                .map((m) => m[1]);
            return [...new Set(found)];
        });

        expect(locales.length, 'no locales found in the shop').toBeGreaterThan(0);

        // A missing translation key silently falls back rather than failing, so this asserts the page still
        // renders in each locale — not the wording
        for (const locale of locales) {
            const response = await page.goto(`/${locale}/products/${GIFT_CARD_SLUG}`);
            expect(response?.status(), `${locale} product page`).toBe(200);
        }
    });

    test('a gift card can be added to the cart', async ({ page }) => {
        await page.goto(`/en_US/products/${GIFT_CARD_SLUG}`);

        const amount = page.locator('[name*="giftCardInformation"][name*="[amount]"]').first();
        await amount.fill('50');

        const message = page.locator('[name*="giftCardInformation"][name*="[customMessage]"]').first();
        if (await message.count() > 0) {
            await message.fill('Happy birthday');
        }

        await page.locator('form[name="sylius_add_to_cart"] button[type="submit"]').first().click();
        await page.waitForLoadState('networkidle');

        const cart = await page.goto('/en_US/cart/');
        expect(cart?.status()).toBe(200);

        // The cart holds a line for the gift card product itself, not merely some row
        await expect(page.locator(`table a[href*="${GIFT_CARD_SLUG}"]`).first()).toBeVisible();
    });
});
