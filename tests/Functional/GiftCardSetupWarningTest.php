<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * The warning about a channel selling gift cards without a design is asked for several times per admin page, so the
 * answer is kept for the request. The runtime keeping it is a shared service, and under a worker runtime it outlives
 * the request, so the kernel has to reset it: once the merchant has fixed the setup, the next page must stop warning.
 * The warnings about the missing and the disabled gift card payment method work the same way
 */
final class GiftCardSetupWarningTest extends AdminFunctionalTestCase
{
    private const TOP_BAR = '//a[@data-test-gift-card-setup-warning]';

    private const MESSAGE = '//*[@data-test-gift-card-setup-warning-message]';

    private const PAYMENT_METHOD_TOP_BAR = '//a[@data-test-gift-card-payment-method-warning]';

    private const PAYMENT_METHOD_MESSAGE = '//*[@data-test-gift-card-payment-method-warning-message]';

    private const DISABLED_PAYMENT_METHOD_TOP_BAR = '//a[@data-test-gift-card-payment-method-disabled-warning]';

    private const DISABLED_PAYMENT_METHOD_MESSAGE = '//*[@data-test-gift-card-payment-method-disabled-warning-message]';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInAsAdministrator();

        /** @var GiftCardProductFactoryInterface $productFactory */
        $productFactory = self::getContainer()->get(GiftCardProductFactoryInterface::class);
        $this->manager->persist($productFactory->create('gift_card', 'Gift card', channels: [$this->getChannel()]));
        $this->manager->flush();
    }

    /** @test */
    public function it_names_the_channel_selling_gift_cards_without_a_design(): void
    {
        $response = $this->request('GET', '/admin/gift-cards/');

        self::assertSame(200, $response->getStatusCode());
        // The top bar of every admin page links to where designs are managed
        self::assertSame(['/admin/gift-card-designs/'], self::textsOf($response, self::TOP_BAR . '/@href'));
        self::assertStringContainsString('Test channel', implode(' ', self::textsOf($response, self::TOP_BAR)));
        // and the index that can fix it explains
        self::assertStringContainsString('Test channel', implode(' ', self::textsOf($response, self::MESSAGE)));
    }

    /** @test */
    public function it_stops_warning_on_the_next_page_once_a_design_is_there(): void
    {
        self::assertCount(1, self::textsOf($this->request('GET', '/admin/gift-cards/'), self::TOP_BAR));

        $this->persistDesign();

        $response = $this->request('GET', '/admin/gift-cards/');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], self::textsOf($response, self::TOP_BAR));
        self::assertSame([], self::textsOf($response, self::MESSAGE));
    }

    /**
     * Without the payment method gift card payments are made with, the shop refuses every gift card, so every admin
     * page says so, and the pages that can fix it explain how: the gift card index, and Sylius' payment methods
     *
     * @test
     */
    public function it_warns_on_every_admin_page_while_the_gift_card_payment_method_is_missing(): void
    {
        $dashboard = $this->request('GET', '/admin/');

        self::assertSame(200, $dashboard->getStatusCode());
        self::assertSame(['/admin/gift-cards/'], self::textsOf($dashboard, self::PAYMENT_METHOD_TOP_BAR . '/@href'));
        self::assertSame([], self::textsOf($dashboard, self::PAYMENT_METHOD_MESSAGE), 'the dashboard only carries the top bar label');

        foreach (['/admin/gift-cards/', '/admin/gift-card-designs/', '/admin/payment-methods/'] as $path) {
            $response = $this->request('GET', $path);

            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertCount(1, self::textsOf($response, self::PAYMENT_METHOD_TOP_BAR), $path);

            $message = implode(' ', self::textsOf($response, self::PAYMENT_METHOD_MESSAGE));
            // the code the method gets, and both ways to create it: the button, and the command for deploy scripts
            self::assertStringContainsString('gift_card', $message, $path);
            self::assertStringContainsString('setono:gift-card:create-payment-method', $message, $path);
            self::assertSame(
                ['/admin/gift-cards/create-payment-method'],
                self::textsOf($response, self::PAYMENT_METHOD_MESSAGE . '//form/@action'),
                $path,
            );
        }
    }

    /** @test */
    public function it_stops_warning_about_the_payment_method_on_the_next_page_once_it_exists(): void
    {
        self::assertCount(1, self::textsOf($this->request('GET', '/admin/gift-cards/'), self::PAYMENT_METHOD_TOP_BAR));

        $this->createGiftCardPaymentMethod();

        $response = $this->request('GET', '/admin/gift-cards/');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], self::textsOf($response, self::PAYMENT_METHOD_TOP_BAR));
        self::assertSame([], self::textsOf($response, self::PAYMENT_METHOD_MESSAGE));
    }

    /**
     * A disabled gift card payment method refuses every gift card just as a missing one does (#484). The merchant may
     * have disabled it on purpose, but the shop goes on selling cards nobody can spend, so every admin page says so, and
     * the label and the pages that explain it lead to the method's edit page, where it is enabled again. There is
     * nothing to create, so the warning about the missing method, with its button, stays away
     *
     * @test
     */
    public function it_warns_on_every_admin_page_while_the_gift_card_payment_method_is_disabled(): void
    {
        $paymentMethod = $this->createGiftCardPaymentMethod();
        $paymentMethod->disable();
        $this->manager->flush();

        $editPage = sprintf('/admin/payment-methods/%d/edit', (int) $paymentMethod->getId());

        $dashboard = $this->request('GET', '/admin/');

        self::assertSame(200, $dashboard->getStatusCode());
        self::assertSame([$editPage], self::textsOf($dashboard, self::DISABLED_PAYMENT_METHOD_TOP_BAR . '/@href'));
        self::assertStringContainsString('the gift card payment method is disabled', implode(' ', self::textsOf($dashboard, self::DISABLED_PAYMENT_METHOD_TOP_BAR)));
        self::assertSame([], self::textsOf($dashboard, self::DISABLED_PAYMENT_METHOD_MESSAGE), 'the dashboard only carries the top bar label');
        self::assertSame([], self::textsOf($dashboard, self::PAYMENT_METHOD_TOP_BAR), 'the method is not missing');

        foreach (['/admin/gift-cards/', '/admin/gift-card-designs/', '/admin/payment-methods/'] as $path) {
            $response = $this->request('GET', $path);

            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertCount(1, self::textsOf($response, self::DISABLED_PAYMENT_METHOD_TOP_BAR), $path);

            $message = implode(' ', self::textsOf($response, self::DISABLED_PAYMENT_METHOD_MESSAGE));
            self::assertStringContainsString('The gift card payment method is disabled', $message, $path);
            self::assertStringContainsString('Gift cards are still for sale', $message, $path);
            self::assertSame([$editPage], self::textsOf($response, self::DISABLED_PAYMENT_METHOD_MESSAGE . '//a/@href'), $path);

            self::assertSame([], self::textsOf($response, self::PAYMENT_METHOD_MESSAGE), $path);
            self::assertSame([], self::textsOf($response, '//form[@action="/admin/gift-cards/create-payment-method"]'), $path);
        }
    }

    /** @test */
    public function it_stops_warning_about_the_disabled_payment_method_on_the_next_page_once_it_is_enabled(): void
    {
        $paymentMethod = $this->createGiftCardPaymentMethod();
        $paymentMethod->disable();
        $this->manager->flush();

        self::assertCount(1, self::textsOf($this->request('GET', '/admin/gift-cards/'), self::DISABLED_PAYMENT_METHOD_TOP_BAR));

        $paymentMethod->enable();
        $this->manager->flush();

        $response = $this->request('GET', '/admin/gift-cards/');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], self::textsOf($response, self::DISABLED_PAYMENT_METHOD_TOP_BAR));
        self::assertSame([], self::textsOf($response, self::DISABLED_PAYMENT_METHOD_MESSAGE));
    }

    private function persistDesign(): void
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $factory->createNew();
        $design->setCode('classic');
        $design->setCurrentLocale('en_US');
        $design->setFallbackLocale('en_US');
        $design->setName('Classic');
        $design->setPosition(0);
        $design->setEnabled(true);
        $design->addChannel($this->getChannel());

        $this->manager->persist($design);
        $this->manager->flush();
    }
}
