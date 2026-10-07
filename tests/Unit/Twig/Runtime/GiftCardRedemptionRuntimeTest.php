<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Twig\Runtime;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardRedemptionRuntime;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * The cart prints what the gift cards cover and what is left to pay from these functions. The figures come from
 * the configured redemption method, so an application that substitutes its own sees its figures in the cart. The
 * thank you page shows the instructions of the payment for what the gift cards do not pay, and the admin's payment
 * method form explains the gift card payment method
 */
final class GiftCardRedemptionRuntimeTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<GiftCardRedemptionMethodInterface> */
    private ObjectProphecy $redemptionMethod;

    /** @var ObjectProphecy<GiftCardPaymentCheckerInterface> */
    private ObjectProphecy $paymentChecker;

    protected function setUp(): void
    {
        $this->redemptionMethod = $this->prophesize(GiftCardRedemptionMethodInterface::class);
        $this->paymentChecker = $this->prophesize(GiftCardPaymentCheckerInterface::class);
    }

    /** @test */
    public function it_tells_what_the_redemption_method_says_the_gift_cards_cover(): void
    {
        $order = $this->order(10000);
        $giftCard = $this->prophesize(GiftCardInterface::class)->reveal();

        $this->redemptionMethod->getCoveredAmount($order)->willReturn(6000);
        $this->redemptionMethod->getCoveredAmountByGiftCard($order, $giftCard)->willReturn(2500);

        self::assertSame(6000, $this->runtime()->getCoveredAmount($order));
        self::assertSame(2500, $this->runtime()->getCoveredAmountByGiftCard($order, $giftCard));
    }

    /**
     * Redeeming leaves the order total alone, so what is left to pay is the total less what the cards cover
     *
     * @test
     *
     * @dataProvider coverages
     */
    public function it_tells_what_is_left_to_pay_after_the_gift_cards(int $covered, int $remaining): void
    {
        $order = $this->order(10000);
        $this->redemptionMethod->getCoveredAmount($order)->willReturn($covered);

        self::assertSame($remaining, $this->runtime()->getRemainingTotal($order));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function coverages(): iterable
    {
        yield 'no coverage' => [0, 10000];
        yield 'part of the order' => [6000, 4000];
        yield 'the whole order' => [10000, 0];
        // never a negative amount to pay, whatever a substituted redemption method reports
        yield 'more than the order' => [12000, 0];
    }

    /**
     * The gift card payments are added when the order is placed, after the payment the customer chose for the rest
     *
     * @test
     */
    public function it_tells_the_payment_for_what_the_gift_cards_do_not_pay_although_a_gift_card_payment_follows_it(): void
    {
        $rest = $this->payment(false);

        self::assertSame($rest, $this->runtime()->getRemainingPayment($this->orderWith($rest, $this->payment(true))));
    }

    /**
     * Sylius replaces a payment for the rest that fails or is cancelled, and the replacement is the one to pay
     *
     * @test
     */
    public function it_tells_the_last_payment_that_is_not_a_gift_card_payment(): void
    {
        $replacement = $this->payment(false);
        $order = $this->orderWith($this->payment(false), $this->payment(true), $replacement, $this->payment(true));

        self::assertSame($replacement, $this->runtime()->getRemainingPayment($order));
    }

    /** @test */
    public function it_tells_the_only_payment_of_an_order_without_gift_cards(): void
    {
        $payment = $this->payment(false);

        self::assertSame($payment, $this->runtime()->getRemainingPayment($this->orderWith($payment)));
    }

    /** @test */
    public function it_tells_no_payment_when_the_gift_cards_pay_the_whole_order(): void
    {
        self::assertNull($this->runtime()->getRemainingPayment($this->orderWith($this->payment(true), $this->payment(true))));
    }

    /** @test */
    public function it_tells_no_payment_for_an_order_without_payments(): void
    {
        self::assertNull($this->runtime()->getRemainingPayment($this->orderWith()));
    }

    /**
     * The admin's payment method form explains the gift card payment method, and the checker is what knows which one
     * that is
     *
     * @test
     */
    public function it_tells_whether_a_payment_method_is_the_gift_card_payment_method(): void
    {
        $giftCardPaymentMethod = $this->prophesize(PaymentMethodInterface::class)->reveal();
        $cash = $this->prophesize(PaymentMethodInterface::class)->reveal();
        $this->paymentChecker->isGiftCardPaymentMethod($giftCardPaymentMethod)->willReturn(true);
        $this->paymentChecker->isGiftCardPaymentMethod($cash)->willReturn(false);

        self::assertTrue($this->runtime()->isGiftCardPaymentMethod($giftCardPaymentMethod));
        self::assertFalse($this->runtime()->isGiftCardPaymentMethod($cash));
        self::assertFalse($this->runtime()->isGiftCardPaymentMethod(null));
    }

    private function runtime(): GiftCardRedemptionRuntime
    {
        return new GiftCardRedemptionRuntime(
            $this->redemptionMethod->reveal(),
            $this->prophesize(FormFactoryInterface::class)->reveal(),
            $this->paymentChecker->reveal(),
        );
    }

    private function payment(bool $giftCard): PaymentInterface
    {
        $payment = $this->prophesize(PaymentInterface::class)->reveal();
        $this->paymentChecker->isGiftCardPayment($payment)->willReturn($giftCard);

        return $payment;
    }

    private function orderWith(PaymentInterface ...$payments): BaseOrderInterface
    {
        $order = $this->prophesize(BaseOrderInterface::class);
        $order->getPayments()->willReturn(new ArrayCollection($payments));

        return $order->reveal();
    }

    private function order(int $total): OrderInterface
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getTotal()->willReturn($total);

        return $order->reveal();
    }
}
