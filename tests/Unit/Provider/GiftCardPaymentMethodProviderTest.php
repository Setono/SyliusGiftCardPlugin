<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardPaymentMethodDisabledException;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardPaymentMethodNotFoundException;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardPaymentMethodProvider;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;

/**
 * The provider only finds the payment method gift card payments are made with. It used to create it when it was
 * missing, flushing whatever else was pending in the middle of placing an order
 */
final class GiftCardPaymentMethodProviderTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_finds_the_payment_method_with_the_configured_code(): void
    {
        $paymentMethod = new PaymentMethod();

        $repository = $this->prophesize(PaymentMethodRepositoryInterface::class);
        $repository->findOneBy(['code' => 'gift_card'])->willReturn($paymentMethod);

        $provider = new GiftCardPaymentMethodProvider($repository->reveal(), 'gift_card');

        self::assertSame($paymentMethod, $provider->findPaymentMethod());
        self::assertSame($paymentMethod, $provider->findEnabledPaymentMethod());
        self::assertSame($paymentMethod, $provider->getEnabledPaymentMethod());
    }

    /** @test */
    public function it_finds_nothing_while_the_shop_has_not_set_the_payment_method_up(): void
    {
        $repository = $this->prophesize(PaymentMethodRepositoryInterface::class);
        $repository->findOneBy(['code' => 'gift_card'])->willReturn(null);
        $repository->add(Argument::any())->shouldNotBeCalled();

        $provider = new GiftCardPaymentMethodProvider($repository->reveal(), 'gift_card');

        self::assertNull($provider->findPaymentMethod());
        self::assertNull($provider->findEnabledPaymentMethod());

        try {
            $provider->getEnabledPaymentMethod();
            self::fail('getting a payment method that does not exist should have thrown');
        } catch (GiftCardPaymentMethodNotFoundException $e) {
            self::assertSame('gift_card', $e->getPaymentMethodCode());
            self::assertStringContainsString('setono:gift-card:create-payment-method', $e->getMessage());
        }
    }

    /**
     * The Enabled switch means whether customers can pay with the method, as for any payment method, so a disabled
     * one is found for the setup steps, which must not create a second one, but no gift card payment is made with it
     *
     * @test
     */
    public function it_finds_a_disabled_payment_method_but_makes_no_gift_card_payment_with_it(): void
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->disable();

        $repository = $this->prophesize(PaymentMethodRepositoryInterface::class);
        $repository->findOneBy(['code' => 'gift_card'])->willReturn($paymentMethod);

        $provider = new GiftCardPaymentMethodProvider($repository->reveal(), 'gift_card');

        self::assertSame($paymentMethod, $provider->findPaymentMethod());
        self::assertNull($provider->findEnabledPaymentMethod());

        try {
            $provider->getEnabledPaymentMethod();
            self::fail('getting a disabled payment method to pay with should have thrown');
        } catch (GiftCardPaymentMethodDisabledException $e) {
            self::assertSame('gift_card', $e->getPaymentMethodCode());
            self::assertStringContainsString('"gift_card" is disabled', $e->getMessage());
        }
    }
}
