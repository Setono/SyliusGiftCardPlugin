const { test, expect } = require('@playwright/test');
const { GRID_ROWS, flashMessages, setChecked } = require('../support/admin');
const { anyCustomerEmail, firstGiftCardId, firstDesignId, giftCardCode, channelBaseCurrencyCode, currencyOtherThan } = require('../support/fixtures');
const { giftCardDetails, giftCardRows, giftCardTransactions, issueGiftCard } = require('../support/gift-cards');
const { moneyInCents } = require('../support/money');
const { clickAndWaitForPage } = require('../support/navigation');
const { pdfPageCount, pdfText } = require('../support/pdf');

// The adjust balance form's own button, the only submit button the form has
const ADJUST_BALANCE_SUBMIT = 'form[name="setono_sylius_gift_card_adjust_balance"] button[type="submit"]';

test.describe('admin gift cards', () => {
    test('the index renders and offers the plugin actions', async ({ page }) => {
        const response = await page.goto('/admin/gift-cards/');

        expect(response?.status()).toBe(200);
        await expect(page.locator(GRID_ROWS).first()).toBeVisible();

        // Designs and the balance report were moved out of the admin menu onto this page
        await expect(page.locator('a[href="/admin/gift-card-designs/"]')).toBeVisible();
        await expect(page.locator('a[href="/admin/gift-cards/balance"]')).toBeVisible();
    });

    test('the plugin takes a single admin menu entry', async ({ page }) => {
        await page.goto('/admin/gift-cards/');

        const menu = page.locator('.sylius-admin-menu');
        await expect(menu.locator('a[href="/admin/gift-cards/"]')).toHaveCount(1);
        // These two live on the gift cards page now, not in the menu
        await expect(menu.locator('a[href="/admin/gift-card-designs/"]')).toHaveCount(0);
        await expect(menu.locator('a[href="/admin/gift-cards/balance"]')).toHaveCount(0);
    });

    test('a gift card can be shown and edited', async ({ page }) => {
        const id = await firstGiftCardId(page);

        const show = await page.goto(`/admin/gift-cards/${id}`);
        expect(show?.status()).toBe(200);
        // the page is about the card: its code heads it, and its details and the way to edit it are there
        const printedCode = await giftCardCode(page, id);
        await expect(page.locator('h1')).toContainText(printedCode);
        await expect(page.locator(`a[href="/admin/gift-cards/${id}/edit"]`)).toBeVisible();

        const edit = await page.goto(`/admin/gift-cards/${id}/edit`);
        expect(edit?.status()).toBe(200);
        // the form is the card's: it shows the code the card is stored with
        const form = page.locator('form[name="setono_sylius_gift_card_gift_card"]');
        await expect(form).toBeVisible();
        await expect(form.locator('[name$="[code]"]')).toHaveValue(printedCode.replace(/-/g, ''));
        await expect(form.locator('button[type="submit"]').first()).toBeEnabled();
    });

    test('the balance report renders and leads back to gift cards', async ({ page }) => {
        const response = await page.goto('/admin/gift-cards/balance');

        expect(response?.status()).toBe(200);
        // The report is only reachable from the gift cards page, so the crumbs have to lead back
        await expect(page.locator('.breadcrumb a[href="/admin/gift-cards/"]')).toBeVisible();

        // The fixtures seed usable cards, so the report has a currency they are counted and summed in
        const rows = page.locator('table.ui.table tbody tr').filter({ has: page.locator('td:nth-child(4)') });
        expect(await rows.count(), 'the report should list the currency the seeded cards hold money in').toBeGreaterThan(0);
        for (const row of await rows.all()) {
            const cells = await row.locator('td').allInnerTexts();
            expect(cells[0].trim(), 'each row is a currency').toMatch(/^[A-Z]{3}$/);
            expect(Number(cells[1].trim()), `the cards counted in ${cells[0]}`).toBeGreaterThan(0);
            expect(moneyInCents(cells[2]), `what the cards in ${cells[0]} hold`).toBeGreaterThanOrEqual(0);
        }
    });

    /**
     * The balance is the ledger's business: it may only be moved through the adjust balance action, which
     * records a transaction. Exposing it on the edit form let an admin move it leaving no trace of why.
     */
    test('the balance can only be set while issuing a card', async ({ page }) => {
        const id = await firstGiftCardId(page);

        await page.goto(`/admin/gift-cards/${id}/edit`);
        await expect(page.locator('[name*="[amount]"]')).toHaveCount(0);

        await page.goto('/admin/gift-cards/new');
        await expect(page.locator('[name*="[amount]"]')).toHaveCount(1);
    });

    /**
     * The currency denominates the balance and every ledger row, none of which carry a currency of their
     * own, so switching it on a live card silently revalues it. Like the channel it is chosen while the
     * card is being issued; afterwards it is shown, because the amounts mean nothing without it, but locked.
     */
    test('the currency can only be chosen while issuing a card', async ({ page }) => {
        const id = await firstGiftCardId(page);

        await page.goto(`/admin/gift-cards/${id}/edit`);
        const onEdit = page.locator('select[name*="[currencyCode]"]');
        await expect(onEdit).toHaveCount(1);
        await expect(onEdit).toBeDisabled();

        await page.goto('/admin/gift-cards/new');
        const onNew = page.locator('select[name*="[currencyCode]"]');
        await expect(onNew).toHaveCount(1);
        await expect(onNew).toBeEnabled();
    });

    /**
     * The list offers every currency the shop knows, but Sylius keeps order amounts in the channel's base
     * currency, so a card issued in any other currency would carry a balance in the wrong unit. The form is the only
     * place where an admin can find that out before the customer does.
     */
    test('a currency other than the channel base currency is rejected when issuing a card', async ({ page }) => {
        await page.goto('/admin/gift-cards/new');
        const channelCode = await page.locator('select[name*="[channel]"]').inputValue();

        const base = await channelBaseCurrencyCode(page, channelCode);
        // the fixtures may seed nothing but the channel's own currency, so make sure there is another one to pick
        const other = await currencyOtherThan(page, base);

        await page.goto('/admin/gift-cards/new');
        const currency = page.locator('select[name*="[currencyCode]"]');
        await currency.selectOption(other);
        await page.locator('input[name*="[amount]"]').fill('100');
        await clickAndWaitForPage(page, page.locator('form[name="setono_sylius_gift_card_gift_card"] button[type="submit"]').first());

        // The message has to be translated, not a raw key: constraint messages resolve in the validators domain
        await expect(page.locator('.sylius-validation-error').first())
            .toContainText(/is not the base currency of the channel/i);
    });

    /**
     * The ledger is meant to account for the whole balance. Before issuance was recorded, a card that
     * demonstrably held money showed an empty transactions list, so the panel explained nothing.
     */
    test('the transactions list explains the balance', async ({ page }) => {
        const id = await firstGiftCardId(page);

        await page.goto(`/admin/gift-cards/${id}`);

        // the card's issuance opens its ledger, and the ledger adds up to what the card holds
        await expect(page.locator('[data-test-gift-card-transaction="issue"]')).toHaveCount(1);
        const movements = (await giftCardTransactions(page, id)).map(({ amount }) => amount);
        expect(movements.reduce((sum, amount) => sum + amount, 0)).toBe(moneyInCents((await giftCardDetails(page, id)).Amount));
    });

    test('the adjust balance form is themed', async ({ page }) => {
        const id = await firstGiftCardId(page);

        const response = await page.goto(`/admin/gift-cards/${id}/adjust-balance`);
        expect(response?.status()).toBe(200);

        // Semantic UI scopes its field styling under .ui.form; without the class the form renders unstyled
        await expect(page.locator('form.ui.form')).toHaveCount(1);
        await expect(page.locator('form.ui.form .field')).not.toHaveCount(0);
    });

    /**
     * Deducting more than the card holds used to reach the balance operator, which asserts and returns a 500.
     * It is ordinary user error, so it has to come back as a field error on a rendered form
     */
    test('deducting more than the balance is a validation error, not a crash', async ({ page }) => {
        const id = await firstGiftCardId(page);

        await page.goto(`/admin/gift-cards/${id}/adjust-balance`);
        await page.locator('input[name$="[amount]"]').fill('-99999');
        await page.locator('textarea[name$="[reason]"]').fill('trying to overdraw');
        await clickAndWaitForPage(page, page.locator(ADJUST_BALANCE_SUBMIT));

        await expect(page.locator('.sylius-validation-error').first()).toBeVisible();
        // The message has to be translated, not a raw key: constraint messages resolve in the validators domain
        await expect(page.locator('.sylius-validation-error').first())
            .toContainText(/Deducting more than the gift card holds/i);
    });

    /**
     * An adjustment of 0 changes nothing. The admin is told what the field expects, in the plugin's words, rather than
     * Symfony's "This value should not be equal to 0."
     */
    test('adjusting the balance by nothing is refused with a message that says what the field expects', async ({ page }) => {
        const id = await firstGiftCardId(page);

        await page.goto(`/admin/gift-cards/${id}/adjust-balance`);
        await page.locator('input[name$="[amount]"]').fill('0');
        await page.locator('textarea[name$="[reason]"]').fill('nothing to adjust');
        await clickAndWaitForPage(page, page.locator(ADJUST_BALANCE_SUBMIT));

        const error = page.locator('.sylius-validation-error').first();
        await expect(error).toContainText('Enter an amount other than 0');
        await expect(error).not.toContainText(/should not be equal/i);
    });

    /**
     * Codes are grouped in fours for reading wherever they are shown (GiftCardCodeNormalizer::format()), so the
     * show page must not print the stored code as one unbroken run
     */
    test('the show page groups the code for reading', async ({ page }) => {
        const id = await firstGiftCardId(page);

        await page.goto(`/admin/gift-cards/${id}`);

        // the details table row labelled "Code"; the seeded codes are generated, so the value is discovered, not known
        const codeRow = page.locator('table.ui.table tr').filter({ has: page.locator('td strong', { hasText: /^Code$/ }) });
        const displayed = (await codeRow.locator('td').nth(1).innerText()).trim();

        expect(displayed).toMatch(/^([A-Z0-9]{4}-)*[A-Z0-9]{1,4}$/);
    });

    /**
     * A card created disabled or expired is not emailed on creation, so the admin needs a way to send it once
     * it is usable — and a way to resend one the customer lost. Sending reaches the customer, so it may only
     * happen through a POST carrying a CSRF token: a link would be followed by a browser prefetch.
     */
    test('a gift card can be emailed from the show page', async ({ page }) => {
        // Issued here rather than taken from the grid: only a card the customer can use is offered for sending
        const { id } = await issueGiftCard(page, { amount: 1000, customerEmail: await anyCustomerEmail(page) });

        await page.goto(`/admin/gift-cards/${id}`);

        const form = page.locator(`form[action="/admin/gift-cards/${id}/send-email"]`);
        await expect(form).toHaveCount(1);
        await expect(form.locator('input[name="_csrf_token"]')).toHaveCount(1);

        await form.locator('button[type="submit"]').click();

        await expect(page).toHaveURL(new RegExp(`/admin/gift-cards/${id}$`));
        expect(await flashMessages(page)).toContainEqual(expect.stringMatching(/emailed to the customer/i));
    });

    test('the grid offers sending as a POST, not a link', async ({ page }) => {
        const { printedCode } = await issueGiftCard(page, { amount: 1000 });

        const row = await giftCardRows(page, printedCode);
        const form = row.locator('form[action$="/send-email"]');
        await expect(form).toBeVisible();
        await expect(form.locator('input[name="_csrf_token"]')).toHaveCount(1);
        // A link would let a prefetch send the card behind the admin's back
        await expect(page.locator('a[href$="/send-email"]')).toHaveCount(0);
    });

    /**
     * A card the customer cannot use would arrive as a gift that does not work, so the email on creation skips it,
     * and the admin is not offered to send it either: neither its row in the grid nor its show page has the action
     */
    test('sending is only offered for a card the customer can use', async ({ page }) => {
        const usable = await issueGiftCard(page, { amount: 1000 });
        const disabled = await issueGiftCard(page, { amount: 1000, enabled: false });
        const expired = await issueGiftCard(page, { amount: 1000, expiresAt: '2020-01-31' });

        for (const [card, offered] of [[usable, 1], [disabled, 0], [expired, 0]]) {
            const sendForm = `form[action="/admin/gift-cards/${card.id}/send-email"]`;

            const row = await giftCardRows(page, card.printedCode);
            await expect(row).toHaveCount(1);
            await expect(row.locator(sendForm), `the grid row of ${card.printedCode}`).toHaveCount(offered);

            await page.goto(`/admin/gift-cards/${card.id}`);
            await expect(page.locator(sendForm), `the show page of ${card.printedCode}`).toHaveCount(offered);
        }
    });

    /**
     * A page opened while the card was usable still offers sending after the card stopped being usable, e.g. when
     * its order was refunded in the meantime. The action refuses then, and says why
     */
    test('a card that can no longer be used is not sent from a page opened before', async ({ page }) => {
        const card = await issueGiftCard(page, { amount: 1000, customerEmail: await anyCustomerEmail(page) });
        await page.goto(`/admin/gift-cards/${card.id}`);
        const send = page.locator(`form[action="/admin/gift-cards/${card.id}/send-email"] button[type="submit"]`);
        await expect(send).toBeVisible();

        // Another tab disables the card while this one still shows it
        const other = await page.context().newPage();
        await other.goto(`/admin/gift-cards/${card.id}/edit`);
        await setChecked(other.locator('[name$="[enabled]"]'), false);
        await clickAndWaitForPage(other, other.locator('form[name="setono_sylius_gift_card_gift_card"] button[type="submit"]').first());
        await other.close();

        await clickAndWaitForPage(page, send);

        expect(page.url()).toMatch(new RegExp(`/admin/gift-cards/${card.id}$`));
        expect(await flashMessages(page)).toContainEqual(expect.stringMatching(/disabled, so it was not sent/i));
        await expect(page.locator(`form[action="/admin/gift-cards/${card.id}/send-email"]`)).toHaveCount(0);
    });

    test('a gift card is not emailed by a GET or without a CSRF token', async ({ page }) => {
        const id = await firstGiftCardId(page);

        const get = await page.request.get(`/admin/gift-cards/${id}/send-email`, { maxRedirects: 0 });
        expect(get.status()).toBe(405);

        const post = await page.request.post(`/admin/gift-cards/${id}/send-email`, {
            form: { _csrf_token: 'forged' },
            maxRedirects: 0,
        });
        expect(post.status()).toBe(403);
    });

    /**
     * The checkbox promises an email that a disabled or expired card never gets, so the form has to say when
     * the notification is actually sent
     */
    test('the notification checkbox explains when nothing is sent', async ({ page }) => {
        await page.goto('/admin/gift-cards/new');

        await expect(page.locator('[name*="[sendNotificationEmail]"]')).toHaveCount(1);
        await expect(page.getByText(/only sent when the gift card is usable/i)).toBeVisible();
    });

    /**
     * The amount is written into the gift card while the form is submitted, before validation runs, so a
     * blank amount used to reach the non nullable setter and end the request in a 500
     */
    test('issuing a card without an amount is a validation error, not a crash', async ({ page }) => {
        await page.goto('/admin/gift-cards/new');

        await page.locator('input[name$="[amount]"]').fill('');

        const [response] = await Promise.all([
            page.waitForResponse((r) => r.request().method() === 'POST'),
            page.locator('form[name="setono_sylius_gift_card_gift_card"] button[type="submit"]').first().click(),
        ]);

        expect(response.status()).toBeLessThan(500);
        await expect(page.locator('.sylius-validation-error').first()).toBeVisible();
    });

    /**
     * The balance is kept in an integer column, a signed 32-bit integer, so a card holds at most 21,474,836.47. The
     * amount is a text field the browser lets any number through, and a larger one used to end the request in a 500
     * when the database refused the card
     */
    test('issuing a card for more than a card can hold is a validation error, not a crash', async ({ page }) => {
        await page.goto('/admin/gift-cards/new');

        const amount = page.locator('input[name$="[amount]"]');
        await amount.fill('30000000');

        const [response] = await Promise.all([
            page.waitForResponse((r) => r.request().method() === 'POST'),
            page.locator('form[name="setono_sylius_gift_card_gift_card"] button[type="submit"]').first().click(),
        ]);

        expect(response.status()).toBe(422);
        await expect(page.locator('.field', { has: amount }).locator('.sylius-validation-error'))
            .toContainText(/more than a gift card can hold/i);
    });

    /**
     * Adding to the balance is held to the same ceiling: an adjustment taking the balance above it used to end the
     * request in a 500 too
     */
    test('adding more than a card can hold is a validation error, not a crash', async ({ page }) => {
        const id = await firstGiftCardId(page);

        await page.goto(`/admin/gift-cards/${id}/adjust-balance`);
        await page.locator('input[name$="[amount]"]').fill('30000000');
        await page.locator('textarea[name$="[reason]"]').fill('trying to overfill');

        const [response] = await Promise.all([
            page.waitForResponse((r) => r.request().method() === 'POST'),
            page.locator(ADJUST_BALANCE_SUBMIT).click(),
        ]);

        expect(response.status()).toBe(422);
        // The plugin's message, translated, quoting the most a card holds
        await expect(page.locator('.sylius-validation-error').first()).toContainText(/cannot hold more than/i);
    });

    /**
     * The back of the card is the only place the customer finds the code again, so it is not enough that the
     * endpoint answers with a valid PDF: the code and the redemption copy have to be drawn on it
     */
    test('a gift card PDF can be downloaded', async ({ page }) => {
        const id = await firstGiftCardId(page);
        const code = await giftCardCode(page, id);

        const response = await page.request.get(`/admin/gift-cards/${id}/pdf`);

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('application/pdf');

        const body = await response.body();
        expect(body.subarray(0, 5).toString()).toBe('%PDF-');

        // front and back, drawn on the configured page size (A6 landscape by default)
        expect(pdfPageCount(body)).toBe(2);
        expect(body.toString('latin1')).toContain('/MediaBox [0.000 0.000 419.530 297.640]');

        const text = pdfText(body);
        expect(text).toContain('REDEMPTION CODE');
        expect(text).toContain(code);
        // the card is redeemed in this shop's checkout, nowhere else
        expect(text).not.toContain('in store');
    });
});

test.describe('admin gift card designs', () => {
    test('the index renders and leads back to gift cards', async ({ page }) => {
        const response = await page.goto('/admin/gift-card-designs/');

        expect(response?.status()).toBe(200);
        // a button next to the header, not the admin menu's entry, which every page has
        await expect(page.locator('.admin-layout__content a[href="/admin/gift-cards/"]')).toBeVisible();
        // the seeded design is listed, with a way to edit it
        await expect(page.locator(`${GRID_ROWS} a[href$="/edit"]`).first()).toBeVisible();
    });

    /**
     * A design is only offered in the channels it is enabled for, so the grid says which those are
     */
    test('the design grid lists the channels of every design', async ({ page }) => {
        await page.goto('/admin/gift-card-designs/');

        const headers = (await page.locator('[data-test-grid-table] thead th').allInnerTexts()).map((header) => header.trim());
        const column = headers.indexOf('Channels');
        expect(column, 'the design grid shows no channels column').toBeGreaterThanOrEqual(0);

        const row = page.locator(GRID_ROWS).first();
        const listed = (await row.locator(`td:nth-child(${column + 1})`).innerText()).trim();
        const editUrl = /** @type {string} */ (await row.locator('a[href$="/edit"]').first().getAttribute('href'));

        // the channels the design's form has ticked, named by their labels
        await page.goto(editUrl);
        const ticked = await page
            .locator('input[name$="[channels][]"]:checked')
            .evaluateAll((inputs) => inputs.map((input) => (/** @type {HTMLInputElement} */ (input)).labels?.[0]?.textContent?.trim() ?? ''));
        expect(ticked.length, 'the seeded design should be enabled in a channel for this to test anything').toBeGreaterThan(0);

        for (const channel of ticked) {
            expect(listed, 'the grid should name every channel of the design').toContain(channel);
        }
    });

    test('the design grid can be sorted by name', async ({ page }) => {
        await page.goto('/admin/gift-card-designs/');

        const header = page.locator('[data-test-grid-table] thead th').filter({ hasText: /^\s*Name/ }).locator('a');
        await expect(header, 'the name column should be sortable').toHaveCount(1);

        for (let click = 0; click < 2; click++) {
            await clickAndWaitForPage(page, header);

            const direction = new URL(page.url()).searchParams.get('sorting[name]');
            expect(['asc', 'desc'], 'the grid should now be sorted by name').toContain(direction);

            const column = (await page.locator('[data-test-grid-table] thead th').allInnerTexts()).findIndex((text) => /^\s*Name/.test(text));
            const names = (await page.locator(`${GRID_ROWS} > td:nth-child(${column + 1})`).allInnerTexts()).map((name) => name.trim());
            const sorted = [...names].sort((a, b) => a.localeCompare(b));
            expect(names).toEqual('asc' === direction ? sorted : sorted.reverse());
        }
    });

    test('a design can be edited', async ({ page }) => {
        const id = await firstDesignId(page);

        const response = await page.goto(`/admin/gift-card-designs/${id}/edit`);

        expect(response?.status()).toBe(200);
        // the form is the design's: it carries the design's code and a name for it
        const form = page.locator('form[name="setono_sylius_gift_card_gift_card_design"]');
        await expect(form).toBeVisible();
        await expect(form.locator('[name$="[code]"]')).not.toHaveValue('');
        await expect(form.locator('[name*="[translations]"][name$="[name]"]').first()).not.toHaveValue('');
    });

    /**
     * A design has no show page, but the resource used to register a show route anyway, rendered with a template
     * Sylius' admin does not ship. Trimming /edit off a design's address landed on it and answered with a 500
     */
    test('a design opened without its edit page is not a server error', async ({ page }) => {
        const id = await firstDesignId(page);

        const response = await page.goto(`/admin/gift-card-designs/${id}`);

        expect(response?.status()).toBeLessThan(500);
    });

    /**
     * The position too was written into a non nullable setter while the form was submitted, so saving a design
     * without a position ended in a 500. The setter now accepts null and validation reports the blank field
     */
    test('a design without a position is a validation error, not a crash', async ({ page }) => {
        const id = await firstDesignId(page);

        await page.goto(`/admin/gift-card-designs/${id}/edit`);
        await page.locator('input[name$="[position]"]').fill('');

        const [response] = await Promise.all([
            page.waitForResponse((r) => r.request().method() === 'POST'),
            page
                .locator('form[name="setono_sylius_gift_card_gift_card_design"] button[type="submit"]')
                .first()
                .click(),
        ]);

        expect(response.status()).toBeLessThan(500);
        await expect(page.locator('.sylius-validation-error').first()).toBeVisible();
    });

    /**
     * The position is kept in an integer column, a signed 32-bit integer. The browser's number field takes a larger
     * one, which used to end the request in a 500 when the database refused the design
     */
    test('a design position its column cannot hold is a validation error, not a crash', async ({ page }) => {
        const id = await firstDesignId(page);

        await page.goto(`/admin/gift-card-designs/${id}/edit`);
        await page.locator('input[name$="[position]"]').fill('99999999999');

        const [response] = await Promise.all([
            page.waitForResponse((r) => r.request().method() === 'POST'),
            page
                .locator('form[name="setono_sylius_gift_card_gift_card_design"] button[type="submit"]')
                .first()
                .click(),
        ]);

        expect(response.status()).toBe(422);
        await expect(page.locator('.sylius-validation-error').first()).toContainText(/position must be between/i);
    });

    /**
     * The seeded design brings a back image, which used to be the whole back of the card: no code, no
     * redemption copy, no terms. The preview has to show the same back a customer would get
     */
    test('a design preview PDF is generated', async ({ page }) => {
        const id = await firstDesignId(page);

        const response = await page.request.get(`/admin/gift-card-designs/${id}/preview-pdf`);

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('application/pdf');

        const body = await response.body();
        expect(body.subarray(0, 5).toString()).toBe('%PDF-');
        expect(pdfPageCount(body)).toBe(2);

        const text = pdfText(body);
        expect(text).toContain('REDEMPTION CODE');
        expect(text).toContain('HOW TO REDEEM');
        // the preview card's code is generated, so only its shape is known
        expect(text).toMatch(/[A-Z0-9]{4}-[A-Z0-9]{4}/);
    });
});
