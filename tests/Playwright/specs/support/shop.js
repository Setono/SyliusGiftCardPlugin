/**
 * Helpers for driving the shop as a customer: finding the products to buy, filling the cart and redeeming a gift card
 * on it, and reading the cart's figures back.
 *
 * Nothing here knows a slug, a locale or a code: the shop's own pages are read to find them, so the specs keep
 * working against a freshly seeded database and whatever locale the channel defaults to. What the specs check is
 * addressed through the templates' data-test-* hooks (Sylius' and the plugin's sylius_test_html_attribute()), never
 * through the English text around it.
 */

const { expect } = require('@playwright/test');
const { moneyInCents, typedAmount } = require('./money');
const { clickAndWaitForPage } = require('./navigation');

const GIFT_CARD_INFORMATION = '[name*="giftCardInformation"]';
const REDEMPTION_FIELD = '[name="setono_sylius_gift_card_add_gift_card_to_order[giftCard]"]';

/**
 * The figures of the cart summary, each by the hook of the element holding it: Sylius' own totals table, and the
 * plugin's rows below it, whose figure is their last cell
 */
const CART_FIGURES = {
    'Items total': '[data-test-cart-items-total]',
    'Order total': '[data-test-cart-grand-total]',
    'Gift cards': '[data-test-gift-cards-total] > td:last-child',
    'Remaining to pay': '[data-test-gift-cards-remaining-total] > td:last-child',
};

/** @type {{locale: string|null, products: Map<string, {giftCard: string|null, ordinary: string|null}>}} */
const discovered = { locale: null, products: new Map() };

/**
 * The locale the shop sends a visitor to when the address names none, read off that first redirect
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<string>}
 */
async function shopLocale(page) {
    if (null === discovered.locale) {
        await page.goto('/');
        const locale = /^\/([^/]+)\//.exec(new URL(page.url()).pathname)?.[1] ?? null;
        expect(locale, `the shop did not redirect to a locale, it landed on ${page.url()}`).not.toBeNull();
        discovered.locale = locale;
    }

    return /** @type {string} */ (discovered.locale);
}

/**
 * Every locale the shop can be browsed in: the one it sends a visitor to, and those its locale switcher offers
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<string[]>}
 */
async function shopLocales(page) {
    const locale = await shopLocale(page);
    await page.goto(`/${locale}/`);

    // the switcher links to /switch-locale/{code} for each locale but the one being browsed
    const offered = await page
        .locator('a[href*="/switch-locale/"]')
        .evaluateAll((links) => links.map((link) => /\/switch-locale\/([^/?#]+)/.exec(link.getAttribute('href') ?? '')?.[1] ?? ''));

    return [...new Set([locale, ...offered.filter((code) => '' !== code)])];
}

/**
 * A path in the shop, prefixed with a locale: the one the shop sends a visitor to, unless another is given
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} path without the locale, e.g. 'cart/'
 * @param {string|null} locale
 */
async function shopPath(page, path = '', locale = null) {
    return `/${locale ?? (await shopLocale(page))}/${path}`;
}

/**
 * Finds the product pages the home page links to in the given locale: the first one that renders the gift card form
 * and the first one that renders an add to cart form without it. Cached per locale for the run, as it costs a page
 * load per candidate.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string|null} locale
 * @returns {Promise<{giftCard: string, ordinary: string}>}
 */
async function discoverProducts(page, locale = null) {
    const key = locale ?? (await shopLocale(page));
    const found = discovered.products.get(key) ?? { giftCard: null, ordinary: null };
    discovered.products.set(key, found);

    if (null === found.giftCard || null === found.ordinary) {
        await page.goto(await shopPath(page, '', key));
        const hrefs = await page
            .locator('a[href*="/products/"]')
            .evaluateAll((links) => [...new Set(links.map((link) => link.getAttribute('href') ?? ''))]);

        for (const href of hrefs) {
            await page.goto(href);
            if (0 === (await page.locator('form[name="sylius_add_to_cart"]').count())) {
                continue;
            }

            const isGiftCard = 0 < (await page.locator(GIFT_CARD_INFORMATION).count());
            if (isGiftCard && null === found.giftCard) {
                found.giftCard = href;
            }
            if (!isGiftCard && null === found.ordinary) {
                found.ordinary = href;
            }
            if (null !== found.giftCard && null !== found.ordinary) {
                break;
            }
        }

        if (null === found.giftCard || null === found.ordinary) {
            throw new Error(`The ${key} home page links to no gift card product or no ordinary product (checked ${hrefs.join(', ')})`);
        }
    }

    return /** @type {{giftCard: string, ordinary: string}} */ (found);
}

/**
 * @param {import('@playwright/test').Page} page
 * @param {string|null} locale
 * @returns {Promise<string>}
 */
async function giftCardProductPath(page, locale = null) {
    return (await discoverProducts(page, locale)).giftCard;
}

/**
 * @param {import('@playwright/test').Page} page
 * @param {string|null} locale
 * @returns {Promise<string>}
 */
async function ordinaryProductPath(page, locale = null) {
    return (await discoverProducts(page, locale)).ordinary;
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
 * @param {{amount: number, message?: string|null, variant?: number|null}} card amount in minor units; variant is the
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
    await page.goto(await ordinaryProductPath(page));

    await submitAddToCart(page);
}

/**
 * Submits the code through the cart's gift card form and waits for the cart it leads back to. Whether the card was
 * accepted is left to the caller, since some specs submit codes that must be rejected.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} code
 */
async function applyGiftCard(page, code) {
    await page.goto(await shopPath(page, 'cart/'));
    await page.locator(REDEMPTION_FIELD).fill(code);

    // the plugin's own button, as the cart also carries Sylius' "Apply coupon"
    await clickAndWaitForPage(page, page.locator('[data-test-apply-gift-card-button]'));
}

/**
 * The cart's row of the applied gift card with the given code, as it is stored (the row shows it grouped for reading)
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} code
 */
function appliedGiftCardRow(page, code) {
    return page.locator(`[data-test-applied-gift-card="${code}"]`);
}

/**
 * Redeems a gift card through the cart's form and checks it was applied.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} code the code as it is stored, which the applied card's row is recognised by
 * @param {string} typed the code the way the customer types it, the stored one when left out
 */
async function redeemGiftCard(page, code, typed = code) {
    await applyGiftCard(page, typed);

    await expect(appliedGiftCardRow(page, code), `the gift card ${code} was not applied`).toHaveCount(1);
}

/**
 * Removes the applied gift card with the given code through its button in the cart
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} code
 */
async function removeGiftCard(page, code) {
    await clickAndWaitForPage(page, page.locator(`[data-test-remove-gift-card-button="${code}"]`));
}

/**
 * A figure of the cart summary, in minor units: 'Items total' or 'Order total' from Sylius' totals, or the plugin's
 * 'Gift cards' (what the applied cards cover, as a negative amount) and 'Remaining to pay' rows below them. The names
 * only pick the hook to read; the page may be in any locale.
 *
 * @param {import('@playwright/test').Page} page
 * @param {'Items total'|'Order total'|'Gift cards'|'Remaining to pay'} figure
 */
async function cartFigure(page, figure) {
    const hook = CART_FIGURES[figure];
    if (undefined === hook) {
        throw new Error(`The cart summary has no figure "${figure}", only ${Object.keys(CART_FIGURES).join(', ')}`);
    }

    const element = page.locator(hook);
    await expect(element, `the cart summary shows no "${figure}"`).toHaveCount(1);

    return moneyInCents(await element.innerText());
}

/**
 * The error messages the page flashes, e.g. why a gift card was refused, one string per message
 *
 * @param {import('@playwright/test').Page} page
 */
async function shopErrors(page) {
    return (await page.locator('.sylius-flash-message.negative [data-test-flash-messages]').allInnerTexts()).map((text) =>
        text.replace(/\s+/g, ' ').trim(),
    );
}

module.exports = {
    GIFT_CARD_INFORMATION,
    REDEMPTION_FIELD,
    addGiftCardToCart,
    addOrdinaryProductToCart,
    appliedGiftCardRow,
    applyGiftCard,
    cartFigure,
    giftCardProductPath,
    giftCardVariantCount,
    ordinaryProductPath,
    redeemGiftCard,
    removeGiftCard,
    shopErrors,
    shopLocale,
    shopLocales,
    shopPath,
    submitAddToCart,
};
