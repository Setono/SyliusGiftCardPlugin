<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\OrderProcessor;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverage;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardPaidAmountCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\OrderProcessor\GiftCardAwareOrderPaymentProcessor;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

/**
 * The same class decorates both of Sylius' payment processors, so it is exercised with both target states: 'cart'
 * for the checkout processor, where the gift cards' live balances say what they will cover, and 'new' for the
 * after-checkout processor, where the gift cards have been redeemed and only their completed payments say what
 * they did cover
 */
final class GiftCardAwareOrderPaymentProcessorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<OrderProcessorInterface> */
    private ObjectProphecy $decorated;

    /** @var ObjectProphecy<GiftCardCoverageCalculatorInterface> */
    private ObjectProphecy $coverageCalculator;

    /** @var ObjectProphecy<GiftCardPaidAmountCalculatorInterface> */
    private ObjectProphecy $paidAmountCalculator;

    /** @var ObjectProphecy<GiftCardPaymentCheckerInterface> */
    private ObjectProphecy $paymentChecker;

    protected function setUp(): void
    {
        $this->decorated = $this->prophesize(OrderProcessorInterface::class);
        $this->coverageCalculator = $this->prophesize(GiftCardCoverageCalculatorInterface::class);
        $this->paidAmountCalculator = $this->prophesize(GiftCardPaidAmountCalculatorInterface::class);
        $this->paymentChecker = $this->prophesize(GiftCardPaymentCheckerInterface::class);
    }

    /** @test */
    public function it_sizes_the_cart_payment_from_the_live_gift_card_coverage_during_checkout(): void
    {
        $cartPayment = $this->gatewayPayment(PaymentInterface::STATE_CART);
        $order = $this->order(10000, [$cartPayment]);

        $this->coverageCalculator->calculate($order->reveal())->willReturn($this->coverage(6000));
        $this->paidAmountCalculator->getPaidAmount(Argument::any())->shouldNotBeCalled();
        $cartPayment->setAmount(4000)->shouldBeCalled();

        $this->processor(PaymentInterface::STATE_CART)->process($order->reveal());

        $this->decorated->process($order->reveal())->shouldHaveBeenCalled();
    }

    /** @test */
    public function it_removes_the_cart_payment_when_the_gift_cards_cover_the_whole_order(): void
    {
        $cartPayment = $this->gatewayPayment(PaymentInterface::STATE_CART);
        $order = $this->order(10000, [$cartPayment]);

        $this->coverageCalculator->calculate($order->reveal())->willReturn($this->coverage(10000));
        $cartPayment->setAmount(Argument::any())->shouldNotBeCalled();
        $order->removePayment($cartPayment->reveal())->shouldBeCalled();

        $this->processor(PaymentInterface::STATE_CART)->process($order->reveal());
    }

    /** @test */
    public function it_sizes_the_replacement_payment_from_the_completed_gift_card_payments_after_checkout(): void
    {
        $giftCardPayment = $this->giftCardPayment(PaymentInterface::STATE_COMPLETED);
        $failedPayment = $this->gatewayPayment(PaymentInterface::STATE_FAILED);
        $replacement = $this->gatewayPayment(PaymentInterface::STATE_NEW);
        $order = $this->order(10000, [$giftCardPayment, $failedPayment, $replacement]);

        // The gift card has been redeemed at order placement, so its live balance says nothing about this order
        // any more and must not be consulted
        $this->coverageCalculator->calculate(Argument::any())->shouldNotBeCalled();
        $this->paidAmountCalculator->getPaidAmount($order->reveal())->willReturn(6000);
        $replacement->setAmount(4000)->shouldBeCalled();
        $giftCardPayment->setAmount(Argument::any())->shouldNotBeCalled();
        $failedPayment->setAmount(Argument::any())->shouldNotBeCalled();

        $this->processor(PaymentInterface::STATE_NEW)->process($order->reveal());

        $this->decorated->process($order->reveal())->shouldHaveBeenCalled();
    }

    /** @test */
    public function it_leaves_the_replacement_payment_alone_when_no_gift_card_paid_anything(): void
    {
        $replacement = $this->gatewayPayment(PaymentInterface::STATE_NEW);
        $order = $this->order(10000, [$replacement]);

        $this->paidAmountCalculator->getPaidAmount($order->reveal())->willReturn(0);
        $replacement->setAmount(Argument::any())->shouldNotBeCalled();
        $order->removePayment(Argument::any())->shouldNotBeCalled();

        $this->processor(PaymentInterface::STATE_NEW)->process($order->reveal());
    }

    private function processor(string $targetState): GiftCardAwareOrderPaymentProcessor
    {
        return new GiftCardAwareOrderPaymentProcessor(
            $this->decorated->reveal(),
            $this->coverageCalculator->reveal(),
            $this->paidAmountCalculator->reveal(),
            $this->paymentChecker->reveal(),
            $targetState,
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
     * @param list<ObjectProphecy<PaymentInterface>> $payments
     *
     * @return ObjectProphecy<OrderInterface>
     */
    private function order(int $total, array $payments): ObjectProphecy
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getTotal()->willReturn($total);
        $order->getPayments()->willReturn(new ArrayCollection(array_map(
            static fn (ObjectProphecy $payment): PaymentInterface => $payment->reveal(),
            $payments,
        )));

        return $order;
    }

    private function coverage(int $amount): GiftCardCoverage
    {
        return new GiftCardCoverage([
            ['giftCard' => $this->prophesize(GiftCardInterface::class)->reveal(), 'amount' => $amount],
        ]);
    }
}
