const { test, expect } = require('@playwright/test');

/**
 * Redemption through the shop UI.
 *
 * A redeemed gift card becomes a payment against the order rather than a discount on it, so the order total
 * is deliberately left alone and what moves is the amount still to be paid by other means.
 */
// Seeded with a known code and a balance far larger than a cart, from the test application's fixtures
const GIFT_CARD_CODE = 'E2EREDEMPTION01';
// Codes are shown grouped in fours for reading (GiftCardCodeNormalizer::format()); the raw code only appears in form actions
const GIFT_CARD_CODE_AS_DISPLAYED = GIFT_CARD_CODE.match(/.{1,4}/g).join('-');

const GIFT_CARD_FIELD = '[name="setono_sylius_gift_card_add_gift_card_to_order[giftCard]"]';

/**
 * @param {import('@playwright/test').Page} page
 */
async function addSomethingToCart(page) {
    await page.goto('/en_US/');

    const href = await page.locator('a[href*="/products/"]').evaluateAll((links) => {
        const found = links
            .map((l) => l.getAttribute('href') ?? '')
            // a gift card product cannot be paid for with a gift card, so the cart needs an ordinary one
            .filter((h) => /\/products\//.test(h) && !h.includes('gift-card'));
        return found[0] ?? null;
    });
    expect(href, 'no ordinary product found in the shop').toBeTruthy();

    await page.goto(href);
    await page.locator('form[name="sylius_add_to_cart"] button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
}

/**
 * @param {import('@playwright/test').Page} page
 */
async function applyGiftCard(page, code) {
    await page.goto('/en_US/cart/');
    await page.locator(GIFT_CARD_FIELD).fill(code);

    // scoped to the gift card form, because the cart also carries an "Apply coupon" button
    const giftCardForm = page.locator('form').filter({ has: page.locator(GIFT_CARD_FIELD) });
    await giftCardForm.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
}

/**
 * The row listing the applied gift card. Found through its remove form, whose action carries the raw code, because
 * the cell shows the code grouped. Scoped to that form rather than `form[action*="remove"]`, which would also match
 * the cart's line item removal and empty the cart instead
 *
 * @param {import('@playwright/test').Page} page
 */
function appliedGiftCardRow(page) {
    return page.locator('tr').filter({ has: page.locator(`form[action*="/gift-cards/${GIFT_CARD_CODE}/remove"]`) });
}

/**
 * The order total as shown in the cart summary, in cents, so amounts can be compared without caring about how
 * the channel formats money.
 *
 * Deliberately not `.sylius-total`: that is the *items* total, which a gift card never changes. What
 * redemption moves is the order total, the figure the customer would actually pay.
 *
 * @param {import('@playwright/test').Page} page
 */
async function orderTotalInCents(page) {
    const text = await page.locator('body').innerText();
    const match = /Order total:?\s*[^\d-]*(-?[\d.,]+)/i.exec(text);
    expect(match, 'could not find an order total in the cart summary').not.toBeNull();

    return Math.round(parseFloat(match[1].replace(/,/g, '')) * 100);
}

/**
 * What is left for the customer to pay once the gift cards are counted.
 *
 * @param {import('@playwright/test').Page} page
 */
async function remainingToPayInCents(page) {
    const text = await page.locator('body').innerText();
    const match = /Remaining to pay:?\s*[^\d-]*(-?[\d.,]+)/i.exec(text);
    expect(match, 'could not find a remaining to pay figure in the cart summary').not.toBeNull();

    return Math.round(parseFloat(match[1].replace(/,/g, '')) * 100);
}

test.describe('shop redemption', () => {
    test('a gift card can be applied and covers what is owed', async ({ page }) => {
        await addSomethingToCart(page);

        await page.goto('/en_US/cart/');
        const before = await orderTotalInCents(page);
        expect(before, 'the cart should cost something before redeeming').toBeGreaterThan(0);

        await applyGiftCard(page, GIFT_CARD_CODE);

        // the card is listed as applied, its code grouped for reading
        await expect(appliedGiftCardRow(page)).toBeVisible();
        await expect(appliedGiftCardRow(page)).toContainText(GIFT_CARD_CODE_AS_DISPLAYED);

        // The gift card pays for the order rather than discounting it, so the order still costs what it did
        expect(await orderTotalInCents(page), 'redeeming should not change what the order costs').toBe(before);
        expect(await remainingToPayInCents(page), 'the gift card should cover the whole order').toBe(0);
    });

    test('an applied gift card can be removed again', async ({ page }) => {
        await addSomethingToCart(page);

        await page.goto('/en_US/cart/');
        const before = await orderTotalInCents(page);

        await applyGiftCard(page, GIFT_CARD_CODE);
        await expect(appliedGiftCardRow(page)).toBeVisible();

        await appliedGiftCardRow(page).locator('button[type="submit"]').first().click();
        await page.waitForLoadState('networkidle');

        await expect(appliedGiftCardRow(page)).toHaveCount(0);
        await expect(page.getByText(GIFT_CARD_CODE_AS_DISPLAYED, { exact: false })).toHaveCount(0);
        expect(await orderTotalInCents(page), 'removing the gift card should restore the total').toBe(before);
    });

    test('an unknown gift card code is rejected', async ({ page }) => {
        await addSomethingToCart(page);

        await applyGiftCard(page, 'NOSUCHCARD000000');

        await expect(page.getByText('NOSUCHCARD000000', { exact: false })).toHaveCount(0);
    });

    /**
     * The code field carries no visible label and the remove button is an icon, so both are addressed here the
     * way assistive technology addresses them — by accessible name — rather than by CSS selector.
     */
    test('the redemption controls have accessible names', async ({ page }) => {
        await addSomethingToCart(page);
        await page.goto('/en_US/cart/');

        const codeField = page.getByLabel('Gift card code');
        await expect(codeField).toHaveCount(1);
        await codeField.fill(GIFT_CARD_CODE);

        const giftCardForm = page.locator('form').filter({ has: page.locator(GIFT_CARD_FIELD) });
        await giftCardForm.locator('button[type="submit"]').first().click();
        await page.waitForLoadState('networkidle');

        // Naming it after the card keeps the buttons apart when several cards are applied
        const remove = page.getByRole('button', { name: `Remove gift card ${GIFT_CARD_CODE}` });
        await expect(remove).toHaveCount(1);

        await remove.click();
        await page.waitForLoadState('networkidle');
        await expect(page.getByText(GIFT_CARD_CODE, { exact: false })).toHaveCount(0);
    });

    test('the cart shows what is left to pay after the gift cards', async ({ page }) => {
        await addSomethingToCart(page);
        await applyGiftCard(page, GIFT_CARD_CODE);

        await expect(page.getByText('Remaining to pay', { exact: false }).first()).toBeVisible();
    });
});
