<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Payum\Core\Model\GatewayConfigInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactoryInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * While the shop sells gift cards without the payment method gift card payments are made with, the setup warning
 * offers a button that creates it. This presses it the way the admin does: with the CSRF token the warning renders,
 * into the real payment method factory and database
 */
final class CreateGiftCardPaymentMethodActionTest extends AdminFunctionalTestCase
{
    private const MESSAGE = '//*[@data-test-gift-card-payment-method-warning-message]';

    private const TOP_BAR = '//a[@data-test-gift-card-payment-method-warning]';

    private const BUTTON = self::MESSAGE . '//form[@action="/admin/gift-cards/create-payment-method"]//button[@type="submit"]';

    private const TOKEN = self::MESSAGE . '//form[@action="/admin/gift-cards/create-payment-method"]//input[@name="_csrf_token"]';

    private const FLASH = '//*[contains(concat(" ", normalize-space(@class), " "), " sylius-flash-message ")]';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInAsAdministrator();

        // The shop sells gift cards, so a missing payment method is something to warn about
        /** @var GiftCardProductFactoryInterface $productFactory */
        $productFactory = self::getContainer()->get(GiftCardProductFactoryInterface::class);
        $this->manager->persist($productFactory->create('gift_card', 'Gift card', channels: [$this->getChannel()]));
        $this->manager->flush();
    }

    /**
     * The button is part of the warning, so it is offered wherever the warning explains how to fix the setup
     *
     * @test
     */
    public function the_warning_about_the_missing_payment_method_offers_a_button_that_creates_it(): void
    {
        foreach (['/admin/gift-cards/', '/admin/gift-card-designs/', '/admin/payment-methods/'] as $path) {
            $response = $this->request('GET', $path);

            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertCount(1, self::textsOf($response, self::BUTTON), $path);
        }
    }

    /**
     * Gift card payments are made with the method in every channel, whichever channels it is in, so it is created in
     * none of the shop's channels (#411)
     *
     * @test
     */
    public function pressing_the_button_creates_the_payment_method_in_no_channel(): void
    {
        $this->createChannel('OTHER_CHANNEL');
        $this->manager->flush();

        $response = $this->pressCreatePaymentMethod();

        self::assertTrue($response->isRedirect('/admin/gift-cards/'), sprintf('Expected a redirect to the gift card index, got a %d response', $response->getStatusCode()));

        $paymentMethods = $this->findGiftCardPaymentMethods();
        self::assertCount(1, $paymentMethods);

        $paymentMethod = $paymentMethods[0];
        self::assertTrue($paymentMethod->isEnabled());
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        self::assertInstanceOf(GatewayConfigInterface::class, $gatewayConfig);
        self::assertSame('offline', $gatewayConfig->getFactoryName());

        self::assertCount(0, $paymentMethod->getChannels());

        // The admin lands where the warning was, and it is gone
        $index = $this->followRedirect($response);
        self::assertStringContainsString('The gift card payment method was created', implode(' ', self::textsOf($index, self::FLASH)));
        self::assertSame([], self::textsOf($index, self::TOP_BAR));
        self::assertSame([], self::textsOf($index, self::MESSAGE));
        self::assertSame([], self::textsOf($index, self::BUTTON));
    }

    /**
     * Another tab, another admin or a deploy running setono:gift-card:create-payment-method may have created the
     * method since the page with the button was rendered. Pressing it then must not create a second one
     *
     * @test
     */
    public function pressing_the_button_once_the_method_exists_creates_nothing_and_says_so(): void
    {
        $token = self::valueOf($this->request('GET', '/admin/gift-cards/'), self::TOKEN);

        $this->createGiftCardPaymentMethod();

        $response = $this->request('POST', '/admin/gift-cards/create-payment-method', ['_csrf_token' => $token]);

        self::assertTrue($response->isRedirect('/admin/gift-cards/'), sprintf('Expected a redirect to the gift card index, got a %d response', $response->getStatusCode()));
        self::assertCount(1, $this->findGiftCardPaymentMethods());

        $index = $this->followRedirect($response);
        self::assertStringContainsString('already exists', implode(' ', self::textsOf($index, self::FLASH)));
    }

    /**
     * @test
     *
     * @dataProvider invalidTokens
     */
    public function it_creates_nothing_without_the_token_the_warning_renders(?string $token): void
    {
        $response = $this->request('POST', '/admin/gift-cards/create-payment-method', null === $token ? [] : ['_csrf_token' => $token]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame([], $this->findGiftCardPaymentMethods());
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public function invalidTokens(): iterable
    {
        yield 'no token' => [null];
        yield 'a forged token' => ['forged'];
    }

    /** @test */
    public function the_button_is_not_offered_once_the_payment_method_exists(): void
    {
        $this->createGiftCardPaymentMethod();

        $response = $this->request('GET', '/admin/gift-cards/');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], self::textsOf($response, self::BUTTON));
    }

    private function pressCreatePaymentMethod(): Response
    {
        $token = self::valueOf($this->request('GET', '/admin/gift-cards/'), self::TOKEN);

        return $this->request('POST', '/admin/gift-cards/create-payment-method', ['_csrf_token' => $token]);
    }

    /**
     * @return list<PaymentMethodInterface>
     */
    private function findGiftCardPaymentMethods(): array
    {
        $this->manager->clear();

        /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.payment_method');

        /** @var list<PaymentMethodInterface> $paymentMethods */
        $paymentMethods = $repository->findBy(['code' => 'gift_card']);

        return $paymentMethods;
    }
}
