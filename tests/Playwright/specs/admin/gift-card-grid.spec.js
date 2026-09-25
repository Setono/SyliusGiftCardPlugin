const { test, expect } = require('@playwright/test');
const { clickAndConfirm } = require('../support/admin');
const { giftCardRows, issueGiftCard } = require('../support/gift-cards');
const { clickAndWaitForPage } = require('../support/navigation');

/**
 * The filters and sorting the gift card grid offers. The grid is where an admin looks a card up when a customer asks
 * about one, so finding a card by its code and telling usable cards from disabled ones have to work.
 */

/**
 * Applies the grid's filters through its form, the way an admin does, and waits for the filtered grid
 *
 * @param {import('@playwright/test').Page} page
 * @param {{code?: {type: string, value: string}, enabled?: string}} criteria
 */
async function filterGrid(page, { code = { type: 'contains', value: '' }, enabled = '' }) {
    await page.goto('/admin/gift-cards/');

    const fields = {
        codeType: page.locator('select[name="criteria[code][type]"]'),
        code: page.locator('input[name="criteria[code][value]"]'),
        enabled: page.locator('select[name="criteria[enabled]"]'),
    };
    for (const [name, field] of Object.entries(fields)) {
        await expect(field, `the grid offers no ${name} filter`).toHaveCount(1);
    }

    await fields.codeType.selectOption(code.type);
    await fields.code.fill(code.value);
    await fields.enabled.selectOption(enabled);

    await clickAndWaitForPage(page, page.getByRole('button', { name: 'Filter' }));
}

/**
 * The code column of every row the grid lists
 *
 * @param {import('@playwright/test').Page} page
 */
async function listedCodes(page) {
    return (await page.locator('table tbody tr td:first-child').allInnerTexts()).map((code) => code.trim());
}

/**
 * The enabled column of every row the grid lists
 *
 * @param {import('@playwright/test').Page} page
 */
async function listedStates(page) {
    return (await page.locator('table tbody tr td:nth-child(5)').allInnerTexts()).map((state) => state.trim());
}

test.describe('admin gift card grid', () => {
    test('filtering by code finds the card from a fragment of its code, in any case', async ({ page }) => {
        const card = await issueGiftCard(page, { amount: 1000 });
        const fragment = card.code.slice(4, 10);

        await filterGrid(page, { code: { type: 'contains', value: fragment.toLowerCase() } });

        const codes = await listedCodes(page);
        expect(codes).toContain(card.code);
        for (const code of codes) {
            expect(code, 'only cards whose code contains the fragment should be listed').toContain(fragment);
        }

        await filterGrid(page, { code: { type: 'equal', value: card.code } });
        expect(await listedCodes(page)).toEqual([card.code]);
    });

    test('filtering by enabled tells disabled cards from usable ones', async ({ page }) => {
        const card = await issueGiftCard(page, { amount: 1000, enabled: false });

        try {
            await filterGrid(page, { enabled: 'false' });
            expect(await listedCodes(page)).toContain(card.code);
            expect(new Set(await listedStates(page))).toEqual(new Set(['Disabled']));

            await filterGrid(page, { enabled: 'true' });
            expect(await listedCodes(page)).not.toContain(card.code);
            expect(new Set(await listedStates(page))).toEqual(new Set(['Enabled']));
        } finally {
            // An untouched card can be deleted, which keeps a disabled card from topping the grid for other specs
            await clickAndConfirm(page, (await giftCardRows(page, card.code)).getByRole('button', { name: /delete/i }));
        }
    });

    test('the grid can be sorted by code both ways', async ({ page }) => {
        await page.goto('/admin/gift-cards/');

        const header = page.locator('table thead th').filter({ hasText: /^\s*Code/ }).locator('a');
        await expect(header, 'the code column should be sortable').toHaveCount(1);

        // Each click on the header sorts by code, turning the direction around on the next one
        const directions = [];
        for (let click = 0; click < 2; click++) {
            await clickAndWaitForPage(page, header);

            const direction = new URL(page.url()).searchParams.get('sorting[code]');
            expect(['asc', 'desc'], 'the grid should now be sorted by code').toContain(direction);
            directions.push(direction);

            const codes = await listedCodes(page);
            expect(codes.length, 'the grid should list cards to sort').toBeGreaterThan(1);
            const sorted = [...codes].sort();
            expect(codes).toEqual('asc' === direction ? sorted : sorted.reverse());
        }

        expect(new Set(directions), 'the second click should reverse the order').toEqual(new Set(['asc', 'desc']));
    });
});
