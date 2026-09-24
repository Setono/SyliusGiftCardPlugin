const { test, expect } = require('@playwright/test');
const { addSomethingToCart, applyGiftCard } = require('../support/cart');
const { ADMIN_STORAGE_STATE } = require('../support/paths');

/**
 * A gift card that stops being usable between the cart and "Place order".
 *
 * A cart the card fully covers skips the payment step, so nothing re-checks the card before the order is placed.
 * If an admin disables it in the meantime (or it is spent from another cart, or adjusted), the customer must not
 * be able to place the order as paid, nor be bounced between checkout steps or shown an error: the card is removed
 * from the cart, the customer is told why and lands on the cart to start over, this time paying by other means.
 *
 * The spec issues its own card through the admin rather than disabling the seeded one, which the other shop specs
 * rely on staying usable.
 */

// Codes are shown grouped in fours for reading (GiftCardCodeNormalizer::format()); the raw code only appears in form actions
const grouped = (code) => code.match(/.{1,4}/g).join('-');

/**
 * Semantic UI lays its own label over a checkbox and toggles the input itself when that label is clicked, so a forced
 * click on the input races the label's handler and can land as no change at all. The state is set on the input instead
 *
 * @param {import('@playwright/test').Locator} checkbox
 * @param {boolean} checked
 */
async function setChecked(checkbox, checked) {
    await checkbox.evaluate((input, value) => {
        input.checked = value;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }, checked);

    if (checked) {
        await expect(checkbox).toBeChecked();
    } else {
        await expect(checkbox).not.toBeChecked();
    }
}

/**
 * Issues a card through the admin form and returns its code and edit url. Big enough to cover any cart, so the
 * checkout skips the payment step.
 *
 * @param {import('@playwright/test').Browser} browser
 */
async function issueGiftCard(browser) {
    const admin = await browser.newContext({ storageState: ADMIN_STORAGE_STATE });
    const page = await admin.newPage();

    await page.goto('/admin/gift-cards/new');
    const form = page.locator('form').filter({ has: page.locator('[name$="[amount]"]') });

    // the code is the plugin's to generate (the form shows it read-only), so it is read back once the card exists
    await form.locator('[name$="[amount]"]').fill('1000');
    // the shop trades in USD; the form proposes whichever currency sorts first
    const currencies = form.locator('select[name$="[currencyCode]"]');
    if (0 < await currencies.locator('option[value="USD"]').count()) {
        await currencies.selectOption('USD');
    }
    await setChecked(form.locator('[name$="[enabled]"]'), true);
    // there is no customer to notify
    const notification = form.locator('[name$="[sendNotificationEmail]"]');
    if (0 < await notification.count()) {
        await setChecked(notification, false);
    }
    await form.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');

    // Creating redirects to the gift cards index with the new card's id as a query parameter, or to the
    // card's own page; either names the id
    const landed = new URL(page.url());
    const id = landed.searchParams.get('id') ?? /\/admin\/gift-cards\/(\d+)(?:\/edit)?$/.exec(landed.pathname)?.[1] ?? null;
    expect(id, `creating the gift card should have led to a page naming its id, not ${page.url()}`).toMatch(/^\d+$/);
    const editHref = `/admin/gift-cards/${id}/edit`;

    await page.goto(editHref);
    const code = (await page.locator('[name$="[code]"]').inputValue()).trim();
    expect(code, 'the issued gift card should carry a code').toMatch(/^[A-Z0-9]+$/);

    await admin.close();

    return { code, editHref };
}

/**
 * @param {import('@playwright/test').Browser} browser
 * @param {string} editHref
 */
async function disableGiftCard(browser, editHref) {
    const admin = await browser.newContext({ storageState: ADMIN_STORAGE_STATE });
    const page = await admin.newPage();

    await page.goto(editHref);
    const form = page.locator('form').filter({ has: page.locator('[name$="[enabled]"]') });
    await setChecked(form.locator('[name$="[enabled]"]'), false);
    await form.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');

    await page.goto(editHref);
    await expect(page.locator('[name$="[enabled]"]')).not.toBeChecked();

    await admin.close();
}

/**
 * Walks a guest through address and shipping. With the card covering everything the payment step is skipped, so
 * this ends on the complete step.
 *
 * @param {import('@playwright/test').Page} page
 */
async function checkoutToCompleteStep(page) {
    await page.goto('/en_US/checkout/address');

    await page.locator('[name="sylius_checkout_address[customer][email]"]').fill(`stale-${Date.now()}@example.com`);
    const billing = 'sylius_checkout_address[billingAddress]';
    await page.locator(`[name="${billing}[firstName]"]`).fill('Stale');
    await page.locator(`[name="${billing}[lastName]"]`).fill('Card');
    await page.locator(`[name="${billing}[street]"]`).fill('1 Main Street');
    // The seeded shipping methods serve the US zone; failing that, the first country the channel offers, so the
    // spec does not assume which countries are seeded
    const countries = page.locator(`select[name="${billing}[countryCode]"]`);
    const offered = await countries.locator('option[value]:not([value=""])').evaluateAll((options) => options.map((o) => o.value));
    expect(offered.length, 'the channel should offer at least one country').toBeGreaterThan(0);
    await countries.selectOption(offered.includes('US') ? 'US' : offered[0]);
    await page.locator(`[name="${billing}[city]"]`).fill('Springfield');
    await page.locator(`[name="${billing}[postcode]"]`).fill('12345');

    await page.locator('#next-step').click();
    await page.waitForURL('**/checkout/select-shipping');

    await page.locator('#next-step').click();
    await page.waitForURL('**/checkout/complete');
}

test.describe('a gift card going stale during checkout', () => {
    // each journey issues a card in the admin, walks a guest through checkout and returns to the admin
    test.setTimeout(120_000);

    test('placing the order sends the customer back to the cart without the card', async ({ page, browser }) => {
        const { code, editHref } = await issueGiftCard(browser);

        await addSomethingToCart(page);
        await applyGiftCard(page, code);
        // the cart lists the code grouped in fours, so the row is found through its remove form, which carries the raw code
        await expect(page.locator(`form[action*="/gift-cards/${code}/remove"]`)).toBeVisible();
        await checkoutToCompleteStep(page);

        await disableGiftCard(browser, editHref);

        await page.locator('form[name="sylius_checkout_complete"] button[type="submit"]').first().click();
        await page.waitForLoadState('networkidle');

        await expect(page).toHaveURL(/\/en_US\/cart\/?$/);
        await expect(page.locator('.sylius-flash-message').filter({ hasText: grouped(code) })).toBeVisible();
        await expect(page.locator('.sylius-flash-message').filter({ hasText: 'can no longer be used' })).toBeVisible();
        // the card is gone from the cart and the order costs what it did, now to be paid by other means
        await expect(page.locator(`form[action*="/gift-cards/${code}/remove"]`)).toHaveCount(0);
    });

    test('revisiting the complete step sends the customer back to the cart rather than looping', async ({ page, browser }) => {
        const { code, editHref } = await issueGiftCard(browser);

        await addSomethingToCart(page);
        await applyGiftCard(page, code);
        // the cart lists the code grouped in fours, so the row is found through its remove form, which carries the raw code
        await expect(page.locator(`form[action*="/gift-cards/${code}/remove"]`)).toBeVisible();
        await checkoutToCompleteStep(page);

        await disableGiftCard(browser, editHref);

        // Sylius' checkout resolver asks the state machine whether the step can be applied on every request to a
        // checkout page; with the guard saying no, it would redirect to the complete step forever
        const response = await page.goto('/en_US/checkout/complete');
        expect(response?.status()).toBe(200);
        await expect(page).toHaveURL(/\/en_US\/cart\/?$/);
        await expect(page.locator('.sylius-flash-message').filter({ hasText: grouped(code) })).toBeVisible();
    });
});
