/**
 * Reading money off the page.
 *
 * Amounts are compared in minor units, the way the plugin stores them, so a spec does not depend on how the channel
 * formats money: the currency symbol, the grouping separator and where the minus sign goes all vary with the locale.
 */

/**
 * The amount a formatted money string stands for, in minor units: "$1,000.00" is 100000, "-$40.10" is -4010 and
 * "1.234,56 €" is 123456. A separator followed by exactly two digits at the end is taken as the decimal one.
 *
 * @param {string} text
 * @returns {number}
 */
function moneyInCents(text) {
    const figures = text.replace(/[^\d.,]/g, '');
    const match = /^(.*?)(?:[.,](\d{2}))?$/.exec(figures);
    if (null === match || '' === `${match[1]}${match[2] ?? ''}`) {
        throw new Error(`"${text}" is not an amount of money`);
    }

    const cents = Number(match[1].replace(/[.,]/g, '') || '0') * 100 + Number(match[2] ?? '0');

    return text.includes('-') ? -cents : cents;
}

/**
 * The amount in major units, the way an admin or a customer types it into a form, e.g. 4010 becomes "40.10".
 *
 * @param {number} cents
 * @returns {string}
 */
function typedAmount(cents) {
    return (cents / 100).toFixed(2);
}

module.exports = { moneyInCents, typedAmount };
