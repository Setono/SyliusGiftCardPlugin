/**
 * The cart helpers under the names the older specs know them by. They live in shop.js, which finds the products and
 * the locale instead of assuming them; new specs import from there.
 */

const { REDEMPTION_FIELD, addOrdinaryProductToCart, applyGiftCard } = require('./shop');

module.exports = {
    GIFT_CARD_FIELD: REDEMPTION_FIELD,
    // a gift card product cannot be paid for with a gift card, so the cart needs an ordinary one
    addSomethingToCart: addOrdinaryProductToCart,
    applyGiftCard,
};
