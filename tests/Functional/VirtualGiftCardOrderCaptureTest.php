<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Payment\VirtualGiftCardOrderPaymentCapturer;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Mime\Email;

/**
 * A shop whose payment provider authorizes at checkout and captures on shipment would keep the buyer of a virtual gift
 * card waiting for a capture nothing triggers. With delivery.capture_virtual_orders, the authorized payment of an order
 * that buys only virtual gift cards is completed straight away, which a payment plugin that captures on completion
 * captures at the gateway, and paying the order issues and emails the cards as always. An order with anything to ship
 * stays authorized until it ships, and so does every order without the setting (#500)
 */
final class VirtualGiftCardOrderCaptureTest extends OrderLifecycleTestCase
{
    /**
     * @test
     *
     * @dataProvider adapters
     */
    public function an_order_of_only_virtual_gift_cards_is_paid_and_issues_them_when_its_payment_is_authorized(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);
        $this->captureVirtualOrders();
        [$order, $giftCard, $payment] = $this->placeOrderBuyingAGiftCard('CAPTURED00000001');

        $this->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_AUTHORIZE);

        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
        self::assertTrue($giftCard->isEnabled(), 'paying the order should have issued the card');
        $ledger = $this->persistedLedgerOf($giftCard);
        self::assertCount(1, $ledger);
        self::assertSame(GiftCardTransactionInterface::TYPE_ISSUE, $ledger[0]['type']);
        self::assertCount(1, $this->sentEmails());
    }

    /**
     * A physical card is shipped, and charged when it ships, like whatever else the order holds
     *
     * @test
     *
     * @dataProvider ordersWithSomethingToShip
     */
    public function an_order_with_something_to_ship_stays_authorized(string $adapter, bool $physicalGiftCard): void
    {
        $this->useStateMachineAdapter($adapter);
        $this->captureVirtualOrders();
        [$order, $giftCard, $payment] = $this->placeOrderBuyingAGiftCard('CAPTURED00000002', physical: $physicalGiftCard, withAMug: !$physicalGiftCard);

        $this->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_AUTHORIZE);

        $this->assertWaitingForTheCapture($order, $giftCard, $payment);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function ordersWithSomethingToShip(): iterable
    {
        foreach (self::adapters() as $name => [$adapter]) {
            yield $name . ', a physical gift card' => [$adapter, true];
            yield $name . ', a virtual gift card and a mug' => [$adapter, false];
        }
    }

    /**
     * Completing a payment only charges the customer with a payment plugin that captures on completion, so nothing is
     * completed unless the shop asks for it
     *
     * @test
     *
     * @dataProvider adapters
     */
    public function by_default_an_order_of_only_virtual_gift_cards_stays_authorized(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);
        [$order, $giftCard, $payment] = $this->placeOrderBuyingAGiftCard('CAPTURED00000003');

        $this->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_AUTHORIZE);

        $this->assertWaitingForTheCapture($order, $giftCard, $payment);
    }

    /**
     * Configures the application the way delivery.capture_virtual_orders: true does, before anything has used the
     * capturer. After useStateMachineAdapter(), whose state machine the capturer applies the transition through
     */
    private function captureVirtualOrders(): void
    {
        $container = self::getContainer();

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = $container->get('sylius_abstraction.state_machine');
        $container->set(VirtualGiftCardOrderPaymentCapturer::class, new VirtualGiftCardOrderPaymentCapturer($stateMachine, true));
    }

    /**
     * An order buying a gift card, and a mug if asked, placed with a payment for all of it
     *
     * @return array{Order, GiftCardInterface, PaymentInterface}
     */
    private function placeOrderBuyingAGiftCard(string $code, bool $physical = false, bool $withAMug = false): array
    {
        $order = $this->createCart();
        $item = $this->addItem($order, 'GIFT_CARD', 5000, giftCard: true);
        $item->getVariant()?->setShippingRequired($physical);
        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);
        $giftCard = $this->createPendingGiftCard($code);
        $unit->setGiftCard($giftCard);
        if ($withAMug) {
            $this->addItem($order, 'MUG', 1000)->getVariant()?->setShippingRequired(true);
        }
        $payment = $this->selectPayment($order, $order->getTotal());
        $this->manager->flush();

        $this->placeOrder($order);
        self::assertSame(OrderPaymentStates::STATE_AWAITING_PAYMENT, $order->getPaymentState(), 'precondition: the order awaits payment');

        return [$order, $giftCard, $payment];
    }

    private function assertWaitingForTheCapture(Order $order, GiftCardInterface $giftCard, PaymentInterface $payment): void
    {
        self::assertSame(PaymentInterface::STATE_AUTHORIZED, $payment->getState());
        self::assertSame(OrderPaymentStates::STATE_AUTHORIZED, $order->getPaymentState());
        self::assertFalse($giftCard->isEnabled(), 'an authorization pays nothing yet');
        self::assertSame([], $this->persistedLedgerOf($giftCard));
        self::assertSame([], $this->sentEmails());
    }

    /**
     * @return list<Email>
     */
    private function sentEmails(): array
    {
        $emails = [];
        foreach (self::getMailerEvents() as $event) {
            $message = $event->getMessage();
            if (!$event->isQueued() && $message instanceof Email) {
                $emails[] = $message;
            }
        }

        return $emails;
    }
}
