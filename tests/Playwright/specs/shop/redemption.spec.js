const { test, expect } = require('@playwright/test');

/**
 * Redemption through the shop UI.
 *
 * The plugin redeems in one of two modes, chosen by setono_sylius_gift_card.redemption.mode. The mode picks
 * which service file the extension loads, so it is fixed when the container is compiled and cannot be flipped
 * at runtime — running these specs in payment mode means building a second container. See
 * tests/Application/config/redemption_payment.yaml.
 *
 * REDEMPTION_MODE tells the specs which container they are pointed at, so they can assert the part of the UI
 * that actually differs: payment mode shows what is left to pay after the gift cards, adjustment mode does
 * not, because there the gift card is an order adjustment rather than a payment covering part of the total.
 */
const MODE = process.env.REDEMPTION_MODE ?? 'adjustment';

// Seeded with a known code and a balance far larger than a cart, from the test application's fixtures
const GIFT_CARD_CODE = 'E2EREDEMPTION01';

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
 * What is left for the customer to pay once the gift cards are counted. Only payment mode shows this.
 *
 * @param {import('@playwright/test').Page} page
 */
async function remainingToPayInCents(page) {
    const text = await page.locator('body').innerText();
    const match = /Remaining to pay:?\s*[^\d-]*(-?[\d.,]+)/i.exec(text);
    expect(match, 'could not find a remaining to pay figure in the cart summary').not.toBeNull();

    return Math.round(parseFloat(match[1].replace(/,/g, '')) * 100);
}

test.describe(`shop redemption (${MODE} mode)`, () => {
    test('a gift card can be applied and covers what is owed', async ({ page }) => {
        await addSomethingToCart(page);

        await page.goto('/en_US/cart/');
        const before = await orderTotalInCents(page);
        expect(before, 'the cart should cost something before redeeming').toBeGreaterThan(0);

        await applyGiftCard(page, GIFT_CARD_CODE);

        // the card is listed as applied, by the code that was entered
        await expect(page.getByText(GIFT_CARD_CODE, { exact: false }).first()).toBeVisible();

        const after = await orderTotalInCents(page);

        if ('payment' === MODE) {
            // The gift card becomes a payment against the order, so the order still costs what it did — what
            // changes is how much of it is left for the customer to pay by other means
            expect(after, 'a gift card payment should not change what the order costs').toBe(before);
            expect(await remainingToPayInCents(page), 'the gift card should cover the whole order').toBe(0);
        } else {
            // The gift card is a negative adjustment on the order, so it reduces the total itself
            expect(after, 'a gift card adjustment should reduce what is owed').toBeLessThan(before);
        }
    });

    test('an applied gift card can be removed again', async ({ page }) => {
        await addSomethingToCart(page);

        await page.goto('/en_US/cart/');
        const before = await orderTotalInCents(page);

        await applyGiftCard(page, GIFT_CARD_CODE);
        await expect(page.getByText(GIFT_CARD_CODE, { exact: false }).first()).toBeVisible();

        // Scoped to the gift card's own remove form. `form[action*="remove"]` would also match the cart's
        // line item removal, which empties the cart instead and makes the assertions below meaningless
        await page.locator(`form[action*="/gift-cards/${GIFT_CARD_CODE}/remove"] button[type="submit"]`).first().click();
        await page.waitForLoadState('networkidle');

        await expect(page.getByText(GIFT_CARD_CODE, { exact: false })).toHaveCount(0);
        expect(await orderTotalInCents(page), 'removing the gift card should restore the total').toBe(before);
    });

    test('an unknown gift card code is rejected', async ({ page }) => {
        await addSomethingToCart(page);

        await applyGiftCard(page, 'NOSUCHCARD000000');

        await expect(page.getByText('NOSUCHCARD000000', { exact: false })).toHaveCount(0);
    });

    test('the totals reflect the redemption mode the container was built with', async ({ page }) => {
        await addSomethingToCart(page);
        await applyGiftCard(page, GIFT_CARD_CODE);

        // Only payment mode has a notion of "what is still to be paid by other means", because there the gift
        // card becomes a payment alongside the others rather than an adjustment on the order
        const remaining = page.getByText('Remaining to pay', { exact: false });

        if ('payment' === MODE) {
            await expect(remaining.first()).toBeVisible();
        } else {
            await expect(remaining).toHaveCount(0);
        }
    });
});
