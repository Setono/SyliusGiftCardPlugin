/**
 * Helpers for finding the seeded records the specs act on.
 *
 * The fixtures generate codes and ids, so nothing here may hardcode them; every spec looks its subject up
 * through the admin grids instead. That keeps the suite working against a freshly seeded database.
 */

/**
 * Returns the id of the first row in an admin grid, taken from its show/edit link.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} indexUrl
 * @param {RegExp} hrefPattern must capture the id in group 1
 */
async function firstIdFromGrid(page, indexUrl, hrefPattern) {
    await page.goto(indexUrl);

    const hrefs = await page.locator('table a').evaluateAll((links) => links.map((l) => l.getAttribute('href') ?? ''));

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

module.exports = { firstGiftCardId, firstDesignId, productIdsByKind };
