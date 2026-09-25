const path = require('node:path');
const { test, expect } = require('@playwright/test');
const { clickAndConfirm, flashMessages, setChecked } = require('../support/admin');
const { clickAndWaitForPage } = require('../support/navigation');
const { giftCardProductPath } = require('../support/shop');

/**
 * Managing gift card designs in the admin, and what that does to the design picker on the shop's gift card product.
 *
 * Every spec creates the design it works on and deletes it again, whatever happens in between: the seeded design has
 * to stay the one the shop preselects, and the setup warning spec flips every design it finds.
 */

const FORM = 'form[name="setono_sylius_gift_card_gift_card_design"]';

// The artwork the bundled "Classic" design ships with, a landscape PNG of the recommended size
const ARTWORK = path.join(__dirname, '..', '..', '..', '..', 'src', 'Resources', 'fixtures', 'default_background.png');

/**
 * A design code nobody else uses
 *
 * @param {string} purpose
 */
function uniqueCode(purpose) {
    return `e2e_${purpose}_${Date.now()}`;
}

/**
 * Fills in and submits the new design form
 *
 * @param {import('@playwright/test').Page} page
 * @param {{code: string, enabled?: boolean, images?: Array<'front'|'back'>}} design
 * @returns {Promise<string>} the name the design was given
 */
async function submitNewDesign(page, { code, enabled = true, images = [] }) {
    const name = `Design ${code}`;

    await page.goto('/admin/gift-card-designs/new');
    await page.locator(`${FORM} [name$="[code]"]`).fill(code);
    // behind the seeded design, which the shop has to keep preselecting
    await page.locator(`${FORM} [name$="[position]"]`).fill('999');
    // the translation of the admin's own locale comes first, and is the one the grid and the shop show
    await page.locator(`${FORM} [name*="[translations]"][name$="[name]"]`).first().fill(name);
    for (const channel of await page.locator(`${FORM} [name$="[channels][]"]`).all()) {
        await setChecked(channel, true);
    }
    await setChecked(page.locator(`${FORM} [name$="[enabled]"]`), enabled);

    for (const [index, type] of images.entries()) {
        await page.locator(`${FORM} [data-form-collection="add"]`).click();
        const image = page.locator(`${FORM} [data-form-collection="item"]`).nth(index);
        await image.locator('select[name$="[type]"]').selectOption(type);
        await image.locator('input[type="file"]').setInputFiles(ARTWORK);
    }

    await clickAndWaitForPage(page, page.locator(`${FORM} button[type="submit"]`).first());

    return name;
}

/**
 * The row of the design in the designs grid
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} code
 */
async function designRow(page, code) {
    await page.goto('/admin/gift-card-designs/');

    return page.locator('table tbody tr').filter({ has: page.locator('td', { hasText: new RegExp(`^${code}$`) }) });
}

/**
 * Deletes the design through the grid, if it exists
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} code
 */
async function deleteDesign(page, code) {
    const row = await designRow(page, code);
    if (0 < (await row.count())) {
        await clickAndConfirm(page, row.getByRole('button', { name: /delete/i }));
    }
}

/**
 * The designs the shop's gift card product offers, by code
 *
 * @param {import('@playwright/test').Page} page
 */
async function designsOfferedInTheShop(page) {
    await page.goto(await giftCardProductPath(page));

    return page.locator('[name*="giftCardInformation"][name*="[design]"]').evaluateAll((choices) => choices.map((choice) => choice.value));
}

test.describe('admin gift card design management', () => {
    test('a design with a front and a back image is listed with its artwork and offered in the shop', async ({ page }) => {
        const code = uniqueCode('artwork');

        try {
            const name = await submitNewDesign(page, { code, images: ['front', 'back'] });
            expect(await flashMessages(page)).toContainEqual(expect.stringMatching(/successfully created/i));

            const row = await designRow(page, code);
            await expect(row).toHaveCount(1);
            await expect(row).toContainText(name);

            // The grid shows the front as a thumbnail, rendered through the plugin's imagine filter
            const thumbnail = row.locator('img');
            await expect(thumbnail).toHaveAttribute('alt', name);
            const response = await page.request.get(/** @type {string} */ (await thumbnail.getAttribute('src')));
            expect(response.status()).toBe(200);
            expect(response.headers()['content-type']).toMatch(/^image\//);

            // Both images are kept, each on its own side
            await clickAndWaitForPage(page, row.locator('a[href$="/edit"]'));
            const images = page.locator(`${FORM} [data-form-collection="item"]`);
            await expect(images).toHaveCount(2);
            await expect(images.locator('img')).toHaveCount(2);
            const sides = await images.locator('select[name$="[type]"]').evaluateAll((selects) => selects.map((select) => select.value));
            expect(sides.sort()).toEqual(['back', 'front']);

            // The shop offers it, with its front image for the preview
            await page.goto(await giftCardProductPath(page));
            const choice = page.locator(`[name*="giftCardInformation"][name*="[design]"][value="${code}"]`);
            await expect(choice).toHaveCount(1);
            await expect(choice).not.toHaveAttribute('data-image-path', '');
        } finally {
            await deleteDesign(page, code);
        }
    });

    test('a design cannot carry two images for the same side', async ({ page }) => {
        const code = uniqueCode('two_fronts');

        try {
            await submitNewDesign(page, { code, images: ['front', 'front'] });

            await expect(page).toHaveURL(/\/admin\/gift-card-designs\/new$/);
            // The message has to be translated, not a raw key: constraint messages resolve in the validators domain
            await expect(page.locator(FORM).getByText('You can only add one "front" image to a design.')).toBeVisible();
            await expect(await designRow(page, code)).toHaveCount(0);
        } finally {
            await deleteDesign(page, code);
        }
    });

    test('only enabled designs are offered in the shop', async ({ page }) => {
        const code = uniqueCode('toggled');

        try {
            await submitNewDesign(page, { code, enabled: false });
            expect(await designsOfferedInTheShop(page)).not.toContain(code);

            const row = await designRow(page, code);
            await clickAndWaitForPage(page, row.locator('a[href$="/edit"]'));
            await setChecked(page.locator(`${FORM} [name$="[enabled]"]`), true);
            await clickAndWaitForPage(page, page.locator(`${FORM} button[type="submit"]`).first());
            expect(await flashMessages(page)).toContainEqual(expect.stringMatching(/successfully updated/i));

            expect(await designsOfferedInTheShop(page)).toContain(code);
        } finally {
            await deleteDesign(page, code);
        }
    });

    test('a deleted design is no longer offered in the shop', async ({ page }) => {
        const code = uniqueCode('deleted');

        try {
            await submitNewDesign(page, { code });
            expect(await designsOfferedInTheShop(page)).toContain(code);

            await clickAndConfirm(page, (await designRow(page, code)).getByRole('button', { name: /delete/i }));
            expect(await flashMessages(page)).toContainEqual(expect.stringMatching(/successfully deleted/i));

            await expect(await designRow(page, code)).toHaveCount(0);
            expect(await designsOfferedInTheShop(page)).not.toContain(code);
        } finally {
            await deleteDesign(page, code);
        }
    });
});
