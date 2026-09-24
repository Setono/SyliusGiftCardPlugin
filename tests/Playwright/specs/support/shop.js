/**
 * Helpers for driving the shop as a customer: finding the products to buy, filling the cart and redeeming a gift card
 * on it.
 *
 * Nothing here knows a slug, a locale or a code: the shop's own pages are read to find them, so the specs keep
 * working against a freshly seeded database and whatever locale the channel defaults to.
 */

const { expect } = require('@playwright/test');
const { moneyInCents, typedAmount } = require('./money');

const GIFT_CARD_INFORMATION = '[name*="giftCardInformation"]';
const REDEMPTION_FIELD = '[name="setono_sylius_gift_card_add_gift_card_to_order[giftCard]"]';

/** @type {{locale: string|null, giftCard: string|null, ordinary: string|null}} */
const discovered = { locale: null, giftCard: null, ordinary: null };

/**
 * A path in the shop, prefixed with the locale the shop sends a visitor to when none is given
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} path without the locale, e.g. 'cart/'
 */
async function shopPath(page, path = '') {
    if (null === discovered.locale) {
        await page.goto('/');
        const locale = /^\/([^/]+)\//.exec(new URL(page.url()).pathname)?.[1] ?? null;
        expect(locale, `the shop did not redirect to a locale, it landed on ${page.url()}`).not.toBeNull();
        discovered.locale = locale;
    }

    return `/${discovered.locale}/${path}`;
}

/**
 * Finds the product pages the home page links to: the first one that renders the gift card form and the first one
 * that renders an add to cart form without it. Cached for the run, as it costs a page load per candidate.
 *
 * @param {import('@playwright/test').Page} page
 */
async function discoverProducts(page) {
    if (null !== discovered.giftCard && null !== discovered.ordinary) {
        return;
    }

    await page.goto(await shopPath(page));
    const hrefs = await page
        .locator('a[href*="/products/"]')
        .evaluateAll((links) => [...new Set(links.map((link) => link.getAttribute('href') ?? ''))]);

    for (const href of hrefs) {
        await page.goto(href);
        if (0 === (await page.locator('form[name="sylius_add_to_cart"]').count())) {
            continue;
        }

        const isGiftCard = 0 < (await page.locator(GIFT_CARD_INFORMATION).count());
        if (isGiftCard && null === discovered.giftCard) {
            discovered.giftCard = href;
        }
        if (!isGiftCard && null === discovered.ordinary) {
            discovered.ordinary = href;
        }
        if (null !== discovered.giftCard && null !== discovered.ordinary) {
            return;
        }
    }

    throw new Error(`The home page links to no gift card product or no ordinary product (checked ${hrefs.join(', ')})`);
}

/**
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<string>}
 */
async function giftCardProductPath(page) {
    await discoverProducts(page);

    return /** @type {string} */ (discovered.giftCard);
}

/**
 * Submits the add to cart form and waits for the cart, where Sylius' script sends the customer once the item is added
 *
 * @param {import('@playwright/test').Page} page
 */
async function submitAddToCart(page) {
    const form = page.locator('form[name="sylius_add_to_cart"]');
    const cart = await form.getAttribute('data-redirect');

    await form.locator('button[type="submit"]').first().click();
    await page.waitForURL(`**${cart}`);
}

/**
 * Puts a gift card in the cart the way a customer does on the product page.
 *
 * @param {import('@playwright/test').Page} page
 * @param {{amount: number, message?: string|null, variant?: number}} card amount in minor units; variant is the
 *        position of the variant in the product's variant table, the one the page preselects when left out
 * @returns {Promise<{design: string}>} the name of the design the card is bought with
 */
async function addGiftCardToCart(page, { amount, message = null, variant = null }) {
    await page.goto(await giftCardProductPath(page));

    if (null !== variant) {
        await page.locator('[name="sylius_add_to_cart[cartItem][variant]"]').nth(variant).check();
    }

    await page.locator(`${GIFT_CARD_INFORMATION}[name*="[amount]"]`).first().fill(typedAmount(amount));
    if (null !== message) {
        await page.locator(`${GIFT_CARD_INFORMATION}[name*="[customMessage]"]`).first().fill(message);
    }

    // The design the picker has selected, named by the label of its choice
    const picked = page
        .locator('[data-js-gift-card-design-picker] .setono-gift-card-design-choice')
        .filter({ has: page.locator('input:checked') });
    const design = (await picked.innerText()).trim();

    await submitAddToCart(page);

    return { design };
}

/**
 * The number of variants the gift card product offers on its page
 *
 * @param {import('@playwright/test').Page} page
 */
async function giftCardVariantCount(page) {
    await page.goto(await giftCardProductPath(page));

    return page.locator('[name="sylius_add_to_cart[cartItem][variant]"]').count();
}

/**
 * Puts an ordinary product in the cart. A gift card cannot pay for another gift card, so this is what a gift card is
 * redeemed against.
 *
 * @param {import('@playwright/test').Page} page
 */
async function addOrdinaryProductToCart(page) {
    await discoverProducts(page);
    await page.goto(/** @type {string} */ (discovered.ordinary));

    await submitAddToCart(page);
}

/**
 * Redeems a gift card through the cart's form and checks it was applied. The cart lists the code grouped for
 * reading, so the card is recognised by its remove form, whose action carries the stored code.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} code
 */
async function redeemGiftCard(page, code) {
    await page.goto(await shopPath(page, 'cart/'));
    await page.locator(REDEMPTION_FIELD).fill(code);

    // scoped to the gift card form, because the cart also carries an "Apply coupon" button
    const form = page.locator('form').filter({ has: page.locator(REDEMPTION_FIELD) });
    await form.locator('button[type="submit"]').first().click();
    await page.waitForLoadState();

    await expect(page.locator(`form[action*="/gift-cards/${code}/remove"]`), `the gift card ${code} was not applied`).toHaveCount(1);
}

/**
 * A figure of the cart summary, in minor units, found by the label of its row: "Order total", or the plugin's
 * "Remaining to pay" row below it
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} label
 */
async function cartFigure(page, label) {
    const row = page.locator('tr').filter({ hasText: new RegExp(`^\\s*${label}:?`, 'i') }).last();
    await expect(row, `the cart summary shows no "${label}"`).toBeVisible();

    return moneyInCents(await row.locator('td').last().innerText());
}

module.exports = {
    addGiftCardToCart,
    addOrdinaryProductToCart,
    cartFigure,
    giftCardProductPath,
    giftCardVariantCount,
    redeemGiftCard,
    shopPath,
};
