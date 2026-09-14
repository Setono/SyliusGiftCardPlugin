/**
 * The base currency of the channel with the given code, read off the channel's edit form. Sylius keeps every order
 * amount in it, which is why a gift card can only be issued in it.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} channelCode
 * @returns {Promise<string>}
 */
async function channelBaseCurrencyCode(page, channelCode) {
    await page.goto('/admin/channels/');

    const editUrl = await page
        .locator('table tbody tr', { hasText: channelCode })
        .locator('a[href$="/edit"]')
        .first()
        .getAttribute('href');

    if (null === editUrl) {
        throw new Error(`No channel with code ${channelCode} in the channels grid`);
    }

    await page.goto(editUrl);

    const code = await page.locator('select[name*="[baseCurrency]"]').inputValue();
    if ('' === code) {
        throw new Error(`The channel ${channelCode} has no base currency`);
    }

    return code;
}

/**
 * Makes sure the shop knows at least one currency other than the given one, creating it through the admin when the
 * fixtures seeded only the channel's own, and returns its code.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} except
 * @returns {Promise<string>}
 */
async function currencyOtherThan(page, except) {
    await page.goto('/admin/currencies/');
    const listed = await page.locator('table tbody tr td:first-child').allInnerTexts();
    const existing = listed.map((text) => text.trim()).find((code) => '' !== code && code !== except);
    if (undefined !== existing) {
        return existing;
    }

    await page.goto('/admin/currencies/new');
    const select = page.locator('select[name="sylius_currency[code]"]');
    const options = await select.locator('option').evaluateAll((all) => all.map((o) => o.value));
    const pick = options.find((code) => '' !== code && code !== except);
    if (undefined === pick) {
        throw new Error('No currency other than the base currency can be created');
    }

    await select.selectOption(pick);
    await page.getByRole('button', { name: /create/i }).first().click();
    await page.waitForURL('**/admin/currencies/**');

    return pick;
}

/** @type {{simple: string|null, configurable: string|null, giftCard: string|null}|null} */
let productCache = null;

/**
 * Products whose edit page must render. `simple` and `configurable` take different branches in the details
 * tab, and only the configurable one reaches the options autocomplete, so both have to be covered.
 *
 * Resolved by walking the products grid and opening candidates, because the fixtures do not guarantee ids.
 * The result is cached for the run — it costs several page loads.
 *
 * @param {import('@playwright/test').Page} page an authenticated admin page
 * @returns {Promise<{simple: string|null, configurable: string|null, giftCard: string|null}>}
 */
async function productIdsByKind(page) {
    if (null !== productCache) {
        return productCache;
    }

    const ids = [];
    for (let p = 1; p <= 3; p++) {
        await page.goto(`/admin/products/?page=${p}`);
        const found = await page.locator('a[href*="/admin/products/"]').evaluateAll((links) =>
            links
                .map((l) => /\/admin\/products\/(\d+)\/edit$/.exec(l.getAttribute('href') ?? ''))
                .filter((m) => null !== m)
                .map((m) => m[1]),
        );
        if (0 === found.length) {
            break;
        }
        ids.push(...found);
    }

    const result = { simple: null, configurable: null, giftCard: null };

    for (const id of [...new Set(ids)]) {
        await page.goto(`/admin/products/${id}/edit`);

        const isGiftCard = 0 < await page.locator('input[name*="[giftCard]"]').count();
        // The variant shipping toggle only exists for simple products, so its presence is what distinguishes
        // the two branches of the details tab
        const isSimple = 0 < await page.locator('input[name*="[variant]"][name*="[shippingRequired]"]').count();

        if (isGiftCard && null === result.giftCard) {
            result.giftCard = id;
        }
        if (isSimple && null === result.simple) {
            result.simple = id;
        }
        if (!isSimple && null === result.configurable) {
            result.configurable = id;
        }

        if (null !== result.simple && null !== result.configurable && null !== result.giftCard) {
            break;
        }
    }

    productCache = result;

    return result;
}

module.exports = {
    channelBaseCurrencyCode,
    currencyOtherThan, firstGiftCardId, firstDesignId, productIdsByKind };
