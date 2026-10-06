const { test, expect } = require('@playwright/test');
const { firstDesignId, firstGiftCardId } = require('../support/fixtures');
const { blankIcons } = require('../support/icons');

/**
 * Every icon the plugin's admin pages show has to be one Sylius' admin stylesheet draws. A name it has no rule for
 * renders as a blank without any error, so it is only ever noticed by looking: the designs index showed an empty circle
 * in its header, for `palette`, until somebody did. Each page is checked as a whole, since the header, the buttons, the
 * grid actions, the status labels, the menu entry and the top bar all name icons. That takes in Sylius' own icons on
 * the page as well, which all draw.
 */

/** @type {Array<[string, (page: import('@playwright/test').Page) => Promise<string>]>} */
const PAGES = [
    ['gift card index', async () => '/admin/gift-cards/'],
    ['gift card create page', async () => '/admin/gift-cards/new'],
    ['gift card show page', async (page) => `/admin/gift-cards/${await firstGiftCardId(page)}`],
    ['gift card edit page', async (page) => `/admin/gift-cards/${await firstGiftCardId(page)}/edit`],
    ['adjust balance page', async (page) => `/admin/gift-cards/${await firstGiftCardId(page)}/adjust-balance`],
    ['balance report', async () => '/admin/gift-cards/balance'],
    ['design index', async () => '/admin/gift-card-designs/'],
    ['design create page', async () => '/admin/gift-card-designs/new'],
    ['design edit page', async (page) => `/admin/gift-card-designs/${await firstDesignId(page)}/edit`],
];

test.describe('admin icons', () => {
    for (const [name, path] of PAGES) {
        test(`every icon on the ${name} draws`, async ({ page }) => {
            const url = await path(page);

            const response = await page.goto(url);
            expect(response?.status(), url).toBe(200);
            // The header's icon is the one that went missing, so there has to be one to check. Sylius' header macro has
            // no hook, so the header is found by its Semantic UI classes, the way a table without one is
            await expect(page.locator('h1.ui.header i.icon')).toHaveCount(1);

            expect(await blankIcons(page), `the icons on ${url} that draw nothing`).toEqual([]);
        });
    }
});
