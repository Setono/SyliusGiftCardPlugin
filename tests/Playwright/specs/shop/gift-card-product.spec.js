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

    /**
     * The chosen amount is written into the gift card information object while the form is submitted, which
     * happens before validation runs. A blank amount used to reach a non nullable setter and end the request
     * in a 500, and because add to cart posts over AJAX the button simply kept spinning with no message.
     */
    test('a blank amount is reported instead of failing the request', async ({ page }) => {
        const serverErrors = [];
        page.on('response', (response) => {
            if (response.status() >= 500) {
                serverErrors.push(`${response.status()} ${response.url()}`);
            }
        });

        await page.goto(`/en_US/products/${GIFT_CARD_SLUG}`);

        const amount = page.locator('[name*="giftCardInformation"][name*="[amount]"]').first();
        await amount.fill('');

        await page.locator('form[name="sylius_add_to_cart"] button[type="submit"]').first().click();

        // Sylius' add to cart script renders the 400 payload into this element
        const validationError = page.locator('#sylius-cart-validation-error');
        await expect(validationError).toBeVisible();
        await expect(validationError).toContainText(/blank/i);

        expect(serverErrors, 'adding to the cart must not fail with a server error').toEqual([]);
    });

    test('the message field counts down and the preview keeps the line breaks', async ({ page }) => {
        await page.goto(`/en_US/products/${GIFT_CARD_SLUG}`);

        const message = page.locator('[name*="giftCardInformation"][name*="[customMessage]"]').first();
        const counter = page.locator('[data-js-gc-message-counter]');

        // The limit is configurable, so it is read off the field the plugin rendered rather than hardcoded
        const limit = Number(await message.getAttribute('maxlength'));
        expect(limit, 'the message field has no maxlength').toBeGreaterThan(0);

        // An untouched field has the whole budget left, and the counter is server rendered so it is already
        // correct before the script runs
        await expect(counter).toContainText(String(limit));

        const typed = 'Happy birthday!\nEnjoy your gift.';
        await message.fill(typed);

        await expect(counter).toContainText(String(limit - typed.length));

        const preview = await previewMessage(page);
        expect(preview.text).toBe(typed);
        // white-space: pre-line is what carries the newline through to the PDF as well
        expect(preview.whiteSpace).toBe('pre-line');
        expect(preview.lines, 'the preview collapsed the line break').toBeGreaterThanOrEqual(2);
    });

    test('a message of many lines is clamped instead of growing over the card', async ({ page }) => {
        await page.goto(`/en_US/products/${GIFT_CARD_SLUG}`);

        const message = page.locator('[name*="giftCardInformation"][name*="[customMessage]"]').first();
        const limit = Number(await message.getAttribute('maxlength'));

        // The nastiest message that still fits the limit: as many lines as characters allow
        await message.fill(Array.from({ length: Math.floor(limit / 3) }, () => 'ab').join('\n'));

        const preview = await previewMessage(page);
        expect(preview.scrollHeight, 'the message is not clamped').toBeGreaterThan(preview.clientHeight);
        expect(preview.titleOverlapsBrand, 'the title has been pushed into the brand row').toBe(false);
    });

    /**
     * Reads the message element of whichever card variant is on screen — the design picker decides whether the
     * framed default or the merchant image variant is the visible one — plus how the card laid it out.
     *
     * @param {import('@playwright/test').Page} page
     */
    async function previewMessage(page) {
        return page.evaluate(() => {
            const container = document.getElementById('setono-gift-card-information');
            const nodes = Array.from(container.querySelectorAll('[data-js-gc-message]'));
            const element = nodes.find((node) => node.getClientRects().length > 0) ?? nodes[0];

            const range = document.createRange();
            range.selectNodeContents(element);

            const card = element.closest('.ssgc-card');
            const brand = card.querySelector('.ssgc-card__brand');
            const title = card.querySelector('.ssgc-card__title');
            const overlaps = null !== brand && null !== title && title.getClientRects().length > 0
                && title.getBoundingClientRect().top < brand.getBoundingClientRect().bottom;

            return {
                text: element.textContent,
                whiteSpace: getComputedStyle(element).whiteSpace,
                // One rect per rendered line box, so this is the number of lines the card actually shows
                lines: range.getClientRects().length,
                clientHeight: element.clientHeight,
                scrollHeight: element.scrollHeight,
                titleOverlapsBrand: overlaps,
            };
        });
    }
});
