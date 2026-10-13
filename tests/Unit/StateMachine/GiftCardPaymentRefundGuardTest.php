<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\StateMachine;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Setono\SyliusGiftCardPlugin\StateMachine\GiftCardPaymentRefundGuard;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;

/**
 * Sylius' order payment graph leads to no refund from an order that awaits payment, is partially authorized or is
 * authorized, so refunding a gift card payment on its own there would leave the order unpaid, or authorized, for good
 * (#507). Cancelling the order refunds it once the order is cancelled, which the guard lets through, and so is every
 * refund of a paid order and of any other payment
 */
final class GiftCardPaymentRefundGuardTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     *
     * @dataProvider refunds
     */
    public function it_refuses_a_gift_card_payment_of_an_order_that_is_not_paid_yet(bool $giftCardPayment, ?string $orderState, string $orderPaymentState, bool $refundable): void
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getState()->willReturn($orderState);
        $order->getPaymentState()->willReturn($orderPaymentState);

        $payment = $this->prophesize(PaymentInterface::class);
        $payment->getOrder()->willReturn(null === $orderState ? null : $order->reveal());

        $paymentChecker = $this->prophesize(GiftCardPaymentCheckerInterface::class);
        $paymentChecker->isGiftCardPayment($payment->reveal())->willReturn($giftCardPayment);

        self::assertSame($refundable, (new GiftCardPaymentRefundGuard($paymentChecker->reveal()))->isSatisfiedBy($payment->reveal()));
    }

    /**
     * @return iterable<string, array{bool, string|null, string, bool}>
     */
    public static function refunds(): iterable
    {
        yield 'a gift card payment of an order awaiting payment' => [true, OrderInterface::STATE_NEW, OrderPaymentStates::STATE_AWAITING_PAYMENT, false];
        yield 'a gift card payment of a partially authorized order' => [true, OrderInterface::STATE_NEW, OrderPaymentStates::STATE_PARTIALLY_AUTHORIZED, false];
        yield 'a gift card payment of an authorized order' => [true, OrderInterface::STATE_NEW, OrderPaymentStates::STATE_AUTHORIZED, false];
        yield 'a gift card payment of an order being cancelled' => [true, OrderInterface::STATE_CANCELLED, OrderPaymentStates::STATE_AUTHORIZED, true];
        yield 'a gift card payment of a paid order' => [true, OrderInterface::STATE_NEW, OrderPaymentStates::STATE_PAID, true];
        yield 'a gift card payment of a partially paid order' => [true, OrderInterface::STATE_NEW, OrderPaymentStates::STATE_PARTIALLY_PAID, true];
        yield 'a gift card payment of a partially refunded order' => [true, OrderInterface::STATE_NEW, OrderPaymentStates::STATE_PARTIALLY_REFUNDED, true];
        yield 'a gift card payment without an order' => [true, null, OrderPaymentStates::STATE_AWAITING_PAYMENT, true];
        yield 'another payment of an authorized order' => [false, OrderInterface::STATE_NEW, OrderPaymentStates::STATE_AUTHORIZED, true];
    }
}
