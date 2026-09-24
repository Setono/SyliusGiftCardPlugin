/**
 * Waiting for the page a click leads to.
 *
 * Forms in Sylius post and redirect, sometimes back to the very page they were sent from (a transition on an order
 * redirects to the order, an invalid checkout step renders itself again), so waiting for a URL would either resolve
 * at once or never. The new document is waited for instead.
 */

const { expect } = require('@playwright/test');

/**
 * Clicks something that submits a form or follows a link, and waits until the page it leads to has loaded. A page
 * that answers with a server error fails right here, rather than as a field the next step cannot find.
 *
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').Locator} target
 */
async function clickAndWaitForPage(page, target) {
    const navigated = page.waitForEvent('framenavigated', (frame) => frame === page.mainFrame());
    await target.click();
    await navigated;
    await page.waitForLoadState();

    // the status of the document the redirects ended on
    const status = await page.evaluate(() => {
        const [navigation] = /** @type {PerformanceNavigationTiming[]} */ (performance.getEntriesByType('navigation'));

        return navigation?.responseStatus ?? 0;
    });
    expect(status, `${page.url()} answered with a server error`).toBeLessThan(500);
}

module.exports = { clickAndWaitForPage };
