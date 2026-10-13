<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\StateResolver;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardPaidAmountCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Setono\SyliusGiftCardPlugin\StateResolver\GiftCardAwareOrderPaymentStateResolver;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;

/**
 * The resolver only steps in while the gift cards have paid part of the order and nothing else has, or nothing but an
 * authorization of the rest, which it authorizes the order for: every other order is Sylius' to resolve, so the decorated resolver must see it unchanged. What the gift cards have paid is the
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

    /** @var ObjectProphecy<StateMachineInterface> */
    private ObjectProphecy $stateMachine;

    protected function setUp(): void
    {
        $this->decorated = $this->prophesize(StateResolverInterface::class);
        $this->paidAmountCalculator = $this->prophesize(GiftCardPaidAmountCalculatorInterface::class);
        $this->paymentChecker = $this->prophesize(GiftCardPaymentCheckerInterface::class);
        $this->stateMachine = $this->prophesize(StateMachineInterface::class);
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
     * Once money of the customer's own has moved, what that means for the order (paid, partially paid, refunded) is
     * Sylius' call, just as it is for an order without a gift card. An authorization is the exception (see below)
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

    /**
     * A payment provider that captures later authorizes the rest. The gift cards and the authorization then secure the
     * whole order, which Sylius, weighing the completed gift card payments first, would call partially paid (#499)
     *
     * @test
     *
     * @dataProvider authorizationsOfTheRest
     *
     * @param list<int> $authorizations the amounts of the authorized payments
     */
    public function it_authorizes_an_order_whose_rest_is_authorized(array $authorizations): void
    {
        $order = $this->order(10000, 6000, [
            $this->giftCardPayment(PaymentInterface::STATE_COMPLETED, 6000),
            $this->gatewayPayment(PaymentInterface::STATE_CANCELLED, 4000),
            ...array_map(fn (int $amount): ObjectProphecy => $this->gatewayPayment(PaymentInterface::STATE_AUTHORIZED, $amount), $authorizations),
        ]);
        $this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_AUTHORIZE)->willReturn(true);

        $this->resolver()->resolve($order->reveal());

        $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_AUTHORIZE)->shouldHaveBeenCalledOnce();
        $this->decorated->resolve(Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * @return iterable<string, array{list<int>}>
     */
    public function authorizationsOfTheRest(): iterable
    {
        yield 'the rest' => [[4000]];
        yield 'more than the rest' => [[4500]];
        // the order is partially authorized after the first, from which it may be authorized
        yield 'the rest in two authorizations' => [[2500, 1500]];
    }

    /**
     * Until the authorizations cover the rest, the order is partially authorized, as it would be without a gift card,
     * rather than partially paid, from which it could not be authorized any more once the rest of the rest is: when the
     * rest is authorized in two payments, or before a second gift card's payment completes (review of #504)
     *
     * @test
     */
    public function it_partially_authorizes_an_order_whose_rest_is_authorized_in_part(): void
    {
        $order = $this->order(10000, 6000, [
            $this->giftCardPayment(PaymentInterface::STATE_COMPLETED, 6000),
            $this->gatewayPayment(PaymentInterface::STATE_AUTHORIZED, 2500),
            $this->gatewayPayment(PaymentInterface::STATE_NEW, 1500),
        ]);
        $this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_PARTIALLY_AUTHORIZE)->willReturn(true);

        $this->resolver()->resolve($order->reveal());

        $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_PARTIALLY_AUTHORIZE)->shouldHaveBeenCalledOnce();
        $this->decorated->resolve(Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * Resolving the order again, after another payment transition, must not make it partially paid
     *
     * @test
     */
    public function it_leaves_an_order_authorized_for_the_rest_as_it_is_once_it_cannot_be_authorized(): void
    {
        $order = $this->order(10000, 6000, [
            $this->giftCardPayment(PaymentInterface::STATE_COMPLETED, 6000),
            $this->gatewayPayment(PaymentInterface::STATE_AUTHORIZED, 4000),
        ]);
        $this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_AUTHORIZE)->willReturn(false);

        $this->resolver()->resolve($order->reveal());

        $this->stateMachine->apply(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->decorated->resolve(Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * Without a gift card paying anything, or with the gift cards paying all of it, there is nothing for the plugin to
     * add to what Sylius makes of the order. And once the rest is captured, or a payment of the customer's own refunded,
     * Sylius resolves the order as always
     *
     * @test
     *
     * @dataProvider ordersNotAuthorizedForTheRestByGiftCards
     *
     * @param list<array{string, int, bool}> $payments each payment's state, amount and whether it is a gift card payment
     */
    public function it_lets_sylius_resolve_an_order_the_gift_cards_and_the_authorizations_do_not_cover(int $paidByGiftCards, array $payments): void
    {
        $order = $this->order(10000, $paidByGiftCards, array_map(
            fn (array $payment): ObjectProphecy => $this->payment(...$payment),
            $payments,
        ));

        $this->resolver()->resolve($order->reveal());

        $this->stateMachine->apply(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->decorated->resolve($order->reveal())->shouldHaveBeenCalledOnce();
    }

    /**
     * @return iterable<string, array{int, list<array{string, int, bool}>}>
     */
    public function ordersNotAuthorizedForTheRestByGiftCards(): iterable
    {
        yield 'an authorization of the whole order without a gift card' => [0, [
            [PaymentInterface::STATE_AUTHORIZED, 10000, false],
        ]];

        yield 'an authorization next to gift cards paying the whole order' => [10000, [
            [PaymentInterface::STATE_COMPLETED, 10000, true],
            [PaymentInterface::STATE_AUTHORIZED, 4000, false],
        ]];

        yield 'the rest captured' => [6000, [
            [PaymentInterface::STATE_COMPLETED, 6000, true],
            [PaymentInterface::STATE_AUTHORIZED, 4000, false],
            [PaymentInterface::STATE_COMPLETED, 4000, false],
        ]];

        yield 'a payment of the customer refunded' => [6000, [
            [PaymentInterface::STATE_COMPLETED, 6000, true],
            [PaymentInterface::STATE_REFUNDED, 4000, false],
            [PaymentInterface::STATE_AUTHORIZED, 4000, false],
        ]];
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
            $this->stateMachine->reveal(),
        );
    }

    /**
     * @return ObjectProphecy<PaymentInterface>
     */
    private function gatewayPayment(string $state, int $amount = 4000): ObjectProphecy
    {
        return $this->payment($state, $amount, false);
    }

    /**
     * @return ObjectProphecy<PaymentInterface>
     */
    private function giftCardPayment(string $state, int $amount = 6000): ObjectProphecy
    {
        return $this->payment($state, $amount, true);
    }

    /**
     * @return ObjectProphecy<PaymentInterface>
     */
    private function payment(string $state, int $amount, bool $giftCard): ObjectProphecy
    {
        $payment = $this->prophesize(PaymentInterface::class);
        $payment->getState()->willReturn($state);
        $payment->getAmount()->willReturn($amount);

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
