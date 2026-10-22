<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusGiftCardPlugin\Payment\VirtualGiftCardOrderPaymentCapturer;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Payment\PaymentTransitions;

/**
 * A shop that captures on shipment would keep the buyer of a virtual gift card waiting for a capture nothing triggers.
 * With delivery.capture_virtual_orders, the authorized payment of an order that buys only virtual gift cards is
 * completed at once, and paying the order issues the cards as always. Anything that ships, a physical card included,
 * is charged when it ships (#500)
 */
final class VirtualGiftCardOrderPaymentCapturerTest extends TestCase
{
    use ProphecyTrait;

    private const VIRTUAL_GIFT_CARD = 'virtual gift card';

    private const PHYSICAL_GIFT_CARD = 'physical gift card';

    private const MUG = 'mug';

    private const DIGITAL_PRODUCT = 'digital product';

    private const NO_VARIANT = 'an item without a variant';

    /** @var ObjectProphecy<StateMachineInterface> */
    private ObjectProphecy $stateMachine;

    protected function setUp(): void
    {
        $this->stateMachine = $this->prophesize(StateMachineInterface::class);
        $this->stateMachine->can(Argument::cetera())->willReturn(true);
    }

    /**
     * @test
     *
     * @dataProvider orders
     *
     * @param list<string> $items
     */
    public function it_completes_the_authorized_payment_of_an_order_of_only_virtual_gift_cards(array $items, bool $captured): void
    {
        $payment = self::authorizedPaymentOf($items);

        $this->capturer(true)->capture($payment);

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)->shouldHaveBeenCalledTimes($captured ? 1 : 0);
    }

    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function orders(): iterable
    {
        yield 'a virtual gift card' => [[self::VIRTUAL_GIFT_CARD], true];
        yield 'virtual gift cards' => [[self::VIRTUAL_GIFT_CARD, self::VIRTUAL_GIFT_CARD], true];
        yield 'a physical gift card' => [[self::PHYSICAL_GIFT_CARD], false];
        yield 'a virtual and a physical gift card' => [[self::VIRTUAL_GIFT_CARD, self::PHYSICAL_GIFT_CARD], false];
        yield 'a virtual gift card and a mug' => [[self::VIRTUAL_GIFT_CARD, self::MUG], false];
        // only the plugin's own digital product is the plugin's to charge
        yield 'a virtual gift card and another digital product' => [[self::VIRTUAL_GIFT_CARD, self::DIGITAL_PRODUCT], false];
        yield 'nothing' => [[], false];
        yield 'a virtual gift card and an item without a variant' => [[self::VIRTUAL_GIFT_CARD, self::NO_VARIANT], false];
    }

    /** @test */
    public function it_leaves_the_payment_alone_unless_the_application_asks_for_it(): void
    {
        $this->capturer(false)->capture(self::authorizedPaymentOf([self::VIRTUAL_GIFT_CARD]));

        $this->stateMachine->apply(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * A payment completed, failed or cancelled in the meantime, by another callback, say, is not completed again
     *
     * @test
     */
    public function it_leaves_a_payment_that_is_no_longer_authorized_alone(): void
    {
        $payment = self::authorizedPaymentOf([self::VIRTUAL_GIFT_CARD]);
        $payment->setState(PaymentInterface::STATE_COMPLETED);

        $this->capturer(true)->capture($payment);

        $this->stateMachine->apply(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /** @test */
    public function it_leaves_a_payment_alone_that_the_state_machine_will_not_complete(): void
    {
        $payment = self::authorizedPaymentOf([self::VIRTUAL_GIFT_CARD]);
        $this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)->willReturn(false);

        $this->capturer(true)->capture($payment);

        $this->stateMachine->apply(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * A payment plugin captures in a callback before the payment is completed, and throws when the provider refuses
     * the capture. The authorization it runs within has to be saved all the same, so the failure is logged rather than
     * thrown, and the payment stays authorized (#511)
     *
     * @test
     */
    public function it_logs_a_failed_capture_and_leaves_the_payment_authorized(): void
    {
        $payment = self::authorizedPaymentOf([self::VIRTUAL_GIFT_CARD]);
        $payment->getOrder()?->setNumber('000000042');
        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)
            ->willThrow(new \RuntimeException('The capture was refused'));
        $logger = $this->prophesize(LoggerInterface::class);

        $this->capturer(true, $logger->reveal())->capture($payment);

        self::assertSame(PaymentInterface::STATE_AUTHORIZED, $payment->getState());
        $logger->error(
            Argument::that(static fn (string $message): bool => str_contains($message, 'order 000000042') && str_contains($message, 'The capture was refused')),
            Argument::withKey('exception'),
        )->shouldHaveBeenCalledOnce();
    }

    /**
     * Once the payment is completed the capture went through, so what fails after it, in paying the order, say, is
     * no failed capture: it is thrown, so the transitions it broke off are not saved half applied
     *
     * @test
     */
    public function it_throws_what_fails_after_the_payment_is_completed(): void
    {
        $payment = self::authorizedPaymentOf([self::VIRTUAL_GIFT_CARD]);
        $failure = new \RuntimeException('The email could not be sent');
        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)
            ->will(static function () use ($payment, $failure): never {
                $payment->setState(PaymentInterface::STATE_COMPLETED);

                throw $failure;
            });
        $logger = $this->prophesize(LoggerInterface::class);

        try {
            $this->capturer(true, $logger->reveal())->capture($payment);
            self::fail('the failure should have been thrown');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        $logger->error(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    private function capturer(bool $enabled, LoggerInterface $logger = new NullLogger()): VirtualGiftCardOrderPaymentCapturer
    {
        return new VirtualGiftCardOrderPaymentCapturer($this->stateMachine->reveal(), $enabled, $logger);
    }

    /**
     * @param list<string> $items
     */
    private static function authorizedPaymentOf(array $items): PaymentInterface
    {
        $order = new Order();
        foreach ($items as $item) {
            if (self::NO_VARIANT === $item) {
                $order->addItem(new OrderItem());

                continue;
            }

            $product = new Product();
            $product->setGiftCard(in_array($item, [self::VIRTUAL_GIFT_CARD, self::PHYSICAL_GIFT_CARD], true));

            $variant = new ProductVariant();
            $variant->setProduct($product);
            $variant->setShippingRequired(in_array($item, [self::PHYSICAL_GIFT_CARD, self::MUG], true));

            $orderItem = new OrderItem();
            $orderItem->setVariant($variant);
            $order->addItem($orderItem);
        }

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_AUTHORIZED);
        $order->addPayment($payment);

        return $payment;
    }
}
