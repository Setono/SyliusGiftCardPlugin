/**
 * Helpers for finding the seeded records the specs act on.
 *
 * The fixtures generate codes and ids, so nothing here may hardcode them; every spec looks its subject up
 * through the admin grids instead. That keeps the suite working against a freshly seeded database.
 */

const { expect } = require('@playwright/test');
const { GRID_ROWS } = require('./admin');
const { clickAndWaitForPage } = require('./navigation');

/**
 * Returns the id of the first row in an admin grid, taken from its show/edit link.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} indexUrl
 * @param {RegExp} hrefPattern must capture the id in group 1
 */
async function firstIdFromGrid(page, indexUrl, hrefPattern) {
    await page.goto(indexUrl);

    const hrefs = await page.locator(`${GRID_ROWS} a`).evaluateAll((links) => links.map((l) => l.getAttribute('href') ?? ''));

    for (const href of hrefs) {
        const match = hrefPattern.exec(href);
        if (null !== match) {
            return match[1];
        }
    }

    throw new Error(`No link matching ${hrefPattern} found in the grid at ${indexUrl}`);
}

/**
 * @param {import('@playwright/test').Page} page
 */
function firstGiftCardId(page) {
    return firstIdFromGrid(page, '/admin/gift-cards/', /\/admin\/gift-cards\/(\d+)$/);
}

/**
 * @param {import('@playwright/test').Page} page
 */
function firstDesignId(page) {
    return firstIdFromGrid(page, '/admin/gift-card-designs/', /\/admin\/gift-card-designs\/(\d+)\/edit$/);
}

/**
 * The code of a gift card, as the admin shows it - grouped for reading. The fixtures generate codes, so it is
 * read off the show page rather than known up front.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} id
 */
async function giftCardCode(page, id) {
    await page.goto(`/admin/gift-cards/${id}`);

    const row = page.locator('table.ui.table tr').filter({ has: page.locator('td strong', { hasText: /^Code$/ }) });

    return (await row.locator('td').nth(1).innerText()).trim();
}

/** @type {{simple: string|null, configurable: string|null, giftCard: string|null, ordinary: string|null}|null} */
let productCache = null;

/**
 * The ids of the products on one page of the products grid
 *
 * @param {import('@playwright/test').Page} page
 * @param {number} number
 * @returns {Promise<string[]>}
 */
async function productIdsOnGridPage(page, number) {
    await page.goto(`/admin/products/?limit=50&page=${number}`);

    const ids = await page.locator(`${GRID_ROWS} a[href*="/admin/products/"]`).evaluateAll((links) =>
        links.map((link) => /\/admin\/products\/(\d+)\/edit$/.exec(link.getAttribute('href') ?? '')?.[1] ?? '').filter((id) => '' !== id),
    );

    return [...new Set(ids)];
}

/**
 * Products of each kind the specs need, by id:
 *
 * - `simple` and `configurable`, whose edit pages take different branches in the details tab (only the configurable
 *   one reaches the options autocomplete), so both have to be covered;
 * - `giftCard`, an enabled product flagged as a gift card, and `ordinary`, an enabled product that is not. The flag is
 *   the product form's checkbox, which every product's edit page renders, so it is its state that tells them apart.
 *
 * Resolved by walking the whole products grid and opening candidates until every kind is found, because the fixtures
 * do not guarantee ids and specs add products of their own. The result is cached for the run, as it costs a page load
 * per product opened.
 *
 * @param {import('@playwright/test').Page} page an authenticated admin page
 * @returns {Promise<{simple: string|null, configurable: string|null, giftCard: string|null, ordinary: string|null}>}
 */
async function productIdsByKind(page) {
    if (null !== productCache) {
        return productCache;
    }

    /** @type {{simple: string|null, configurable: string|null, giftCard: string|null, ordinary: string|null}} */
    const result = { simple: null, configurable: null, giftCard: null, ordinary: null };
    const complete = () => Object.values(result).every((id) => null !== id);
    const opened = new Set();

    for (let number = 1; !complete(); number++) {
        // a page past the last one lists nothing new, whether the grid answers it with an empty page or the last one
        const ids = (await productIdsOnGridPage(page, number)).filter((id) => !opened.has(id));
        if (0 === ids.length) {
            break;
        }

        for (const id of ids) {
            opened.add(id);
            await page.goto(`/admin/products/${id}/edit`);

            const isGiftCard = await page.locator('input[name="sylius_product[giftCard]"]').isChecked();
            const isEnabled = await page.locator('input[name="sylius_product[enabled]"]').isChecked();
            // The variant shipping toggle only exists for simple products, so its presence is what distinguishes
            // the two branches of the details tab
            const isSimple = 0 < (await page.locator('input[name*="[variant]"][name*="[shippingRequired]"]').count());

            if (isSimple) {
                result.simple ??= id;
            } else {
                result.configurable ??= id;
            }
            if (isEnabled && isGiftCard) {
                result.giftCard ??= id;
            }
            if (isEnabled && !isGiftCard) {
                result.ordinary ??= id;
            }

            if (complete()) {
                break;
            }
        }
    }

    productCache = result;

    return result;
}

/**
 * The path of the product's page in the shop, taken from the "Show product in shop page" button of its edit page,
 * or null while the shop does not show the product (it is disabled, or in no enabled channel)
 *
 * Sylius renders that button in two shapes (`@SyliusAdmin/Product/_showInShopButton.html.twig`), the hook on the outer
 * element of either: a link for a product in one enabled channel (a disabled one, to `#`, for a product the shop does
 * not show), and a dropdown of links, one per channel, for a product in several. Of those, the first that leads
 * somewhere is taken
 *
 * @param {import('@playwright/test').Page} page an authenticated admin page
 * @param {string} id
 * @returns {Promise<string|null>}
 */
async function productShopPath(page, id) {
    await page.goto(`/admin/products/${id}/edit`);

    const button = page.locator('[data-test-show-product-in-shop-page]');
    await expect(button, `the edit page of product ${id} has no "Show product in shop page" button`).toHaveCount(1);

    const isLink = 'A' === (await button.evaluate((element) => element.tagName));
    const link = isLink ? button : button.locator('.menu a.item:not(.disabled)').first();
    if (0 === (await link.count()) || (await link.evaluate((element) => element.classList.contains('disabled')))) {
        return null;
    }

    const href = (await link.getAttribute('href')) ?? '#';

    // a link to the channel's hostname, of which only the path is the same on the application under test
    return '#' === href ? null : new URL(href, page.url()).pathname;
}

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
        .locator(GRID_ROWS, { hasText: channelCode })
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
 * The email of a customer the shop knows, read off the customers grid
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<string>}
 */
async function anyCustomerEmail(page) {
    await page.goto('/admin/customers/');

    const email = (await page.locator(`${GRID_ROWS} > td`).allInnerTexts()).map((text) => text.trim()).find((text) => /^\S+@\S+$/.test(text));
    if (undefined === email) {
        throw new Error('The shop has no customer to issue a card to');
    }

    return email;
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
    const listed = await page.locator(`${GRID_ROWS} > td:first-child`).allInnerTexts();
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
    await clickAndWaitForPage(page, page.locator('form[name="sylius_currency"] button[type="submit"]').first());
    await expect(page, `creating the currency ${pick} should have led away from the form`).not.toHaveURL(/\/admin\/currencies\/new$/);

    return pick;
}

module.exports = {
    anyCustomerEmail,
    channelBaseCurrencyCode,
    currencyOtherThan,
    firstDesignId,
    firstGiftCardId,
    giftCardCode,
    productIdsByKind,
    productShopPath,
};
