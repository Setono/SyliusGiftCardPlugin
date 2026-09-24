<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Resolver\GiftCardAwarePaymentMethodsResolver;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

/**
 * The gift card payment method is an ordinary enabled payment method in the channel, so Sylius offers it at the
 * payment step like any other. Picking it would settle the rest of the order with no gift card behind it, so it
 * has to be taken out of the choices
 */
final class GiftCardAwarePaymentMethodsResolverTest extends TestCase
{
    use ProphecyTrait;

    private const GIFT_CARD_CODE = 'gift_card';

    /** @test */
    public function it_takes_the_gift_card_payment_method_out_of_the_choices(): void
    {
        $cash = $this->paymentMethod('cash_on_delivery');
        $bank = $this->paymentMethod('bank_transfer');
        $payment = $this->prophesize(PaymentInterface::class)->reveal();

        $resolver = $this->resolver($payment, [$cash, $this->paymentMethod(self::GIFT_CARD_CODE), $bank]);

        // a list, so a choice type iterating it by index does not trip over the gap the gift card method left
        self::assertSame([$cash, $bank], $resolver->getSupportedMethods($payment));
    }

    /** @test */
    public function it_offers_every_other_payment_method(): void
    {
        $cash = $this->paymentMethod('cash_on_delivery');
        $bank = $this->paymentMethod('bank_transfer');
        $payment = $this->prophesize(PaymentInterface::class)->reveal();

        self::assertSame([$cash, $bank], $this->resolver($payment, [$cash, $bank])->getSupportedMethods($payment));
    }

    /**
     * @test
     *
     * @dataProvider supportAnswers
     */
    public function it_supports_the_payments_the_decorated_resolver_supports(bool $supports): void
    {
        $payment = $this->prophesize(PaymentInterface::class)->reveal();

        $decorated = $this->prophesize(PaymentMethodsResolverInterface::class);
        $decorated->supports($payment)->willReturn($supports);

        $resolver = new GiftCardAwarePaymentMethodsResolver($decorated->reveal(), self::GIFT_CARD_CODE);

        self::assertSame($supports, $resolver->supports($payment));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function supportAnswers(): iterable
    {
        yield 'supported' => [true];
        yield 'not supported' => [false];
    }

    /**
     * @param list<PaymentMethodInterface> $methods
     */
    private function resolver(PaymentInterface $payment, array $methods): GiftCardAwarePaymentMethodsResolver
    {
        $decorated = $this->prophesize(PaymentMethodsResolverInterface::class);
        $decorated->getSupportedMethods($payment)->willReturn($methods);

        return new GiftCardAwarePaymentMethodsResolver($decorated->reveal(), self::GIFT_CARD_CODE);
    }

    private function paymentMethod(string $code): PaymentMethodInterface
    {
        $method = new PaymentMethod();
        $method->setCode($code);

        return $method;
    }
}
