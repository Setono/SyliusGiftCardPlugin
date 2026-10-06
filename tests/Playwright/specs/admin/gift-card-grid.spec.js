const { test, expect } = require('@playwright/test');
const { GRID_ROWS, clickAndConfirm } = require('../support/admin');
const { firstGiftCardId, giftCardCode } = require('../support/fixtures');
const { adjustBalance, filterGiftCards, giftCardIds, giftCardRows, giftCardStatuses, issueGiftCard } = require('../support/gift-cards');
const { moneyInCents } = require('../support/money');
const { clickAndWaitForPage } = require('../support/navigation');

/**
 * The filters, sorting and status column the gift card grid offers. The grid is where an admin looks a card up when a
 * customer asks about one, so finding a card by its code and telling usable cards from the others have to work.
 */

/**
 * The grid's rows and column headers, by Sylius' hooks on the grid's table. A bare `table tbody tr` also counts the rows
 * of any other table on the page, such as the web debug toolbar's list of AJAX requests in the dev environment (#427)
 */
const ROWS = GRID_ROWS;
const HEADERS = '[data-test-grid-table] thead th';

/**
 * The code column of every row the grid lists, without the grouping the grid shows a code in, so it compares with the
 * code issueGiftCard() returns
 *
 * @param {import('@playwright/test').Page} page
 */
async function listedCodes(page) {
    return (await page.locator(`${ROWS} td:first-child`).allInnerTexts()).map((code) => code.trim().replace(/-/g, ''));
}

/**
 * The balance column of every row the grid lists, in minor units
 *
 * @param {import('@playwright/test').Page} page
 */
async function listedAmounts(page) {
    const column = await page.locator(HEADERS).evaluateAll((headers) => headers.findIndex((header) => /^\s*Amount/.test(header.textContent ?? '')));
    expect(column, 'the grid shows no amount column').toBeGreaterThanOrEqual(0);

    // the cell names the initial amount below a balance that has moved, so only its first line is the balance
    const cells = await page.locator(`${ROWS} td:nth-child(${column + 1})`).allInnerTexts();

    return cells.map((cell) => moneyInCents(cell.split('\n')[0]));
}

/**
 * Deletes the given untouched cards through their rows, which keeps them from topping the grid for other specs
 *
 * @param {import('@playwright/test').Page} page
 * @param {Array<{printedCode: string}>} cards
 */
async function deleteCards(page, cards) {
    for (const card of cards) {
        await clickAndConfirm(page, (await giftCardRows(page, card.printedCode)).getByRole('button', { name: /delete/i }));
    }
}

test.describe('admin gift card grid', () => {
    test('filtering by code finds the card from a fragment of its code, in any case', async ({ page }) => {
        const card = await issueGiftCard(page, { amount: 1000 });
        const fragment = card.code.slice(4, 10);

        await filterGiftCards(page, { code: { type: 'contains', value: fragment.toLowerCase() } });

        const codes = await listedCodes(page);
        expect(codes).toContain(card.code);
        for (const code of codes) {
            expect(code, 'only cards whose code contains the fragment should be listed').toContain(fragment);
        }

        await filterGiftCards(page, { code: { type: 'equal', value: card.code } });
        expect(await listedCodes(page)).toEqual([card.code]);
    });

    /**
     * The card, the emails and the show page print a code grouped, and that is how a customer reads it out to support,
     * while the stored code has no separators. The printed code is taken from the show page, not from the grid, so
     * this is about the filter alone
     */
    test('a code typed the way it is printed on the card finds the card', async ({ page }) => {
        const id = await firstGiftCardId(page);
        const printed = await giftCardCode(page, id);
        expect(printed, 'the code should be printed with separators for this to test anything').toContain('-');

        for (const code of [
            { type: 'contains', value: printed },
            // read out over the phone and typed with spaces, in lower case
            { type: 'equal', value: printed.toLowerCase().replace(/-/g, ' ') },
        ]) {
            await filterGiftCards(page, { code });
            expect(await giftCardIds(page.locator(ROWS)), `filtering by ${code.type} "${code.value}"`).toEqual([id]);
        }
    });

    test('the grid shows a code the way the show page prints it', async ({ page }) => {
        await page.goto('/admin/gift-cards/');

        const row = page.locator(ROWS).first();
        const listed = (await row.locator('td').first().innerText()).trim();
        const [id] = await giftCardIds(row);

        expect(listed).toBe(await giftCardCode(page, id));
    });

    /**
     * The enabled flag said nothing about a card that has expired or has nothing left, so the grid shows one status
     * per card instead, the same the show page shows
     */
    test('the status column tells usable, disabled, expired and spent cards apart', async ({ page }) => {
        const usable = await issueGiftCard(page, { amount: 1000 });
        const disabled = await issueGiftCard(page, { amount: 1000, enabled: false });
        const expired = await issueGiftCard(page, { amount: 1000, expiresAt: '2020-01-31' });
        const spent = await issueGiftCard(page, { amount: 1000 });
        await adjustBalance(page, spent.id, -1000, 'Paid in the physical store');

        try {
            await page.goto('/admin/gift-cards/');
            await expect(page.locator(HEADERS).filter({ hasText: /^\s*Enabled/ })).toHaveCount(0);

            for (const [card, status] of [[usable, 'Usable'], [disabled, 'Disabled'], [expired, 'Expired'], [spent, 'Spent']]) {
                const row = await giftCardRows(page, card.printedCode);
                expect(await giftCardStatuses(row), `the status of ${card.printedCode}`).toEqual([status]);

                await page.goto(`/admin/gift-cards/${card.id}`);
                await expect(page.locator('table.ui.table').first().locator('[data-test-gift-card-status]'), `the show page of ${card.printedCode}`).toHaveText(status);
            }
        } finally {
            await deleteCards(page, [usable, disabled, expired]);
        }
    });

    test('filtering by enabled tells disabled cards from enabled ones', async ({ page }) => {
        const card = await issueGiftCard(page, { amount: 1000, enabled: false });

        try {
            await filterGiftCards(page, { enabled: 'false' });
            expect(await listedCodes(page)).toContain(card.code);
            expect(new Set(await giftCardStatuses(page.locator(ROWS)))).toEqual(new Set(['Disabled']));

            await filterGiftCards(page, { enabled: 'true' });
            expect(await listedCodes(page)).not.toContain(card.code);
            for (const status of await giftCardStatuses(page.locator(ROWS))) {
                expect(['Usable', 'Expired', 'Spent'], 'only enabled cards should be listed').toContain(status);
            }
        } finally {
            await deleteCards(page, [card]);
        }
    });

    /**
     * Expired and spent look at the expiry date and the balance alone, so they also find a disabled card, which the
     * status column shows as disabled
     */
    test('filtering by expired and by spent finds the cards past their date and those with nothing left', async ({ page }) => {
        const expired = await issueGiftCard(page, { amount: 1000, expiresAt: '2020-01-31' });
        const spent = await issueGiftCard(page, { amount: 1000 });
        await adjustBalance(page, spent.id, -1000, 'Paid in the physical store');

        try {
            await filterGiftCards(page, { expired: 'true' });
            expect(await listedCodes(page)).toContain(expired.code);
            expect(await listedCodes(page)).not.toContain(spent.code);
            for (const status of await giftCardStatuses(page.locator(ROWS))) {
                expect(['Expired', 'Spent', 'Disabled']).toContain(status);
            }

            await filterGiftCards(page, { expired: 'false' });
            expect(await listedCodes(page)).not.toContain(expired.code);

            await filterGiftCards(page, { spent: 'true' });
            expect(await listedCodes(page)).toContain(spent.code);
            expect(await listedCodes(page)).not.toContain(expired.code);
            expect(new Set(await listedAmounts(page))).toEqual(new Set([0]));

            await filterGiftCards(page, { spent: 'false', expired: 'false', enabled: 'true' });
            expect(await listedCodes(page)).not.toContain(spent.code);
            expect(new Set(await giftCardStatuses(page.locator(ROWS))), 'enabled, not expired and not spent is usable').toEqual(new Set(['Usable']));
        } finally {
            await deleteCards(page, [expired]);
        }
    });

    test('the grid can be sorted by code both ways', async ({ page }) => {
        await page.goto('/admin/gift-cards/');

        const header = page.locator(HEADERS).filter({ hasText: /^\s*Code/ }).locator('a');
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

    test('the grid can be sorted by balance both ways', async ({ page }) => {
        await page.goto('/admin/gift-cards/');

        const header = page.locator(HEADERS).filter({ hasText: /^\s*Amount/ }).locator('a');
        await expect(header, 'the amount column should be sortable').toHaveCount(1);

        const directions = [];
        for (let click = 0; click < 2; click++) {
            await clickAndWaitForPage(page, header);

            const direction = new URL(page.url()).searchParams.get('sorting[amount]');
            expect(['asc', 'desc'], 'the grid should now be sorted by amount').toContain(direction);
            directions.push(direction);

            const amounts = await listedAmounts(page);
            expect(new Set(amounts).size, 'the grid should list different balances to sort').toBeGreaterThan(1);
            const sorted = [...amounts].sort((a, b) => a - b);
            expect(amounts).toEqual('asc' === direction ? sorted : sorted.reverse());
        }

        expect(new Set(directions), 'the second click should reverse the order').toEqual(new Set(['asc', 'desc']));
    });

    test('the grid can be sorted by customer', async ({ page }) => {
        await page.goto('/admin/gift-cards/');
        const listed = await page.locator(ROWS).count();

        const header = page.locator(HEADERS).filter({ hasText: /^\s*Customer/ }).locator('a');
        await expect(header, 'the customer column should be sortable').toHaveCount(1);

        await clickAndWaitForPage(page, header);
        expect(['asc', 'desc']).toContain(new URL(page.url()).searchParams.get('sorting[customer]'));
        // cards without a customer stay listed, which an inner join to the customer would drop
        await expect(page.locator(ROWS)).toHaveCount(listed);
    });
});
