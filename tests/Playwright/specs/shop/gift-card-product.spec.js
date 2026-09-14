const { test, expect } = require('@playwright/test');

/**
 * The gift card form is added to the add to cart form by a form type extension, so it has to appear on gift
 * card products and stay away from every other product.
 */
test.describe('shop gift card product', () => {
    const GIFT_CARD_SLUG = 'gift-card';

    /**
     * Discovered from the locale switcher rather than hardcoded, because which locales a channel has
     * depends on how the application was seeded. The switcher links to the locale to switch to, while every
     * other link carries the locale currently being browsed
     */
    const localesOnPage = (page) =>
        page.locator('a[href^="/"]').evaluateAll((links) => {
            const found = [];
            links.forEach((l) => {
                const href = l.getAttribute('href') ?? '';
                const match = /\/switch-locale\/([a-z]{2}_[A-Z]{2})$/.exec(href) ??
                    /^\/([a-z]{2}_[A-Z]{2})\//.exec(href);
                if (null !== match) {
                    found.push(match[1]);
                }
            });
            return [...new Set(found)];
        });

    test('the product page renders the gift card form', async ({ page }) => {
        const response = await page.goto(`/en_US/products/${GIFT_CARD_SLUG}`);

        expect(response?.status()).toBe(200);

        const giftCardFields = page.locator('[name*="giftCardInformation"]');
        await expect(giftCardFields.first()).toBeVisible();

        // amount, message and design are what the customer fills in
        await expect(page.locator('[name*="giftCardInformation"][name*="[amount]"]')).toHaveCount(1);
        await expect(page.locator('[name*="giftCardInformation"][name*="[customMessage]"]')).toHaveCount(1);
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

    test('the amount field tells the customer the limits and asks for a numeric keypad', async ({ page }) => {
        await page.goto(`/en_US/products/${GIFT_CARD_SLUG}`);

        const amount = page.locator('[name*="giftCardInformation"][name*="[amount]"]').first();
        await expect(amount).toHaveAttribute('inputmode', 'decimal');

        // The help the form theme renders next to the field carries the formatted limits, so the customer
        // does not have to submit the form to find out what they are
        const amountId = await amount.getAttribute('id');
        const help = page.locator(`#${amountId}_help`);
        await expect(help).toBeVisible();
        // The limits are configurable, so this asserts a money figure is quoted rather than a particular one
        await expect(help).toHaveText(/\d/);
    });

    test('the preview reads the amount with the decimal separator of the locale', async ({ page }) => {
        await page.goto(`/en_US/products/${GIFT_CARD_SLUG}`);

        // A locale writing decimals with a comma is the one a naive parseFloat truncates to whole units
        const locales = await localesOnPage(page);
        const commaLocale = locales.find(
            (locale) => new Intl.NumberFormat(locale.replace('_', '-')).format(1.1).indexOf(',') !== -1,
        );
        test.skip(undefined === commaLocale, 'the shop offers no locale that uses a decimal comma');

        await page.goto(`/${commaLocale}/products/${GIFT_CARD_SLUG}`);

        const amount = page.locator('[name*="giftCardInformation"][name*="[amount]"]').first();
        await amount.fill('50,50');

        // Both the framed and the image layout of the card carry the amount; whichever is on screen has to
        // show the halves the customer typed, not a truncated 50
        const previews = page.locator('#setono-gift-card-information [data-js-gc-amount]');
        expect(await previews.count(), 'the preview does not render an amount').toBeGreaterThan(0);
        for (let i = 0; i < await previews.count(); i++) {
            await expect(previews.nth(i)).toHaveText(/50,50/);
        }
    });

    test('the page renders in every locale the channel offers', async ({ page }) => {
        await page.goto(`/en_US/products/${GIFT_CARD_SLUG}`);

        const locales = await localesOnPage(page);

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
