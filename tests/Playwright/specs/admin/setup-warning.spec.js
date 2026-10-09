const { test, expect } = require('@playwright/test');
const { GRID_ROWS, clickAndConfirm, flashMessages, setChecked } = require('../support/admin');
const { blankIcons } = require('../support/icons');
const { clickAndWaitForPage } = require('../support/navigation');
const { GIFT_CARD_PAYMENT_METHOD_CODE, paymentMethodEditUrl, saveEnabled, setPaymentMethodEnabled } = require('../support/payment-methods');

/**
 * The admin warns while a channel sells gift cards without an enabled design. The seeded shop has the classic
 * design enabled in its only channel, so the warning is provoked by disabling every design and cleared again by
 * re-enabling them, whatever happens in between, so the specs after this one find the shop as seeded.
 */

const TOPBAR_WARNING = '[data-test-gift-card-setup-warning]';
const MESSAGE = '[data-test-gift-card-setup-warning-message]';
const PAYMENT_METHOD_TOPBAR_WARNING = '[data-test-gift-card-payment-method-warning]';
const PAYMENT_METHOD_MESSAGE = '[data-test-gift-card-payment-method-warning-message]';
const CREATE_PAYMENT_METHOD_BUTTON = `${PAYMENT_METHOD_MESSAGE} form[action$="/admin/gift-cards/create-payment-method"] button[type="submit"]`;
const DISABLED_PAYMENT_METHOD_TOPBAR_WARNING = '[data-test-gift-card-payment-method-disabled-warning]';
const DISABLED_PAYMENT_METHOD_MESSAGE = '[data-test-gift-card-payment-method-disabled-warning-message]';
const DISABLED_PAYMENT_METHOD_EDIT_LINK = `${DISABLED_PAYMENT_METHOD_MESSAGE} [data-test-gift-card-payment-method-disabled-warning-edit]`;

/**
 * The row of the gift card payment method in Sylius' payment methods grid, found by the code the plugin gives it (the
 * redemption.payment_method_code setting, which the test application leaves at gift_card)
 *
 * @param {import('@playwright/test').Page} page
 */
async function giftCardPaymentMethodRow(page) {
    await page.goto('/admin/payment-methods/');

    return page.locator(GRID_ROWS, { hasText: 'gift_card' });
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
    const form = 'form[name="setono_sylius_gift_card_gift_card_design"]';

    for (const editUrl of editUrls) {
        await page.goto(editUrl);
        await setChecked(page.locator(`${form} input[name$="[enabled]"]`), enabled);
        await clickAndWaitForPage(page, page.locator(`${form} button[type="submit"]`).first());
        await expect(page.locator(`${form} .sylius-validation-error`), `saving ${editUrl} was refused`).toHaveCount(0);
    }
}

test.describe('gift card setup warning', () => {
    test('the seeded shop is set up, so nothing is shown', async ({ page }) => {
        await page.goto('/admin/');
        await expect(page.locator(TOPBAR_WARNING)).toHaveCount(0);
        await expect(page.locator(PAYMENT_METHOD_TOPBAR_WARNING)).toHaveCount(0);
        await expect(page.locator(DISABLED_PAYMENT_METHOD_TOPBAR_WARNING)).toHaveCount(0);

        await page.goto('/admin/gift-card-designs/');
        await expect(page.locator(MESSAGE)).toHaveCount(0);
        await expect(page.locator(PAYMENT_METHOD_MESSAGE)).toHaveCount(0);

        // the button creating the payment method is part of the warning, so there is nothing to press either
        await page.goto('/admin/gift-cards/');
        await expect(page.locator(PAYMENT_METHOD_MESSAGE)).toHaveCount(0);
        await expect(page.locator('form[action$="/admin/gift-cards/create-payment-method"]')).toHaveCount(0);
    });

    /**
     * The fixture sets the payment method up for the seeded shop, so the spec deletes it to provoke the warning. Sylius
     * only lets a payment method be deleted while no payment uses it, and gift card payments are made when an order is
     * placed: the admin specs only apply cards to carts and run before the shop specs place orders, each CI shard on a
     * database of its own, so here it still can be. On a database where it cannot, there is no missing method to fix and
     * the test is skipped. Pressing the button puts the method back, and so does the finally, whatever fails in between,
     * so the specs after this one find a shop that takes gift cards
     */
    test('while the gift card payment method is missing, the warning creates it with one click', async ({ page }) => {
        const row = await giftCardPaymentMethodRow(page);
        await expect(row, 'the seeded shop should have the gift card payment method').toHaveCount(1);

        await clickAndConfirm(page, row.getByRole('button', { name: /delete/i }));
        const deleted = 0 === (await (await giftCardPaymentMethodRow(page)).count());
        test.skip(!deleted, 'The gift card payment method is in use, so Sylius does not let it be deleted to provoke the warning');

        try {
            // on a page that has nothing to do with gift cards, the top bar leads to the gift card index
            await page.goto('/admin/products/');
            const topbar = page.locator(PAYMENT_METHOD_TOPBAR_WARNING);
            await expect(topbar).toBeVisible();
            expect(await blankIcons(topbar), 'the icons of the top bar label that draw nothing').toEqual([]);
            await clickAndWaitForPage(page, topbar);
            await expect(page).toHaveURL(/\/admin\/gift-cards\/?$/);

            // which explains it and offers the button, with the command for deploy scripts next to it
            const message = page.locator(PAYMENT_METHOD_MESSAGE);
            await expect(message).toBeVisible();
            await expect(message).toContainText('gift_card');
            await expect(message).toContainText('setono:gift-card:create-payment-method');
            expect(await blankIcons(message), 'the icons of the warning that draw nothing').toEqual([]);

            await clickAndWaitForPage(page, page.locator(CREATE_PAYMENT_METHOD_BUTTON));

            await expect(page).toHaveURL(/\/admin\/gift-cards\/?$/);
            expect(await flashMessages(page)).toContainEqual(expect.stringMatching(/payment method was created/i));
            await expect(page.locator(PAYMENT_METHOD_TOPBAR_WARNING)).toHaveCount(0);
            await expect(page.locator(PAYMENT_METHOD_MESSAGE)).toHaveCount(0);

            // and Sylius lists the method again
            await expect(await giftCardPaymentMethodRow(page)).toHaveCount(1);
        } finally {
            await page.goto('/admin/gift-cards/');
            const button = page.locator(CREATE_PAYMENT_METHOD_BUTTON);
            if (0 < (await button.count())) {
                await clickAndWaitForPage(page, button);
            }
        }
    });

    /**
     * A disabled gift card payment method refuses gift cards just as a missing one does (#484). The merchant may have
     * disabled it on purpose, but the shop goes on selling cards nobody can spend, so every admin page says so and leads
     * to the method's edit page, where it is enabled again. The finally puts the method back the way it was, whatever
     * fails in between, so the specs after this one find a shop that takes gift cards
     */
    test('while the gift card payment method is disabled, every page says so and leads to its edit page', async ({ page }) => {
        const editUrl = await paymentMethodEditUrl(page, GIFT_CARD_PAYMENT_METHOD_CODE);
        const restore = await setPaymentMethodEnabled(page, GIFT_CARD_PAYMENT_METHOD_CODE, false);

        try {
            // on a page that has nothing to do with gift cards, the top bar leads straight to the method's edit page
            await page.goto('/admin/products/');
            const topbar = page.locator(DISABLED_PAYMENT_METHOD_TOPBAR_WARNING);
            await expect(topbar).toBeVisible();
            expect(await blankIcons(topbar), 'the icons of the top bar label that draw nothing').toEqual([]);
            // the method is there, so nothing says it is missing
            await expect(page.locator(PAYMENT_METHOD_TOPBAR_WARNING)).toHaveCount(0);

            await clickAndWaitForPage(page, topbar);
            expect(new URL(page.url()).pathname).toBe(editUrl);

            // the indexes the setup warning is on explain it, with a link to the same page and no button creating a method
            for (const index of ['/admin/gift-cards/', '/admin/gift-card-designs/', '/admin/payment-methods/']) {
                await page.goto(index);

                const message = page.locator(DISABLED_PAYMENT_METHOD_MESSAGE);
                await expect(message, index).toBeVisible();
                expect(await blankIcons(message), `the icons of the warning on ${index} that draw nothing`).toEqual([]);
                await expect(page.locator(PAYMENT_METHOD_MESSAGE), index).toHaveCount(0);
                await expect(page.locator('form[action$="/admin/gift-cards/create-payment-method"]'), index).toHaveCount(0);
            }

            await clickAndWaitForPage(page, page.locator(DISABLED_PAYMENT_METHOD_EDIT_LINK));
            expect(new URL(page.url()).pathname).toBe(editUrl);

            // where enabling it again takes the warning away
            await saveEnabled(page, true);
            await expect(page.locator(DISABLED_PAYMENT_METHOD_TOPBAR_WARNING)).toHaveCount(0);

            await page.goto('/admin/gift-cards/');
            await expect(page.locator(DISABLED_PAYMENT_METHOD_TOPBAR_WARNING)).toHaveCount(0);
            await expect(page.locator(DISABLED_PAYMENT_METHOD_MESSAGE)).toHaveCount(0);
        } finally {
            await restore();
        }
    });

    /**
     * The payment method gift card payments are made with is a setup step, which the plugin's fixture takes for the
     * seeded shop
     */
    test('the seeded shop has the gift card payment method, so the payment methods say nothing about it', async ({ page }) => {
        await page.goto('/admin/payment-methods/');

        await expect(page.locator(PAYMENT_METHOD_MESSAGE)).toHaveCount(0);
        await expect(page.locator(GRID_ROWS, { hasText: 'gift_card' })).toHaveCount(1);
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
            expect(await blankIcons(topbar), 'the icons of the top bar label that draw nothing').toEqual([]);

            // it leads to the designs, which explain the two ways out
            await clickAndWaitForPage(page, topbar);
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
