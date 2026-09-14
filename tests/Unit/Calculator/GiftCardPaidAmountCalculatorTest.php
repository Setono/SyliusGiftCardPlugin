<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Calculator;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardPaidAmountCalculator;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class GiftCardPaidAmountCalculatorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<GiftCardPaymentCheckerInterface> */
    private ObjectProphecy $paymentChecker;

    protected function setUp(): void
    {
        $this->paymentChecker = $this->prophesize(GiftCardPaymentCheckerInterface::class);
    }

    /** @test */
    public function it_sums_the_completed_gift_card_payments(): void
    {
        $order = $this->orderWith([
            $this->giftCardPayment(PaymentInterface::STATE_COMPLETED, 6000),
            $this->giftCardPayment(PaymentInterface::STATE_COMPLETED, 1500),
        ]);

        $calculator = new GiftCardPaidAmountCalculator($this->paymentChecker->reveal());

        self::assertSame(7500, $calculator->getPaidAmount($order));
    }

    /** @test */
    public function it_ignores_gateway_payments_and_gift_card_payments_that_are_not_completed(): void
    {
        $order = $this->orderWith([
            $this->giftCardPayment(PaymentInterface::STATE_COMPLETED, 6000),
            $this->gatewayPayment(PaymentInterface::STATE_COMPLETED, 4000),
            $this->giftCardPayment(PaymentInterface::STATE_REFUNDED, 3000),
            $this->giftCardPayment(PaymentInterface::STATE_NEW, 2000),
        ]);

        $calculator = new GiftCardPaidAmountCalculator($this->paymentChecker->reveal());

        self::assertSame(6000, $calculator->getPaidAmount($order));
    }

    /** @test */
    public function it_returns_zero_when_the_order_has_no_gift_card_payments(): void
    {
        $order = $this->orderWith([$this->gatewayPayment(PaymentInterface::STATE_COMPLETED, 10000)]);

        $calculator = new GiftCardPaidAmountCalculator($this->paymentChecker->reveal());

        self::assertSame(0, $calculator->getPaidAmount($order));
    }

    private function giftCardPayment(string $state, int $amount): PaymentInterface
    {
        return $this->payment($state, $amount, true);
    }

    private function gatewayPayment(string $state, int $amount): PaymentInterface
    {
        return $this->payment($state, $amount, false);
    }

    private function payment(string $state, int $amount, bool $giftCard): PaymentInterface
    {
        $payment = $this->prophesize(PaymentInterface::class);
        $payment->getState()->willReturn($state);
        $payment->getAmount()->willReturn($amount);

        $this->paymentChecker->isGiftCardPayment($payment->reveal())->willReturn($giftCard);

        return $payment->reveal();
    }

    /**
     * @param list<PaymentInterface> $payments
     */
    private function orderWith(array $payments): OrderInterface
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getPayments()->willReturn(new ArrayCollection($payments));

        return $order->reveal();
    }
}
