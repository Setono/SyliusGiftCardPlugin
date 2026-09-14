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

// Also seeded: codes that exist and still cannot be used, one disabled and one without a balance left
const DISABLED_GIFT_CARD_CODE = 'E2EDISABLED0001';
const SPENT_GIFT_CARD_CODE = 'E2ENOBALANCE01';

const GIFT_CARD_FIELD = '[name="setono_sylius_gift_card_add_gift_card_to_order[giftCard]"]';

/**
 * The code the way the PDF prints it, in groups of four separated by dashes, e.g. E2ER-EDEM-PTIO-N01
 *
 * @param {string} code
 */
function asPrintedOnTheCard(code) {
    return code.match(/.{1,4}/g).join('-');
}

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
 * The amount printed after a label in the cart summary, in cents, so amounts can be compared without caring about
 * how the channel formats money. A negative amount is formatted by the money macro, which puts the minus sign
 * before the currency symbol in some locales and after it in others, so both placements are accepted.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} label
 * @param {string} description
 */
async function labelledAmountInCents(page, label, description) {
    const text = await page.locator('body').innerText();
    const match = new RegExp(`${label}:?\\s*(-?)[^\\d-]*(-?)([\\d.,]+)`, 'i').exec(text);
    expect(match, description).not.toBeNull();

    const sign = match[1] === '-' || match[2] === '-' ? -1 : 1;

    return sign * Math.round(parseFloat(match[3].replace(/,/g, '')) * 100);
}

/**
 * The error the shop answered the last gift card submission with.
 *
 * @param {import('@playwright/test').Page} page
 */
async function giftCardError(page) {
    const messages = page.locator('.sylius-flash-message.negative p');
    await expect(messages.first(), 'the shop did not report an error at all').toBeVisible();

    const texts = await messages.allInnerTexts();

    return texts.map((text) => text.trim()).join(' | ');
}

/**
 * The order total as shown in the cart summary.
 *
 * Deliberately not `.sylius-total`: that is the *items* total, which a gift card never changes. What
 * redemption moves is the order total, the figure the customer would actually pay.
 *
 * @param {import('@playwright/test').Page} page
 */
async function orderTotalInCents(page) {
    return labelledAmountInCents(page, 'Order total', 'could not find an order total in the cart summary');
}

/**
 * What the applied gift cards take off what is owed, as a negative amount.
 *
 * @param {import('@playwright/test').Page} page
 */
async function giftCardsTotalInCents(page) {
    return labelledAmountInCents(page, 'Gift cards', 'could not find a gift cards total in the cart summary');
}

/**
 * What is left for the customer to pay once the gift cards are counted.
 *
 * @param {import('@playwright/test').Page} page
 */
async function remainingToPayInCents(page) {
    return labelledAmountInCents(page, 'Remaining to pay', 'could not find a remaining to pay figure in the cart summary');
}

/**
 * Document order of the summary figures, as indexes into every table row on the page. The gift card figures used to
 * be emitted as bare `div`s above "Items total", so this pins both that they are rows of a totals table and that
 * they follow the order total rather than preceding the items total.
 *
 * @param {import('@playwright/test').Page} page
 */
async function summaryRowPositions(page) {
    return page.evaluate(() => {
        const rows = Array.from(document.querySelectorAll('tr'));
        const position = (pattern) => rows.findIndex((row) => new RegExp(pattern, 'i').test(row.innerText));

        return {
            itemsTotal: position('items total'),
            orderTotal: position('order total'),
            giftCards: position('gift cards'),
            remaining: position('remaining to pay'),
        };
    });
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

    test('a code typed the way it is printed on the card is accepted', async ({ page }) => {
        await addSomethingToCart(page);

        // Customers copy the code off the card, dashes included, in whatever case they like
        await applyGiftCard(page, asPrintedOnTheCard(GIFT_CARD_CODE).toLowerCase());

        // Applied is judged by the card's own remove form, whose action carries the stored code whichever
        // way the cart chooses to display it
        await expect(
            page.locator(`form[action*="/gift-cards/${GIFT_CARD_CODE}/remove"] button[type="submit"]`).first(),
        ).toBeVisible();
        expect(await remainingToPayInCents(page), 'the gift card should cover the whole order').toBe(0);
    });

    test('an unknown gift card code is rejected', async ({ page }) => {
        await addSomethingToCart(page);

        await applyGiftCard(page, 'NOSUCHCARD000000');

        await expect(page.getByText('NOSUCHCARD000000', { exact: false })).toHaveCount(0);
        expect(await giftCardError(page), 'the rejection should not name the code or the reason')
            .not.toContain('NOSUCHCARD000000');
    });

    /**
     * A code that exists must not be distinguishable from one that does not: a different answer for a gift
     * card that is merely unusable turns this form into a way of finding out which codes are real.
     */
    test('an unusable gift card code gets the same answer as an unknown one', async ({ page }) => {
        await addSomethingToCart(page);

        await applyGiftCard(page, 'NOSUCHCARD000000');
        const unknown = await giftCardError(page);

        await applyGiftCard(page, DISABLED_GIFT_CARD_CODE);
        const disabled = await giftCardError(page);

        await applyGiftCard(page, SPENT_GIFT_CARD_CODE);
        const spent = await giftCardError(page);

        expect(unknown, 'the shop should say something when a code is rejected').not.toBe('');
        expect(disabled, 'a disabled code must be answered exactly like an unknown one').toBe(unknown);
        expect(spent, 'a spent code must be answered exactly like an unknown one').toBe(unknown);

        // none of them end up on the order
        await expect(page.getByText(DISABLED_GIFT_CARD_CODE, { exact: false })).toHaveCount(0);
        await expect(page.getByText(SPENT_GIFT_CARD_CODE, { exact: false })).toHaveCount(0);
    });

    test('the cart shows what is left to pay after the gift cards', async ({ page }) => {
        await addSomethingToCart(page);
        await applyGiftCard(page, GIFT_CARD_CODE);

        await expect(page.getByText('Remaining to pay', { exact: false }).first()).toBeVisible();
    });

    test('the gift card figures are laid out as totals rows below the order total', async ({ page }) => {
        await addSomethingToCart(page);
        await applyGiftCard(page, GIFT_CARD_CODE);

        const positions = await summaryRowPositions(page);
        expect(positions.itemsTotal, 'no items total row in the cart summary').toBeGreaterThanOrEqual(0);
        expect(positions.orderTotal, 'the order total should follow the items total').toBeGreaterThan(positions.itemsTotal);
        expect(positions.giftCards, 'the gift cards figure should be a totals row below the order total').toBeGreaterThan(positions.orderTotal);
        expect(positions.remaining, 'the remaining to pay figure should follow the gift cards one').toBeGreaterThan(positions.giftCards);

        // and the figures still read as money, with the deduction expressed as a negative amount
        const total = await orderTotalInCents(page);
        const giftCards = await giftCardsTotalInCents(page);
        expect(total, 'the cart should cost something').toBeGreaterThan(0);
        expect(giftCards, 'the gift cards figure should be a deduction').toBeLessThan(0);
        expect(await remainingToPayInCents(page), 'the remaining total should be the order total less the gift cards').toBe(total + giftCards);
    });
});
