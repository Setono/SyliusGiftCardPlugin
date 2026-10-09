const { test, expect } = require('@playwright/test');
const { blankIcons } = require('../support/icons');
const { otherPaymentMethodEditUrl, paymentMethodEditUrl } = require('../support/payment-methods');

/**
 * The plugin creates the payment method gift card payments are made with in no channel, and Sylius' form for it says
 * nothing about why, or about the code being how the plugin finds it. So its edit page explains it above the form, and
 * no other payment method's page says anything about gift cards (#411).
 *
 * The method is found by the code the plugin gives it (the redemption.payment_method_code setting, which the test
 * application leaves at gift_card), the other method as the first one the grid lists besides it
 */

const CODE = 'gift_card';
const FORM = 'form[name="sylius_payment_method"]';
const MESSAGE = '[data-test-gift-card-payment-method-message]';
const MESSAGE_CODE = `${MESSAGE} [data-test-gift-card-payment-method-code]`;
const CHANNELS = `${FORM} input[name="sylius_payment_method[channels][]"]`;

test.describe('gift card payment method', () => {
    test('its edit page explains it above the form', async ({ page }) => {
        await page.goto(await paymentMethodEditUrl(page, CODE));

        const message = page.locator(MESSAGE);
        await expect(message).toBeVisible();
        expect(await blankIcons(message), 'the icons of the message that draw nothing').toEqual([]);

        // the code it has to keep, and the plugin it keeps the method out of the refund destinations of
        await expect(page.locator(MESSAGE_CODE)).toHaveText(CODE);
        await expect(message).toContainText('RefundPlugin');
        // and that it is to stay enabled, as disabling it stops gift cards being redeemed (#484)
        await expect(message.locator('[data-test-gift-card-payment-method-enabled]')).toBeVisible();

        // above Sylius' form, whose code field shows the same code
        await expect(
            page.locator('xpath=//*[@data-test-gift-card-payment-method-message]/following::form[@name="sylius_payment_method"]'),
            'the message should come before the form',
        ).toHaveCount(1);
        await expect(page.locator(`${FORM} input[name="sylius_payment_method[code]"]`)).toHaveValue(CODE);

        // the seeded shop has the method from the plugin's fixture, which puts it in none of the shop's channels
        expect(await page.locator(CHANNELS).count(), 'the form should offer the shop\'s channels').toBeGreaterThan(0);
        await expect(page.locator(`${CHANNELS}:checked`)).toHaveCount(0);
    });

    test('another payment method\'s edit page says nothing about gift cards', async ({ page }) => {
        await page.goto(await otherPaymentMethodEditUrl(page, CODE));

        await expect(page.locator(FORM)).toBeVisible();
        await expect(page.locator(`${FORM} input[name="sylius_payment_method[code]"]`)).not.toHaveValue(CODE);
        await expect(page.locator(MESSAGE)).toHaveCount(0);
    });
});
