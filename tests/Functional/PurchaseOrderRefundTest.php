<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Payment\PaymentTransitions;

/**
 * Refunding an order that bought gift cards gives the customer their money back, so the cards must not stay
 * spendable on top of that. Only a refund of the whole order says that much, though: a partial refund does not say
 * which payments or items the money went back for, so the cards are left alone and the merchant disables them by
 * hand where that is what the refund meant
 */
final class PurchaseOrderRefundTest extends OrderLifecycleTestCase
{
    /**
     * The admin's "Refund" button refunds a payment, and Sylius resolves the order's payment state from that, so
     * this is the whole chain from the refunded payment down to the disabled card
     *
     * @test
     *
     * @dataProvider adapters
     */
    public function refunding_the_payment_of_an_order_that_bought_gift_cards_disables_them(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        [$order, $giftCard, $payment] = $this->placePaidOrderBuyingAGiftCard('PURCHASE00000001');

        $this->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);

        self::assertSame(OrderPaymentStates::STATE_REFUNDED, $order->getPaymentState(), 'precondition: refunding the only payment refunds the order');
        self::assertFalse($giftCard->isEnabled(), 'the card bought with money that went back must not be spendable');
    }

    /**
     * Sylius itself only refunds whole payments. A refund of part of the order, as a refund plugin makes one, says so
     * on the order's payment state machine
     *
     * @test
     *
     * @dataProvider adapters
     */
    public function a_partial_refund_leaves_the_gift_cards_the_order_bought_usable(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        [$order, $giftCard] = $this->placePaidOrderBuyingAGiftCard('PURCHASE00000002');

        $this->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_PARTIALLY_REFUND);

        self::assertSame(OrderPaymentStates::STATE_PARTIALLY_REFUNDED, $order->getPaymentState());
        self::assertTrue($giftCard->isEnabled(), 'a partial refund does not say what it was for, so the card stays usable');
    }

    /**
     * An order that bought a virtual gift card, placed and paid in full with one cash payment, which issued the card
     *
     * @return array{Order, GiftCardInterface, PaymentInterface} the order, the card it bought and its payment
     */
    private function placePaidOrderBuyingAGiftCard(string $code): array
    {
        $order = $this->createCart();
        $item = $this->addItem($order, 'GIFT_CARD', 5000, giftCard: true);
        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);
        $giftCard = $this->createPendingGiftCard($code);
        $unit->setGiftCard($giftCard);
        $payment = $this->selectPayment($order, 5000);
        $this->manager->flush();

        $this->placeOrder($order);
        $this->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);

        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState(), 'precondition: the order is paid');
        self::assertTrue($giftCard->isEnabled(), 'precondition: paying the order enabled the card it bought');

        return [$order, $giftCard, $payment];
    }
}
