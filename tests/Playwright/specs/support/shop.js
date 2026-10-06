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
/** The radio buttons of Sylius' variant table, which a product with one variant and no options does not get */
const VARIANT_CHOICE = '[name="sylius_add_to_cart[cartItem][variant]"]';

/**
 * The product pages found so far: the gift card product offering a choice of delivery types, a gift card product
 * with a single delivery type (a simple product, without a variant choice) and an ordinary product
 *
 * @typedef {'giftCard'|'singleDeliveryTypeGiftCard'|'ordinary'} ProductKind
 * @type {{locale: string|null, giftCard: string|null, singleDeliveryTypeGiftCard: string|null, ordinary: string|null}}
 */
const discovered = { locale: null, giftCard: null, singleDeliveryTypeGiftCard: null, ordinary: null };

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
 * Finds the product pages the home page links to, the first of each kind asked for: a page rendering the gift card
 * form and a variant choice, one rendering the gift card form without a variant choice, and one rendering an add to
 * cart form without the gift card form. Cached for the run, as it costs a page load per candidate.
 *
 * The seeded gift card products are the newest products, so the home page lists them all, in no particular order.
 * That is why the gift card product the specs buy is the one offering a choice of delivery types, rather than
 * whichever gift card product comes first.
 *
 * @param {import('@playwright/test').Page} page
 * @param {ProductKind[]} kinds
 */
async function discoverProducts(page, kinds = ['giftCard', 'ordinary']) {
    const found = () => kinds.every((kind) => null !== discovered[kind]);
    if (found()) {
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

        /** @type {ProductKind} */
        let kind = 'ordinary';
        if (0 < (await page.locator(GIFT_CARD_INFORMATION).count())) {
            kind = 0 < (await page.locator(VARIANT_CHOICE).count()) ? 'giftCard' : 'singleDeliveryTypeGiftCard';
        }
        if (null === discovered[kind]) {
            discovered[kind] = href;
        }
        if (found()) {
            return;
        }
    }

    const missing = kinds.filter((kind) => null === discovered[kind]);
    throw new Error(`The home page links to no product of the kind ${missing.join(', ')} (checked ${hrefs.join(', ')})`);
}

/**
 * The page of the gift card product offering a choice of delivery types
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<string>}
 */
async function giftCardProductPath(page) {
    await discoverProducts(page);

    return /** @type {string} */ (discovered.giftCard);
}

/**
 * The page of a gift card product with a single delivery type, which Sylius treats as a simple product
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<string>}
 */
async function singleDeliveryTypeGiftCardProductPath(page) {
    await discoverProducts(page, ['singleDeliveryTypeGiftCard']);

    return /** @type {string} */ (discovered.singleDeliveryTypeGiftCard);
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
 * @param {{amount: number, message?: string|null, variant?: number|null, productPath?: string|null}} card amount in
 *        minor units; variant is the position of the variant in the product's variant table, the one the page
 *        preselects when left out; productPath is the product page, the gift card product's when left out
 * @returns {Promise<{design: string}>} the name of the design the card is bought with
 */
async function addGiftCardToCart(page, { amount, message = null, variant = null, productPath = null }) {
    await page.goto(productPath ?? (await giftCardProductPath(page)));

    if (null !== variant) {
        await page.locator(VARIANT_CHOICE).nth(variant).check();
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

    return page.locator(VARIANT_CHOICE).count();
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
    singleDeliveryTypeGiftCardProductPath,
    submitAddToCart,
    VARIANT_CHOICE,
};
