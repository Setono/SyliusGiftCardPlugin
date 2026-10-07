<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Payment\PaymentTransitions;

/**
 * A refunded gift card payment has given the shop's money back, so the card has to get its balance back too, and
 * exactly once however the refund came about: an admin refunding the payment by hand (the admin's "Refund" button
 * applies sylius_payment.refund), the order being cancelled (rollback refunds its gift card payments), or the
 * callback firing again. The order here is only partly covered by the card, so Sylius has a gateway payment left
 * to resolve the order's payment state from once the gift card payment is gone
 */
final class GiftCardPaymentRefundTest extends OrderLifecycleTestCase
{
    /**
     * @test
     *
     * @dataProvider adapters
     */
    public function refunding_a_gift_card_payment_restores_the_balance_once(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        [$order, $giftCard, $payment] = $this->placePaidOrderCoveredInPartByAGiftCard('REFUND0000000001');

        $this->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);

        self::assertSame(PaymentInterface::STATE_REFUNDED, $payment->getState());
        $this->assertRestoredOnce($order, $giftCard, $payment);
        self::assertSame(
            OrderPaymentStates::STATE_PARTIALLY_REFUNDED,
            $order->getPaymentState(),
            'Sylius should still have resolved the order payment state from the refunded payment',
        );

        // the callback firing again for the same payment must not hand the money out twice
        $this->redemptionMethod()->rollbackPayment($payment);
        $this->manager->flush();

        $this->assertRestoredOnce($order, $giftCard, $payment);
    }

    /**
     * Cancelling refunds the gift card payment, which is now the one place the balance comes back from, so a
     * cancelled order must still end up with the balance restored exactly once
     *
     * @test
     *
     * @dataProvider adapters
     */
    public function cancelling_the_order_restores_the_balance_once(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        [$order, $giftCard, $payment] = $this->placePaidOrderCoveredInPartByAGiftCard('CANCEL0000000001');

        $this->apply($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        self::assertSame(BaseOrderInterface::STATE_CANCELLED, $order->getState());
        self::assertSame(PaymentInterface::STATE_REFUNDED, $payment->getState(), 'rollback should have refunded the gift card payment');
        $this->assertRestoredOnce($order, $giftCard, $payment);
    }

    /**
     * Asserts against the database rather than the identity map: the balance and the ledger are read back with
     * queries, so this is what was actually persisted
     */
    private function assertRestoredOnce(Order $order, GiftCardInterface $giftCard, PaymentInterface $payment): void
    {
        self::assertSame(3000, $this->persistedBalanceOf($giftCard), 'the card should hold exactly what the payment took, once');

        $ledger = $this->persistedLedgerOf($giftCard);
        self::assertSame(
            [GiftCardTransactionInterface::TYPE_REDEEM, GiftCardTransactionInterface::TYPE_RESTORE],
            array_column($ledger, 'type'),
            'refunding should have written exactly one restore row',
        );

        $restore = $ledger[1];
        self::assertSame(3000, $restore['amount']);
        self::assertSame(sprintf('restore:payment:%d', (int) $payment->getId()), $restore['idempotencyKey']);
        self::assertSame($payment->getId(), $restore['payment'], 'the restore row should link the payment it gives back');
        self::assertSame($order->getId(), $restore['order']);
    }

    /**
     * A placed and paid order for a 5000 mug, 3000 of which the gift card paid and the rest a cash payment. Placing
     * the order is what turns the applied card into a completed gift card payment and takes the balance, so the
     * payment carries the details the refund hook resolves the card from. The mug is still to be shipped, which
     * keeps the paid order open for cancelling
     *
     * @return array{Order, GiftCardInterface, PaymentInterface} the order, the gift card and its payment
     */
    private function placePaidOrderCoveredInPartByAGiftCard(string $code): array
    {
        $giftCard = $this->createEnabledGiftCard($code, 3000);
        $order = $this->createCart();
        $this->addItem($order, 'MUG', 5000)->getVariant()?->setShippingRequired(true);
        $order->addShipment($this->createShipment());
        $order->addGiftCard($giftCard);
        $rest = $this->selectPayment($order, 2000);
        $this->manager->flush();

        $this->placeOrder($order);
        $this->apply($rest, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);

        $payments = $this->giftCardPayments($order);
        self::assertCount(1, $payments, 'precondition: placing the order made a gift card payment');
        $payment = $payments[0];
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState(), 'precondition: placing the order completed the gift card payment');
        self::assertSame(3000, $payment->getAmount());
        self::assertSame(0, $this->persistedBalanceOf($giftCard), 'precondition: the card paid everything it had into the order');
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
        self::assertSame(BaseOrderInterface::STATE_NEW, $order->getState(), 'precondition: the order still has something to ship');

        return [$order, $giftCard, $payment];
    }

    private function redemptionMethod(): GiftCardRedemptionMethodInterface
    {
        /** @var GiftCardRedemptionMethodInterface $redemptionMethod */
        $redemptionMethod = self::getContainer()->get('setono_sylius_gift_card.redemption_method');

        return $redemptionMethod;
    }
}
