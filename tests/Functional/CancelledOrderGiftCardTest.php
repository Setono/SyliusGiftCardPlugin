<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * Cancelling an order undoes what it did to gift cards: the cards that paid for it get their balance back, through
 * the refund of their payments, and the cards it bought stop being spendable
 */
final class CancelledOrderGiftCardTest extends OrderLifecycleTestCase
{
    /**
     * @test
     *
     * @dataProvider adapters
     */
    public function cancelling_the_order_refunds_its_gift_card_payment_and_gives_the_card_its_balance_back(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        [$order, $giftCard, $rest] = $this->placeOrderPaidInPartByAGiftCard('CANCELLED0000001');

        $this->apply($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        self::assertSame(OrderInterface::STATE_CANCELLED, $order->getState());
        self::assertSame(OrderPaymentStates::STATE_CANCELLED, $order->getPaymentState());

        [$giftCardPayment] = $this->giftCardPayments($order);
        self::assertSame(PaymentInterface::STATE_REFUNDED, $giftCardPayment->getState());
        self::assertSame(PaymentInterface::STATE_CANCELLED, $rest->getState(), 'the payment for the rest should have been cancelled');

        self::assertSame(6000, $this->persistedBalanceOf($giftCard));
        self::assertTrue($giftCard->isEnabled(), 'the card that paid belongs to somebody who is owed their balance back');
        $this->assertRestoredOnce($order, $giftCard, $giftCardPayment);
    }

    /**
     * The callbacks can fire again for an order that is already cancelled, as a retried request would. The gift card
     * payment is refunded by then, so there is nothing left to refund, and the restore row written the first time is
     * what keeps the balance from coming back twice
     *
     * @test
     */
    public function rolling_back_a_cancelled_order_again_gives_nothing_more_back(): void
    {
        [$order, $giftCard] = $this->placeOrderPaidInPartByAGiftCard('CANCELLED0000002');
        $this->apply($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        $order = $this->reload($order);
        [$giftCardPayment] = $this->giftCardPayments($order);

        /** @var GiftCardRedemptionMethodInterface $redemptionMethod */
        $redemptionMethod = self::getContainer()->get('setono_sylius_gift_card.redemption_method');
        $redemptionMethod->rollback($order);
        $redemptionMethod->rollbackPayment($giftCardPayment);
        $this->manager->flush();

        self::assertSame(PaymentInterface::STATE_REFUNDED, $giftCardPayment->getState());
        self::assertSame(6000, $this->persistedBalanceOf($giftCard));
        $this->assertRestoredOnce($order, $giftCard, $giftCardPayment);
    }

    /**
     * An order is only open for cancelling until it is fulfilled, and an order with nothing to ship is fulfilled as
     * soon as it is paid. So the order buys a physical gift card, which is still waiting to be shipped when the order
     * is cancelled
     *
     * @test
     *
     * @dataProvider adapters
     */
    public function cancelling_a_paid_order_disables_the_gift_cards_it_bought(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        $order = $this->createCart();
        $item = $this->addItem($order, 'GIFT_CARD', 5000, giftCard: true);
        $item->getVariant()?->setShippingRequired(true);
        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);
        $unit->setGiftCard($this->createPendingGiftCard('BOUGHT0000000001'));
        $order->addShipment($this->createShipment());
        $cash = $this->selectPayment($order, 5000);
        $this->manager->flush();

        $this->placeOrder($order);
        $this->apply($cash, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);

        $bought = $unit->getGiftCard();
        self::assertInstanceOf(GiftCardInterface::class, $bought);
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState(), 'precondition: the order is paid');
        self::assertSame(OrderInterface::STATE_NEW, $order->getState(), 'precondition: the order still has something to ship');
        self::assertTrue($bought->isEnabled(), 'precondition: paying the order enabled the card it bought');

        $this->apply($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        self::assertSame(OrderInterface::STATE_CANCELLED, $order->getState());
        self::assertFalse($bought->isEnabled(), 'the card of a cancelled order must not be spendable');
        self::assertSame(
            [GiftCardTransactionInterface::TYPE_ISSUE],
            array_column($this->persistedLedgerOf($bought), 'type'),
            'the issuance stays on record, the shop took money for the card',
        );
    }

    /**
     * A placed order for a 10000 mug, 6000 of which the gift card paid, awaiting the cash payment for the rest
     *
     * @return array{Order, GiftCardInterface, PaymentInterface} the order, the gift card and the payment for the rest
     */
    private function placeOrderPaidInPartByAGiftCard(string $code): array
    {
        $giftCard = $this->createEnabledGiftCard($code, 6000);
        $order = $this->createCart();
        $this->addItem($order, 'MUG', 10000);
        $order->addGiftCard($giftCard);
        $rest = $this->selectPayment($order, 4000);
        $this->manager->flush();

        $this->placeOrder($order);

        self::assertSame(OrderPaymentStates::STATE_AWAITING_PAYMENT, $order->getPaymentState(), 'precondition: the rest is still to be paid');
        self::assertSame(0, $this->persistedBalanceOf($giftCard), 'precondition: the card paid everything it had into the order');

        return [$order, $giftCard, $rest];
    }

    /**
     * The card's ledger holds the redemption and exactly one restore row, keyed on the refunded payment and pointing
     * at it and at the order
     */
    private function assertRestoredOnce(Order $order, GiftCardInterface $giftCard, PaymentInterface $payment): void
    {
        $ledger = $this->persistedLedgerOf($giftCard);
        self::assertSame(
            [GiftCardTransactionInterface::TYPE_REDEEM, GiftCardTransactionInterface::TYPE_RESTORE],
            array_column($ledger, 'type'),
            'the balance should have come back exactly once',
        );

        $restore = $ledger[1];
        self::assertSame(6000, $restore['amount']);
        self::assertSame(sprintf('restore:payment:%d', (int) $payment->getId()), $restore['idempotencyKey']);
        self::assertSame($payment->getId(), $restore['payment']);
        self::assertSame($order->getId(), $restore['order']);
    }

    /**
     * The shipment the checkout gave the order, with the shipping method the customer picked
     */
    private function createShipment(): ShipmentInterface
    {
        $container = self::getContainer();

        /** @var FactoryInterface<ZoneInterface> $zoneFactory */
        $zoneFactory = $container->get('sylius.factory.zone');
        $zone = $zoneFactory->createNew();
        $zone->setCode('WORLD');
        $zone->setName('World');
        $zone->setType(ZoneInterface::TYPE_COUNTRY);
        $this->manager->persist($zone);

        /** @var FactoryInterface<ShippingMethodInterface> $shippingMethodFactory */
        $shippingMethodFactory = $container->get('sylius.factory.shipping_method');
        $shippingMethod = $shippingMethodFactory->createNew();
        $shippingMethod->setCode('post');
        $shippingMethod->setCurrentLocale('en_US');
        $shippingMethod->setFallbackLocale('en_US');
        $shippingMethod->setName('Post');
        $shippingMethod->setZone($zone);
        $shippingMethod->setCalculator('flat_rate');
        $shippingMethod->setConfiguration([(string) $this->getChannel()->getCode() => ['amount' => 0]]);
        $shippingMethod->addChannel($this->getChannel());
        $this->manager->persist($shippingMethod);

        /** @var FactoryInterface<ShipmentInterface> $shipmentFactory */
        $shipmentFactory = $container->get('sylius.factory.shipment');
        $shipment = $shipmentFactory->createNew();
        $shipment->setMethod($shippingMethod);

        return $shipment;
    }
}
