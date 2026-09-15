const { test, expect } = require('@playwright/test');

/**
 * The admin warns while a channel sells gift cards without an enabled design. The seeded shop has the classic
 * design enabled in its only channel, so the warning is provoked by disabling every design and cleared again by
 * re-enabling them, whatever happens in between, so the specs after this one find the shop as seeded.
 */

const TOPBAR_WARNING = '[data-test-gift-card-setup-warning]';
const MESSAGE = '[data-test-gift-card-setup-warning-message]';

/**
 * Semantic UI lays its own label over a checkbox and toggles the input itself when that label is clicked, so a
 * forced click on the input races the label's handler; the state is set on the input instead
 *
 * @param {import('@playwright/test').Locator} checkbox
 * @param {boolean} checked
 */
async function setChecked(checkbox, checked) {
    await checkbox.evaluate((input, value) => {
        input.checked = value;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }, checked);
}

/**
 * The edit urls of every design in the grid
 *
 * @param {import('@playwright/test').Page} page
 */
async function designEditUrls(page) {
    await page.goto('/admin/gift-card-designs/');

    const hrefs = await page.locator('a[href*="/admin/gift-card-designs/"]').evaluateAll((links) =>
        links.map((link) => link.getAttribute('href') ?? '').filter((href) => /\/admin\/gift-card-designs\/\d+\/edit$/.test(href)),
    );

    return [...new Set(hrefs)];
}

/**
 * @param {import('@playwright/test').Page} page
 * @param {string[]} editUrls
 * @param {boolean} enabled
 */
async function setDesignsEnabled(page, editUrls, enabled) {
    for (const editUrl of editUrls) {
        await page.goto(editUrl);
        await setChecked(page.locator('input[name$="[enabled]"]'), enabled);
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'POST'),
            page.locator('form[name="setono_sylius_gift_card_gift_card_design"] button[type="submit"]').first().click(),
        ]);
        await page.waitForLoadState('networkidle');
    }
}

test.describe('gift card setup warning', () => {
    test('the seeded shop is set up, so nothing is shown', async ({ page }) => {
        await page.goto('/admin/');
        await expect(page.locator(TOPBAR_WARNING)).toHaveCount(0);

        await page.goto('/admin/gift-card-designs/');
        await expect(page.locator(MESSAGE)).toHaveCount(0);
    });

    test('a channel selling gift cards without an enabled design is pointed out everywhere', async ({ page }) => {
        // two designs per card and a save per design, on top of the pages checked afterwards
        test.setTimeout(120_000);

        const editUrls = await designEditUrls(page);
        expect(editUrls.length, 'the seeded shop should have at least one design').toBeGreaterThan(0);

        try {
            await setDesignsEnabled(page, editUrls, false);

            // on a page that has nothing to do with gift cards
            await page.goto('/admin/products/');
            const topbar = page.locator(TOPBAR_WARNING);
            await expect(topbar).toBeVisible();
            await expect(topbar).toContainText(/setup incomplete/i);

            // it leads to the designs, which explain the two ways out
            await topbar.click();
            await expect(page).toHaveURL(/\/admin\/gift-card-designs\/?$/);
            const message = page.locator(MESSAGE);
            await expect(message).toBeVisible();
            await expect(message).toContainText(/no enabled gift card design/i);
            await expect(message).toContainText('setono:gift-card:create-default-design');
            await expect(message.locator('a[href$="/admin/gift-card-designs/new"]')).toBeVisible();

            // and the gift cards index says the same
            await page.goto('/admin/gift-cards/');
            await expect(page.locator(MESSAGE)).toBeVisible();
        } finally {
            await setDesignsEnabled(page, editUrls, true);
        }

        // once a design is enabled again the warning is gone
        await page.goto('/admin/gift-cards/');
        await expect(page.locator(TOPBAR_WARNING)).toHaveCount(0);
        await expect(page.locator(MESSAGE)).toHaveCount(0);
    });
});
