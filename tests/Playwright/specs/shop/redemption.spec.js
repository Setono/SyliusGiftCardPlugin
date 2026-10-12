const { test, expect } = require('@playwright/test');
const { signInAsAdministrator } = require('../support/admin');
const { blankIcons } = require('../support/icons');
const { moneyInCents } = require('../support/money');
const { GIFT_CARD_PAYMENT_METHOD_CODE, setPaymentMethodEnabled } = require('../support/payment-methods');
const {
    REDEMPTION_FIELD,
    addOrdinaryProductToCart,
    appliedGiftCardRow,
    applyGiftCard,
    cartFigure,
    removeGiftCard,
    shopErrors,
    shopPath,
} = require('../support/shop');

/**
 * Redemption through the shop UI.
 *
 * A redeemed gift card becomes a payment against the order rather than a discount on it, so the order total
 * is deliberately left alone and what moves is the amount still to be paid by other means.
 *
 * The cards are only applied to carts here, never spent, so the seeded ones keep their balance for every other spec.
 * The figures and controls are read through the templates' data-test-* hooks, so nothing depends on the locale the
 * shop is browsed in or on how it formats money.
 */
// Seeded with a known code and a balance far larger than a cart, from the test application's fixtures
const GIFT_CARD_CODE = 'E2EREDEMPTION01';

// Also seeded: codes that exist and still cannot be used, one disabled and one without a balance left
const DISABLED_GIFT_CARD_CODE = 'E2EDISABLED0001';
const SPENT_GIFT_CARD_CODE = 'E2ENOBALANCE01';

// A code no card has
const UNKNOWN_GIFT_CARD_CODE = 'NOSUCHCARD000000';

/**
 * The code the way the card, the cart and the emails print it, in groups of four separated by dashes, e.g.
 * E2ER-EDEM-PTIO-N01 (GiftCardCodeNormalizer::format())
 *
 * @param {string} code
 */
function asPrintedOnTheCard(code) {
    return code.match(/.{1,4}/g).join('-');
}

/**
 * The error the shop answered the last gift card submission with. The cart must not list any applied card then, nor
 * the figures that come with one.
 *
 * @param {import('@playwright/test').Page} page
 */
async function giftCardRejection(page) {
    const errors = await shopErrors(page);
    expect(errors, 'the shop should have said why the code was refused').toHaveLength(1);

    await expect(page.locator('[data-test-applied-gift-cards]'), 'a refused code must not be listed as applied').toHaveCount(0);
    await expect(page.locator('[data-test-gift-card-totals]'), 'a refused code must not change what is left to pay').toHaveCount(0);

    return errors[0];
}

test.describe('shop redemption', () => {
    test.beforeEach(async ({ page }) => {
        await addOrdinaryProductToCart(page);
    });

    test('a gift card can be applied and covers what is owed', async ({ page }) => {
        await page.goto(await shopPath(page, 'cart/'));
        const before = await cartFigure(page, 'Order total');
        expect(before, 'the cart should cost something before redeeming').toBeGreaterThan(0);

        await applyGiftCard(page, GIFT_CARD_CODE);

        // the card is listed as applied, its code grouped for reading, covering the whole order
        const row = appliedGiftCardRow(page, GIFT_CARD_CODE);
        await expect(row).toBeVisible();
        await expect(row.locator('td').first()).toHaveText(asPrintedOnTheCard(GIFT_CARD_CODE));
        expect(moneyInCents(await row.locator('td').nth(1).innerText()), 'the card should cover the whole order').toBe(-before);

        // The gift card pays for the order rather than discounting it, so the order still costs what it did
        expect(await cartFigure(page, 'Order total'), 'redeeming should not change what the order costs').toBe(before);
        expect(await cartFigure(page, 'Gift cards')).toBe(-before);
        expect(await cartFigure(page, 'Remaining to pay'), 'the gift card should cover the whole order').toBe(0);
    });

    test('an applied gift card can be removed again', async ({ page }) => {
        await page.goto(await shopPath(page, 'cart/'));
        const before = await cartFigure(page, 'Order total');

        await applyGiftCard(page, GIFT_CARD_CODE);
        await expect(appliedGiftCardRow(page, GIFT_CARD_CODE)).toBeVisible();

        await removeGiftCard(page, GIFT_CARD_CODE);

        await expect(appliedGiftCardRow(page, GIFT_CARD_CODE)).toHaveCount(0);
        await expect(page.locator('[data-test-applied-gift-cards]')).toHaveCount(0);
        await expect(page.locator('[data-test-gift-card-totals]'), 'nothing is covered once the card is removed').toHaveCount(0);
        expect(await cartFigure(page, 'Order total'), 'removing the gift card should leave the total alone').toBe(before);
    });

    test('a code typed the way it is printed on the card is accepted', async ({ page }) => {
        // Customers copy the code off the card, dashes included, in whatever case they like
        await applyGiftCard(page, asPrintedOnTheCard(GIFT_CARD_CODE).toLowerCase());

        // the card is applied under its stored code, whichever way it was typed
        await expect(appliedGiftCardRow(page, GIFT_CARD_CODE)).toBeVisible();
        expect(await cartFigure(page, 'Remaining to pay'), 'the gift card should cover the whole order').toBe(0);
    });

    test('an unknown gift card code is rejected', async ({ page }) => {
        await applyGiftCard(page, UNKNOWN_GIFT_CARD_CODE);

        const error = await giftCardRejection(page);
        expect(error, 'the rejection should not name the code').not.toContain(UNKNOWN_GIFT_CARD_CODE);
        // the message has to be translated, not a raw key: flash messages resolve in the flashes domain
        expect(error).not.toMatch(/^setono_sylius_gift_card\./);
        await expect(page.locator(REDEMPTION_FIELD), 'the form should be offered again').toBeVisible();
    });

    /**
     * A code that exists must not be distinguishable from one that does not: a different answer for a gift
     * card that is merely unusable turns this form into a way of finding out which codes are real.
     */
    test('an unusable gift card code gets the same answer as an unknown one', async ({ page }) => {
        await applyGiftCard(page, UNKNOWN_GIFT_CARD_CODE);
        const unknown = await giftCardRejection(page);

        await applyGiftCard(page, DISABLED_GIFT_CARD_CODE);
        const disabled = await giftCardRejection(page);

        await applyGiftCard(page, SPENT_GIFT_CARD_CODE);
        const spent = await giftCardRejection(page);

        expect(unknown, 'the shop should say something when a code is rejected').not.toBe('');
        expect(disabled, 'a disabled code must be answered exactly like an unknown one').toBe(unknown);
        expect(spent, 'a spent code must be answered exactly like an unknown one').toBe(unknown);
    });

    /**
     * While the gift card payment method is disabled the shop takes no gift cards (#484). The cart keeps its gift card
     * field all the same, and refuses every code, a usable one and one no card has alike, before looking at it: with an
     * answer of its own rather than the one an unknown code gets, as nothing is wrong with the code. Once the method is
     * enabled again the same card is taken. The finally enables it, whatever fails in between, so the specs after this
     * one find a shop that takes gift cards
     */
    test('while the gift card payment method is disabled, the cart keeps its field but refuses every code', async ({ page, browser }) => {
        await applyGiftCard(page, UNKNOWN_GIFT_CARD_CODE);
        const unknown = await giftCardRejection(page);

        const admin = await signInAsAdministrator(browser);
        try {
            const restore = await setPaymentMethodEnabled(admin.page, GIFT_CARD_PAYMENT_METHOD_CODE, false);
            try {
                await page.goto(await shopPath(page, 'cart/'));
                await expect(page.locator(REDEMPTION_FIELD), 'the cart should still offer the gift card field').toBeVisible();

                await applyGiftCard(page, GIFT_CARD_CODE);
                const refused = await giftCardRejection(page);
                expect(refused, 'nothing is wrong with the code, so the answer should not be the unknown code\'s').not.toBe(unknown);

                await applyGiftCard(page, UNKNOWN_GIFT_CARD_CODE);
                expect(await giftCardRejection(page), 'every code should get the same answer, before it is looked at').toBe(refused);
                await expect(page.locator(REDEMPTION_FIELD), 'the form should be offered again').toBeVisible();
            } finally {
                await restore();
            }
        } finally {
            await admin.close();
        }

        await applyGiftCard(page, GIFT_CARD_CODE);
        await expect(appliedGiftCardRow(page, GIFT_CARD_CODE)).toBeVisible();
    });

    /**
     * The code field carries no visible label and the remove button is an icon, so both have to carry a name of their
     * own, which is how assistive technology addresses them. The names are translated, so they are read rather than
     * known: the field's has to be there, and the button's has to name the card, which keeps the buttons apart when
     * several cards are applied.
     */
    test('the redemption controls have accessible names', async ({ page }) => {
        await page.goto(await shopPath(page, 'cart/'));

        const field = page.locator(REDEMPTION_FIELD);
        const fieldName = (await field.getAttribute('aria-label')) ?? '';
        expect(fieldName.trim(), 'the code field has no accessible name').not.toBe('');
        await expect(page.getByLabel(fieldName, { exact: true })).toHaveCount(1);

        await applyGiftCard(page, GIFT_CARD_CODE);

        const remove = page.locator(`[data-test-remove-gift-card-button="${GIFT_CARD_CODE}"]`);
        await expect(remove).toHaveAccessibleName(new RegExp(GIFT_CARD_CODE));
        // the icon is all a sighted customer sees of the button
        expect(await blankIcons(remove), 'the icons of the remove button that draw nothing').toEqual([]);
        await expect(page.getByRole('button', { name: GIFT_CARD_CODE })).toHaveCount(1);

        await removeGiftCard(page, GIFT_CARD_CODE);
        await expect(appliedGiftCardRow(page, GIFT_CARD_CODE)).toHaveCount(0);
    });

    test('the gift card figures are laid out as totals rows below the order total', async ({ page }) => {
        await applyGiftCard(page, GIFT_CARD_CODE);

        // The gift card figures used to be emitted as bare divs above "Items total", so this pins both that they are
        // rows of a totals table and that they follow the order total rather than preceding the items total
        const layout = await page.evaluate(() => {
            const element = (hook) => document.querySelector(`[data-test-${hook}]`);
            const follows = (later, earlier) =>
                null !== later && null !== earlier && 0 !== (earlier.compareDocumentPosition(later) & Node.DOCUMENT_POSITION_FOLLOWING);

            const itemsTotal = element('cart-items-total');
            const orderTotal = element('cart-grand-total');
            const giftCards = element('gift-cards-total');
            const remaining = element('gift-cards-remaining-total');

            return {
                orderTotalFollowsItemsTotal: follows(orderTotal, itemsTotal),
                giftCardsFollowOrderTotal: follows(giftCards, orderTotal),
                remainingFollowsGiftCards: follows(remaining, giftCards),
                rowsOfATable: [giftCards, remaining].every((row) => null !== row && 'TR' === row.tagName && null !== row.closest('table')),
            };
        });
        expect(layout).toEqual({
            orderTotalFollowsItemsTotal: true,
            giftCardsFollowOrderTotal: true,
            remainingFollowsGiftCards: true,
            rowsOfATable: true,
        });

        // and the figures still read as money, with the deduction expressed as a negative amount
        const total = await cartFigure(page, 'Order total');
        const giftCards = await cartFigure(page, 'Gift cards');
        expect(total, 'the cart should cost something').toBeGreaterThan(0);
        expect(giftCards, 'the gift cards figure should be a deduction').toBeLessThan(0);
        expect(await cartFigure(page, 'Remaining to pay'), 'the remaining total should be the order total less the gift cards').toBe(total + giftCards);
    });
});
