<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Guard;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverage;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardApplicabilityCheckerInterface;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardInapplicabilityReason;
use Setono\SyliusGiftCardPlugin\Guard\GiftCardCoverageGuard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

/**
 * The guard the checkout's complete transition asks. A cart the gift cards fully covered skipped the payment step
 * and carries no gateway payment; one they covered in part carries a gateway payment sized to the rest. Either way
 * the cards have to pay what they did when the cart was sized, or the order is placed underpaid
 */
final class GiftCardCoverageGuardTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_passes_an_order_that_is_not_gift_card_aware(): void
    {
        $order = $this->prophesize(CoreOrderInterface::class)->reveal();

        self::assertTrue($this->guard()->isSatisfiedBy($order));
    }

    /** @test */
    public function it_passes_an_order_without_gift_cards(): void
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->hasGiftCards()->willReturn(false);
        $order->getGiftCards()->shouldNotBeCalled();

        self::assertTrue($this->guard()->isSatisfiedBy($order->reveal()));
    }

    /** @test */
    public function it_lists_the_applied_gift_cards_that_can_no_longer_pay_for_the_order(): void
    {
        $usable = $this->prophesize(GiftCardInterface::class)->reveal();
        $spent = $this->prophesize(GiftCardInterface::class)->reveal();
        $order = $this->orderWith([$usable, $spent], [$this->payment(5000)], 5000);

        $checker = $this->prophesize(GiftCardApplicabilityCheckerInterface::class);
        $checker->getInapplicabilityReason($usable, $order)->willReturn(null);
        $checker->getInapplicabilityReason($spent, $order)->willReturn(GiftCardInapplicabilityReason::NoBalance);

        $guard = $this->guard($checker->reveal(), 0);

        self::assertSame([$spent], $guard->getInapplicableGiftCards($order));
        // the gateway payment happens to cover the total, but a card that cannot be used has no business on the order
        self::assertTrue($guard->isTotalCovered($order));
        self::assertFalse($guard->isSatisfiedBy($order));
    }

    /** @test */
    public function it_is_satisfied_when_the_gift_cards_and_the_gateway_payment_add_up_to_the_total(): void
    {
        $order = $this->orderWith([$this->giftCard()], [$this->payment(3000)], 5000);

        $guard = $this->guard(coverage: 2000);

        self::assertTrue($guard->isTotalCovered($order));
        self::assertTrue($guard->isSatisfiedBy($order));
    }

    /** @test */
    public function it_is_not_satisfied_when_a_gift_card_covers_less_than_the_gateway_payment_was_sized_for(): void
    {
        // the gateway payment was sized for a card covering 2000 which has since been adjusted down to 1000
        $order = $this->orderWith([$this->giftCard()], [$this->payment(3000)], 5000);

        $guard = $this->guard(coverage: 1000);

        self::assertFalse($guard->isTotalCovered($order));
        self::assertFalse($guard->isSatisfiedBy($order));
    }

    /** @test */
    public function it_is_not_satisfied_when_a_card_that_covered_everything_no_longer_covers_it(): void
    {
        // the payment step was skipped on the strength of the card, so there is no gateway payment at all
        $order = $this->orderWith([$this->giftCard()], [], 5000);

        self::assertFalse($this->guard(coverage: 4000)->isSatisfiedBy($order));
    }

    /** @test */
    public function it_does_not_count_gift_card_payments_towards_the_total(): void
    {
        $giftCardPayment = $this->payment(5000);
        $order = $this->orderWith([$this->giftCard()], [$giftCardPayment], 5000);

        $paymentChecker = $this->prophesize(GiftCardPaymentCheckerInterface::class);
        $paymentChecker->isGiftCardPayment($giftCardPayment)->willReturn(true);

        $guard = new GiftCardCoverageGuard($this->applicable(), $this->coverageOf(0), $paymentChecker->reveal());

        self::assertFalse($guard->isTotalCovered($order));
    }

    private function guard(?GiftCardApplicabilityCheckerInterface $checker = null, int $coverage = 0): GiftCardCoverageGuard
    {
        $paymentChecker = $this->prophesize(GiftCardPaymentCheckerInterface::class);
        $paymentChecker->isGiftCardPayment(Argument::any())->willReturn(false);

        return new GiftCardCoverageGuard($checker ?? $this->applicable(), $this->coverageOf($coverage), $paymentChecker->reveal());
    }

    private function applicable(): GiftCardApplicabilityCheckerInterface
    {
        $checker = $this->prophesize(GiftCardApplicabilityCheckerInterface::class);
        $checker->getInapplicabilityReason(Argument::cetera())->willReturn(null);

        return $checker->reveal();
    }

    private function coverageOf(int $total): GiftCardCoverageCalculatorInterface
    {
        $entries = $total > 0 ? [['giftCard' => $this->giftCard(), 'amount' => $total]] : [];

        $calculator = $this->prophesize(GiftCardCoverageCalculatorInterface::class);
        $calculator->calculate(Argument::type(CoreOrderInterface::class))->willReturn(new GiftCardCoverage($entries));

        return $calculator->reveal();
    }

    private function giftCard(): GiftCardInterface
    {
        return $this->prophesize(GiftCardInterface::class)->reveal();
    }

    private function payment(int $amount): PaymentInterface
    {
        $payment = $this->prophesize(PaymentInterface::class);
        $payment->getAmount()->willReturn($amount);

        return $payment->reveal();
    }

    /**
     * @param list<GiftCardInterface> $giftCards
     * @param list<PaymentInterface> $payments
     */
    private function orderWith(array $giftCards, array $payments, int $total): OrderInterface
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->hasGiftCards()->willReturn([] !== $giftCards);
        $order->getGiftCards()->willReturn(new ArrayCollection($giftCards));
        $order->getPayments()->willReturn(new ArrayCollection($payments));
        $order->getTotal()->willReturn($total);

        return $order->reveal();
    }
}
