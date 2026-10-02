<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\StateResolver;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardPaidAmountCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Setono\SyliusGiftCardPlugin\StateResolver\GiftCardAwareOrderPaymentStateResolver;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;

/**
 * The resolver only steps in while the gift cards have paid part of the order and nothing else has: every other
 * order is Sylius' to resolve, so the decorated resolver must see it unchanged. What the gift cards have paid is the
 * paid amount calculator's answer (the completed gift card payments), which GiftCardPaidAmountCalculatorTest covers
 */
final class GiftCardAwareOrderPaymentStateResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<StateResolverInterface> */
    private ObjectProphecy $decorated;

    /** @var ObjectProphecy<GiftCardPaidAmountCalculatorInterface> */
    private ObjectProphecy $paidAmountCalculator;

    /** @var ObjectProphecy<GiftCardPaymentCheckerInterface> */
    private ObjectProphecy $paymentChecker;

    protected function setUp(): void
    {
        $this->decorated = $this->prophesize(StateResolverInterface::class);
        $this->paidAmountCalculator = $this->prophesize(GiftCardPaidAmountCalculatorInterface::class);
        $this->paymentChecker = $this->prophesize(GiftCardPaymentCheckerInterface::class);
    }

    /**
     * @test
     *
     * @dataProvider statesOfAPaymentThatHasNotGoneThrough
     */
    public function it_leaves_the_order_awaiting_payment_while_the_gift_cards_have_paid_only_part_of_it(string $state): void
    {
        $order = $this->order(10000, 6000, [
            $this->giftCardPayment(PaymentInterface::STATE_COMPLETED),
            $this->gatewayPayment($state),
        ]);

        $this->resolver()->resolve($order->reveal());

        $this->decorated->resolve($order->reveal())->shouldNotHaveBeenCalled();
    }

    /**
     * What the rest looks like while nothing has paid for it: waiting to be paid, waiting for the payment provider to
     * confirm it, or failed or cancelled (Sylius then adds a new payment next to it)
     *
     * @return iterable<string, array{string}>
     */
    public function statesOfAPaymentThatHasNotGoneThrough(): iterable
    {
        yield 'cart' => [PaymentInterface::STATE_CART];
        yield 'new' => [PaymentInterface::STATE_NEW];
        yield 'processing' => [PaymentInterface::STATE_PROCESSING];
        yield 'failed' => [PaymentInterface::STATE_FAILED];
        yield 'cancelled' => [PaymentInterface::STATE_CANCELLED];
    }

    /**
     * With nothing left to pay, Sylius pays the order
     *
     * @test
     */
    public function it_lets_sylius_resolve_an_order_the_gift_cards_cover_in_full(): void
    {
        $order = $this->order(10000, 10000, [
            $this->giftCardPayment(PaymentInterface::STATE_COMPLETED),
            $this->giftCardPayment(PaymentInterface::STATE_COMPLETED),
        ]);

        $this->resolver()->resolve($order->reveal());

        $this->decorated->resolve($order->reveal())->shouldHaveBeenCalledOnce();
    }

    /**
     * Once money of the customer's own has moved, what that means for the order (paid, partially paid, authorized,
     * refunded) is Sylius' call, just as it is for an order without a gift card
     *
     * @test
     *
     * @dataProvider statesOfAPaymentThatHasGoneThrough
     */
    public function it_lets_sylius_resolve_the_order_once_a_payment_of_the_customer_has_gone_through(string $state): void
    {
        $order = $this->order(10000, 6000, [
            $this->giftCardPayment(PaymentInterface::STATE_COMPLETED),
            $this->gatewayPayment($state),
        ]);

        $this->resolver()->resolve($order->reveal());

        $this->decorated->resolve($order->reveal())->shouldHaveBeenCalledOnce();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public function statesOfAPaymentThatHasGoneThrough(): iterable
    {
        yield 'completed' => [PaymentInterface::STATE_COMPLETED];
        yield 'authorized' => [PaymentInterface::STATE_AUTHORIZED];
        yield 'refunded' => [PaymentInterface::STATE_REFUNDED];
    }

    /**
     * An order no gift card paid for, or whose gift card payments have been refunded (cancelling the order refunds
     * them), has nothing the gift cards paid to keep the order from
     *
     * @test
     */
    public function it_lets_sylius_resolve_an_order_the_gift_cards_have_paid_nothing_on(): void
    {
        $order = $this->order(10000, 0, [
            $this->giftCardPayment(PaymentInterface::STATE_REFUNDED),
            $this->gatewayPayment(PaymentInterface::STATE_NEW),
        ]);

        $this->resolver()->resolve($order->reveal());

        $this->decorated->resolve($order->reveal())->shouldHaveBeenCalledOnce();
    }

    /** @test */
    public function it_lets_sylius_resolve_an_order_that_cannot_carry_payments(): void
    {
        $order = $this->prophesize(BaseOrderInterface::class);

        $this->resolver()->resolve($order->reveal());

        $this->decorated->resolve($order->reveal())->shouldHaveBeenCalledOnce();
    }

    private function resolver(): GiftCardAwareOrderPaymentStateResolver
    {
        return new GiftCardAwareOrderPaymentStateResolver(
            $this->decorated->reveal(),
            $this->paidAmountCalculator->reveal(),
            $this->paymentChecker->reveal(),
        );
    }

    /**
     * @return ObjectProphecy<PaymentInterface>
     */
    private function gatewayPayment(string $state): ObjectProphecy
    {
        return $this->payment($state, false);
    }

    /**
     * @return ObjectProphecy<PaymentInterface>
     */
    private function giftCardPayment(string $state): ObjectProphecy
    {
        return $this->payment($state, true);
    }

    /**
     * @return ObjectProphecy<PaymentInterface>
     */
    private function payment(string $state, bool $giftCard): ObjectProphecy
    {
        $payment = $this->prophesize(PaymentInterface::class);
        $payment->getState()->willReturn($state);

        $this->paymentChecker->isGiftCardPayment($payment->reveal())->willReturn($giftCard);

        return $payment;
    }

    /**
     * @param int $paidByGiftCards what the paid amount calculator says the gift cards have paid on the order
     * @param list<ObjectProphecy<PaymentInterface>> $payments
     *
     * @return ObjectProphecy<OrderInterface>
     */
    private function order(int $total, int $paidByGiftCards, array $payments): ObjectProphecy
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getTotal()->willReturn($total);
        $order->getPayments()->willReturn(new ArrayCollection(array_map(
            static fn (ObjectProphecy $payment): PaymentInterface => $payment->reveal(),
            $payments,
        )));

        $this->paidAmountCalculator->getPaidAmount($order->reveal())->willReturn($paidByGiftCards);

        return $order;
    }
}
