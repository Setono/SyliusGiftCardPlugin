<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * The plugin creates the payment method gift card payments are made with in no channel, and Sylius' form for it says
 * nothing about why, or about the code being how the plugin finds it. So the method's own create and edit pages explain
 * it, and no other payment method's pages say anything about gift cards (#411)
 */
final class GiftCardPaymentMethodMessageTest extends AdminFunctionalTestCase
{
    private const MESSAGE = '//*[@data-test-gift-card-payment-method-message]';

    private const CODE = self::MESSAGE . '//*[@data-test-gift-card-payment-method-code]';

    private const CHANNELS = '//form[@name="sylius_payment_method"]//input[@name="sylius_payment_method[channels][]"]';

    private PaymentMethodInterface $giftCardPaymentMethod;

    private PaymentMethodInterface $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInAsAdministrator();

        $this->giftCardPaymentMethod = $this->createGiftCardPaymentMethod();
        $this->createChannel('OTHER_CHANNEL');
        $this->cash = $this->createCashPaymentMethod();
        $this->manager->flush();
    }

    /** @test */
    public function the_edit_page_of_the_gift_card_payment_method_explains_it_above_the_form(): void
    {
        $page = $this->request('GET', sprintf('/admin/payment-methods/%d/edit', (int) $this->giftCardPaymentMethod->getId()));

        self::assertSame(200, $page->getStatusCode());

        $message = self::textsOf($page, self::MESSAGE);
        self::assertCount(1, $message);
        self::assertStringContainsString('This is the gift card payment method', $message[0]);
        // the code it has to keep, the channels making no difference, and why it is in none
        self::assertSame(['gift_card'], self::textsOf($page, self::CODE));
        self::assertStringContainsString('so the code has to stay gift_card.', $message[0]);
        self::assertStringContainsString('gift cards pay with it in every channel', $message[0]);
        self::assertStringContainsString('Sylius RefundPlugin', $message[0]);
        // and that it stays enabled, as disabling it stops gift cards being redeemed (#484)
        self::assertSame(
            ['Checkout never offers it to customers, so it does not need disabling to keep it out of checkout. Disabling it stops customers paying with gift cards altogether, until it is enabled again.'],
            self::textsOf($page, self::MESSAGE . '//*[@data-test-gift-card-payment-method-enabled]'),
        );
        self::assertCount(1, self::textsOf($page, self::MESSAGE . '/following::form[@name="sylius_payment_method"]'), 'the message should come before the form');

        // and the form shows the method in none of the shop's channels
        self::assertSame(['OTHER_CHANNEL', 'TEST_CHANNEL'], $this->sorted(self::textsOf($page, self::CHANNELS . '/@value')));
        self::assertSame([], self::textsOf($page, self::CHANNELS . '[@checked]/@value'));
    }

    /**
     * The message is about the method, not about its channels, so it stays on the page of a method an administrator
     * did put in a channel
     *
     * @test
     */
    public function the_message_stays_when_the_gift_card_payment_method_is_in_a_channel(): void
    {
        $this->giftCardPaymentMethod->addChannel($this->getChannel());
        $this->manager->flush();

        $page = $this->request('GET', sprintf('/admin/payment-methods/%d/edit', (int) $this->giftCardPaymentMethod->getId()));

        self::assertSame(200, $page->getStatusCode());
        self::assertCount(1, self::textsOf($page, self::MESSAGE));
        self::assertSame(['TEST_CHANNEL'], self::textsOf($page, self::CHANNELS . '[@checked]/@value'));
    }

    /** @test */
    public function the_edit_page_of_another_payment_method_says_nothing_about_gift_cards(): void
    {
        $page = $this->request('GET', sprintf('/admin/payment-methods/%d/edit', (int) $this->cash->getId()));

        self::assertSame(200, $page->getStatusCode());
        self::assertSame([], self::textsOf($page, self::MESSAGE));
    }

    /** @test */
    public function the_create_page_of_a_new_payment_method_says_nothing_about_gift_cards(): void
    {
        $page = $this->request('GET', '/admin/payment-methods/new/offline');

        self::assertSame(200, $page->getStatusCode());
        self::assertSame([], self::textsOf($page, self::MESSAGE));
    }

    /**
     * An administrator may create the method by hand, as an offline method with the configured code. The create page
     * explains it as soon as it shows a method with that code, as it does when it refuses the form: here because the
     * code is taken already
     *
     * @test
     */
    public function the_create_page_explains_a_payment_method_given_the_gift_card_code(): void
    {
        $page = $this->request('GET', '/admin/payment-methods/new/offline');
        self::assertSame(200, $page->getStatusCode());

        $refused = $this->request('POST', '/admin/payment-methods/new/offline', [
            'sylius_payment_method' => [
                'code' => 'gift_card',
                'position' => '',
                'enabled' => '1',
                'gatewayConfig' => ['factoryName' => 'offline'],
                'translations' => ['en_US' => ['name' => 'Gift card']],
                '_token' => self::valueOf($page, '//input[@name="sylius_payment_method[_token]"]'),
            ],
        ]);

        self::assertSame(422, $refused->getStatusCode(), 'the code is taken, so the form should be refused');
        self::assertSame(['gift_card'], self::textsOf($refused, self::CODE));
        self::assertSame(
            ['The payment method with given code already exists.'],
            self::textsOf($refused, '//*[contains(concat(" ", normalize-space(@class), " "), " sylius-validation-error ")]'),
        );
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
