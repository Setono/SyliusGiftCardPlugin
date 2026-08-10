<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Resolver\GiftCardAwareDefaultPaymentMethodResolver;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Exception\UnresolvedDefaultPaymentMethodException;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Resolver\DefaultPaymentMethodResolverInterface;

/**
 * The gift card payment method is a real, enabled payment method in the "payment" redemption mode, so the
 * decorated resolver can hand it back as the default for a gateway payment. This resolver has to keep that
 * from happening without breaking checkout for the channel
 */
final class GiftCardAwareDefaultPaymentMethodResolverTest extends TestCase
{
    use ProphecyTrait;

    private const GIFT_CARD_CODE = 'gift_card';

    /** @test */
    public function it_returns_the_decorated_method_when_it_is_not_the_gift_card_method(): void
    {
        $method = $this->paymentMethod('offline');

        $resolver = new GiftCardAwareDefaultPaymentMethodResolver(
            $this->decoratedReturning($method),
            $this->repositoryReturning([]),
            self::GIFT_CARD_CODE,
        );

        self::assertSame($method, $resolver->getDefaultPaymentMethod($this->payment()->reveal()));
    }

    /** @test */
    public function it_falls_back_to_the_first_enabled_non_gift_card_method(): void
    {
        $giftCardMethod = $this->paymentMethod(self::GIFT_CARD_CODE);
        $fallback = $this->paymentMethod('offline');

        $resolver = new GiftCardAwareDefaultPaymentMethodResolver(
            $this->decoratedReturning($giftCardMethod),
            // the gift card method is listed first on purpose: it has to be skipped, not merely deduplicated
            $this->repositoryReturning([$giftCardMethod, $fallback]),
            self::GIFT_CARD_CODE,
        );

        self::assertSame($fallback, $resolver->getDefaultPaymentMethod($this->payment()->reveal()));
    }

    /** @test */
    public function it_throws_when_the_gift_card_method_is_the_only_enabled_one(): void
    {
        $giftCardMethod = $this->paymentMethod(self::GIFT_CARD_CODE);

        $resolver = new GiftCardAwareDefaultPaymentMethodResolver(
            $this->decoratedReturning($giftCardMethod),
            $this->repositoryReturning([$giftCardMethod]),
            self::GIFT_CARD_CODE,
        );

        $this->expectException(UnresolvedDefaultPaymentMethodException::class);

        $resolver->getDefaultPaymentMethod($this->payment()->reveal());
    }

    /**
     * Without an order there is no channel to look up candidates for, so there is nothing to fall back to
     *
     * @test
     */
    public function it_throws_when_the_payment_has_no_order(): void
    {
        $giftCardMethod = $this->paymentMethod(self::GIFT_CARD_CODE);

        $payment = $this->prophesize(PaymentInterface::class);
        $payment->getOrder()->willReturn(null);

        $resolver = new GiftCardAwareDefaultPaymentMethodResolver(
            $this->decoratedReturning($giftCardMethod),
            $this->repositoryReturning([]),
            self::GIFT_CARD_CODE,
        );

        $this->expectException(UnresolvedDefaultPaymentMethodException::class);

        $resolver->getDefaultPaymentMethod($payment->reveal());
    }

    private function paymentMethod(string $code): PaymentMethodInterface
    {
        $method = $this->prophesize(PaymentMethodInterface::class);
        $method->getCode()->willReturn($code);

        return $method->reveal();
    }

    /**
     * @return ObjectProphecy<PaymentInterface>
     */
    private function payment(): ObjectProphecy
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getChannel()->willReturn($this->prophesize(ChannelInterface::class)->reveal());

        $payment = $this->prophesize(PaymentInterface::class);
        $payment->getOrder()->willReturn($order->reveal());

        return $payment;
    }

    private function decoratedReturning(PaymentMethodInterface $method): DefaultPaymentMethodResolverInterface
    {
        $decorated = $this->prophesize(DefaultPaymentMethodResolverInterface::class);
        $decorated->getDefaultPaymentMethod(Argument::type(BasePaymentInterface::class))->willReturn($method);

        return $decorated->reveal();
    }

    /**
     * @param list<PaymentMethodInterface> $methods
     *
     * @return PaymentMethodRepositoryInterface<PaymentMethodInterface>
     */
    private function repositoryReturning(array $methods): PaymentMethodRepositoryInterface
    {
        $repository = $this->prophesize(PaymentMethodRepositoryInterface::class);
        $repository->findEnabledForChannel(Argument::type(ChannelInterface::class))->willReturn($methods);

        return $repository->reveal();
    }
}
