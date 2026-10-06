const { test, expect } = require('@playwright/test');
const { signInAsAdministrator } = require('../support/admin');
const { productIdsByKind, productShopPath } = require('../support/fixtures');
const { moneyInCents, typedAmount } = require('../support/money');
const { GIFT_CARD_INFORMATION, giftCardProductPath, shopLocale, shopLocales, submitAddToCart } = require('../support/shop');

/**
 * The gift card form is added to the add to cart form by a form type extension, so it has to appear on gift
 * card products and stay away from every other product.
 *
 * The product page is the one the shop links to as the gift card product, and the locale the one the shop sends a
 * visitor to, so neither a slug nor a locale is assumed.
 */
test.describe('shop gift card product', () => {
    /**
     * The shop page of a product of the given kind, by the gift card flag the admin shows for it, so which products
     * carry the form is not decided by looking for the form
     *
     * @param {import('@playwright/test').Browser} browser
     * @param {'giftCard'|'ordinary'} kind
     */
    async function shopPathOfProduct(browser, kind) {
        const admin = await signInAsAdministrator(browser);
        try {
            const id = (await productIdsByKind(admin.page))[kind];
            expect(id, `no enabled ${kind} product was seeded`).not.toBeNull();

            const path = await productShopPath(admin.page, /** @type {string} */ (id));
            expect(path, `the shop does not show the ${kind} product ${id}`).not.toBeNull();

            return /** @type {string} */ (path);
        } finally {
            await admin.close();
        }
    }

    test('a product flagged as a gift card renders the gift card form', async ({ page, browser }) => {
        const response = await page.goto(await shopPathOfProduct(browser, 'giftCard'));

        expect(response?.status()).toBe(200);
        await expect(page.locator('form[name="sylius_add_to_cart"]')).toBeVisible();

        // amount, message and design are what the customer fills in
        await expect(page.locator(`${GIFT_CARD_INFORMATION}[name*="[amount]"]`)).toBeVisible();
        await expect(page.locator(`${GIFT_CARD_INFORMATION}[name*="[customMessage]"]`)).toBeVisible();
        // the design picker is only rendered when the channel has designs, which the fixtures seed
        await expect(page.locator('[data-js-gift-card-design-picker]')).toBeVisible();
        expect(await page.locator(`${GIFT_CARD_INFORMATION}[name*="[design]"]`).count()).toBeGreaterThan(0);
    });

    /**
     * The design picker is a radio group. A `<label for>` can only ever point at a single control, so the group
     * is named by a legend instead, and which design is picked has to be visible on the choice itself.
     */
    test('the design picker is a named group with a visible selection', async ({ page }) => {
        await page.goto(await giftCardProductPath(page));

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
        const response = await page.request.get(await giftCardProductPath(page));
        expect(response.status()).toBe(200);
        const html = await response.text();

        // the element itself still has to be there: the picker sets its src when a design with an image is chosen
        expect(html).toContain('class="ssgc-card__bg"');
        expect(html, 'an empty img src makes the browser fetch the page again as an image').not.toMatch(/<img[^>]*src=""/);
    });

    test('a product not flagged as a gift card renders no gift card form', async ({ page, browser }) => {
        const response = await page.goto(await shopPathOfProduct(browser, 'ordinary'));

        expect(response?.status()).toBe(200);
        // a product the customer can buy, so the form the extension would add to is there
        await expect(page.locator('form[name="sylius_add_to_cart"]')).toBeVisible();
        await expect(page.locator(GIFT_CARD_INFORMATION)).toHaveCount(0);
        await expect(page.locator('#setono-gift-card-information')).toHaveCount(0);
    });

    test('the amount field tells the customer the limits and asks for a numeric keypad', async ({ page }) => {
        await page.goto(await giftCardProductPath(page));

        const amount = page.locator(`${GIFT_CARD_INFORMATION}[name*="[amount]"]`).first();
        await expect(amount).toHaveAttribute('inputmode', 'decimal');

        // The help the form theme renders next to the field carries the formatted limits, so the customer
        // does not have to submit the form to find out what they are
        const amountId = await amount.getAttribute('id');
        const help = page.locator(`#${amountId}_help`);
        await expect(help).toBeVisible();
        // The limits are configurable, so this asserts a money figure is quoted rather than a particular one
        await expect(help).toHaveText(/\d/);
    });

    /**
     * Sylius only prices a line once it is in the cart, so the field used to start at 0.00 — an amount the shop
     * refuses — right under the price the page prints. It starts at that price now, and the preview shows it before
     * the customer has typed anything.
     */
    test('the amount field starts at the price the page shows', async ({ page }) => {
        await page.goto(await giftCardProductPath(page));

        const price = moneyInCents(await page.locator('#product-price').innerText());
        expect(price, 'the gift card product has no price to start from').toBeGreaterThan(0);

        const amount = page.locator(`${GIFT_CARD_INFORMATION}[name*="[amount]"]`).first();
        expect(moneyInCents(await amount.inputValue())).toBe(price);

        const previews = page.locator('#setono-gift-card-information [data-js-gc-amount]');
        expect(await previews.count(), 'the preview does not render an amount').toBeGreaterThan(0);
        for (let i = 0; i < await previews.count(); i++) {
            // the script fills the preview in once the page has loaded
            await expect(previews.nth(i)).toHaveText(/\d/);
            expect(moneyInCents(/** @type {string} */ (await previews.nth(i).textContent())), 'the preview').toBe(price);
        }
    });

    test('a gift card can be bought at the amount the field starts at', async ({ page }) => {
        await page.goto(await giftCardProductPath(page));

        const price = moneyInCents(await page.locator('#product-price').innerText());

        // the customer leaves the amount alone and only picks what the form preselects
        await submitAddToCart(page);

        const line = page.locator('#sylius-cart-items tbody tr').first();
        expect(moneyInCents(await line.locator('.sylius-unit-price').innerText())).toBe(price);
    });

    /**
     * The preview parses the typed amount in the locale the template writes onto its container: the one being
     * browsed. A shop only renders the locales its channel offers, and the seeded channel deliberately offers one:
     * Sylius' order fixture picks each demo order's locale from the channel and loads the products with only that
     * locale's translation, so a second channel locale makes loading the fixtures fail at random. A locale writing
     * decimals the other way is therefore written into the real product page on its way to the browser; the markup,
     * the field and the script are the shop's own. One of the two writes them with a comma, the separator a naive
     * parseFloat truncates to whole units.
     */
    test('the preview reads the amount with the decimal separator of the locale', async ({ page }) => {
        const productPage = await giftCardProductPath(page);
        const browsed = (await shopLocale(page)).replace('_', '-');
        const container = page.locator('#setono-gift-card-information');
        const amount = page.locator(`${GIFT_CARD_INFORMATION}[name*="[amount]"]`).first();

        // The script is told the locale being browsed, and reads that locale's decimal separator
        await page.goto(productPage);
        await expect(container).toHaveAttribute('data-locale', browsed);
        const separator = await decimalSeparator(page, browsed);
        await amount.fill(`50${separator}50`);
        await expectPreviewAmount(page, `50${separator}50`);

        const other = ',' === separator ? 'en-US' : 'fr-FR';
        const otherSeparator = await decimalSeparator(page, other);
        expect(otherSeparator, `${other} should write decimals unlike ${browsed}`).not.toBe(separator);

        await page.route((url) => url.pathname === productPage, async (route) => {
            const response = await route.fetch();
            const html = await response.text();
            await route.fulfill({
                response,
                body: html.replace(/(id="setono-gift-card-information"[^>]*?\sdata-locale=")[^"]*"/, `$1${other}"`),
            });
        });
        await page.goto(productPage);
        await expect(container).toHaveAttribute('data-locale', other);

        await amount.fill(`50${otherSeparator}50`);
        await expectPreviewAmount(page, `50${otherSeparator}50`);
    });

    test('the page renders in every locale the channel offers', async ({ page }) => {
        // Discovered from the locale switcher rather than hardcoded, because which locales a channel has depends on
        // how the application was seeded
        const locales = await shopLocales(page);
        expect(locales.length, 'no locales found in the shop').toBeGreaterThan(0);

        // A missing translation key silently falls back rather than failing, so this asserts the page still renders
        // in each locale, with the gift card form told the locale it is in, not the wording. The gift card product is
        // found in each locale's own shop, as its slug may be translated
        for (const locale of locales) {
            const response = await page.goto(await giftCardProductPath(page, locale));
            expect(response?.status(), `${locale} product page`).toBe(200);
            await expect(page.locator('#setono-gift-card-information'), `${locale} product page`).toHaveAttribute(
                'data-locale',
                locale.replace('_', '-'),
            );
        }
    });

    test('a gift card can be added to the cart', async ({ page }) => {
        const productPage = await giftCardProductPath(page);
        await page.goto(productPage);

        await page.locator(`${GIFT_CARD_INFORMATION}[name*="[amount]"]`).first().fill(typedAmount(5000));
        await page.locator(`${GIFT_CARD_INFORMATION}[name*="[customMessage]"]`).first().fill('Happy birthday');

        await submitAddToCart(page);

        // The cart holds a line for the gift card product itself, not merely some row, at the amount chosen
        const line = page.locator('[data-test-cart-items] tbody tr').filter({ has: page.locator(`a[href="${productPage}"]`) });
        await expect(line).toHaveCount(1);
        expect(moneyInCents(await line.locator('.sylius-unit-price').innerText())).toBe(5000);
    });

    /**
     * The chosen amount is written into the gift card information object while the form is submitted, which
     * happens before validation runs. A blank amount used to reach a non nullable setter and end the request
     * in a 500, and because add to cart posts over AJAX the button simply kept spinning with no message.
     */
    test('a blank amount is reported instead of failing the request', async ({ page }) => {
        await page.goto(await giftCardProductPath(page));

        const amount = page.locator(`${GIFT_CARD_INFORMATION}[name*="[amount]"]`).first();
        await amount.fill('');

        const form = page.locator('form[name="sylius_add_to_cart"]');
        const action = await form.getAttribute('action');
        const [response] = await Promise.all([
            page.waitForResponse((r) => 'POST' === r.request().method() && r.url().endsWith(action ?? '')),
            form.locator('button[type="submit"]').first().click(),
        ]);

        // refused as invalid, and for the amount the customer left blank
        expect(response.status(), 'adding to the cart must be refused as invalid, not fail with a server error').toBe(400);
        const { errors } = await response.json();
        const amountErrors = errors?.form?.errors?.children?.giftCardInformation?.children?.amount?.errors ?? [];
        expect(amountErrors, 'the refusal should be about the amount').toHaveLength(1);

        // Sylius' add to cart script renders the 400 payload into this element
        const validationError = page.locator('#sylius-cart-validation-error');
        await expect(validationError).toBeVisible();
        await expect(validationError).toContainText(amountErrors[0]);
    });

    test('the message field counts down and the preview keeps the line breaks', async ({ page }) => {
        await page.goto(await giftCardProductPath(page));

        const message = page.locator(`${GIFT_CARD_INFORMATION}[name*="[customMessage]"]`).first();
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

    /**
     * The textarea's maxlength and the counter count a line break as one character, but the browser submits it as
     * CR LF. A message the field let the customer type used to be refused as too long once it had line breaks in it
     */
    test('a message with line breaks up to the limit can be added to the cart', async ({ page }) => {
        await page.goto(await giftCardProductPath(page));

        const message = page.locator(`${GIFT_CARD_INFORMATION}[name*="[customMessage]"]`).first();
        const limit = Number(await message.getAttribute('maxlength'));
        expect(limit, 'the message field has no maxlength').toBeGreaterThan(5);

        // Six lines, so five line breaks, filling the limit exactly by the browser's count
        const lineLength = Math.floor((limit - 5) / 6);
        const lines = Array.from({ length: 6 }, () => 'a'.repeat(lineLength));
        lines[5] += 'a'.repeat(limit - 5 - 6 * lineLength);
        const typed = lines.join('\n');
        await message.fill(typed);

        // The field took all of it, and the counter says there is nothing left
        expect(await message.inputValue()).toBe(typed);
        await expect(page.locator('[data-js-gc-message-counter]')).toHaveText(/^\s*0\D/);

        // The amount field starts at the product's price, which the shop sells, so only the message is under test
        const form = page.locator('form[name="sylius_add_to_cart"]');
        const cart = await form.getAttribute('data-redirect');
        const action = await form.getAttribute('action');
        const added = page.waitForResponse((response) => response.request().method() === 'POST'
            && response.url().endsWith(action ?? ''));
        await form.locator('button[type="submit"]').first().click();
        const response = await added;

        // A refused line keeps the customer on the page, so its errors can still be read; an added one sends them on
        const refusal = response.ok() ? '' : await response.text();
        expect(response.ok(), `adding to the cart was refused: ${refusal}`).toBe(true);
        await page.waitForURL(`**${cart}`);
    });

    test('a message of many lines is clamped instead of growing over the card', async ({ page }) => {
        await page.goto(await giftCardProductPath(page));

        const message = page.locator(`${GIFT_CARD_INFORMATION}[name*="[customMessage]"]`).first();
        const limit = Number(await message.getAttribute('maxlength'));

        // The nastiest message that still fits the limit: as many lines as characters allow
        await message.fill(Array.from({ length: Math.floor(limit / 3) }, () => 'ab').join('\n'));

        const preview = await previewMessage(page);
        expect(preview.scrollHeight, 'the message is not clamped').toBeGreaterThan(preview.clientHeight);
        expect(preview.titleOverlapsBrand, 'the title has been pushed into the brand row').toBe(false);
    });

    /**
     * The separator the browser writes decimals with in the given locale, the way the preview script formats them
     *
     * @param {import('@playwright/test').Page} page
     * @param {string} locale a BCP 47 tag, e.g. en-US
     */
    async function decimalSeparator(page, locale) {
        return page.evaluate((tag) => new Intl.NumberFormat(tag).formatToParts(1.5).find((part) => 'decimal' === part.type)?.value ?? '.', locale);
    }

    /**
     * Both the framed and the image layout of the card carry the amount, and whichever is on screen has to show
     * what the customer typed, so every one of them is checked
     *
     * @param {import('@playwright/test').Page} page
     * @param {string} expected the figures, which the preview surrounds with the currency
     */
    async function expectPreviewAmount(page, expected) {
        const previews = page.locator('#setono-gift-card-information [data-js-gc-amount]');
        expect(await previews.count(), 'the preview does not render an amount').toBeGreaterThan(0);
        for (let i = 0; i < await previews.count(); i++) {
            await expect(previews.nth(i)).toContainText(expected);
        }
    }

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

    /**
     * The seeded shop configures no maximum, so an amount used to be held to the minimum only. One above what a gift
     * card's balance column holds, a signed 32-bit integer (at most 21,474,836.47), made the database refuse the cart
     * with a 500, and the button kept spinning. It is refused like a blank amount, on the amount field
     */
    test('an amount more than a gift card can hold is reported instead of failing the request', async ({ page }) => {
        await page.goto(await giftCardProductPath(page));

        const amount = page.locator(`${GIFT_CARD_INFORMATION}[name*="[amount]"]`).first();
        await amount.fill(typedAmount(3000000000));

        const form = page.locator('form[name="sylius_add_to_cart"]');
        const action = await form.getAttribute('action');
        const [response] = await Promise.all([
            page.waitForResponse((r) => 'POST' === r.request().method() && r.url().endsWith(action ?? '')),
            form.locator('button[type="submit"]').first().click(),
        ]);

        expect(response.status(), 'adding to the cart must be refused as invalid, not fail with a server error').toBe(400);
        const { errors } = await response.json();
        const amountErrors = errors?.form?.errors?.children?.giftCardInformation?.children?.amount?.errors ?? [];
        expect(amountErrors, 'the refusal should be about the amount').toHaveLength(1);

        // Sylius' add to cart script renders the 400 payload into this element
        await expect(page.locator('#sylius-cart-validation-error')).toContainText(amountErrors[0]);
    });
});
