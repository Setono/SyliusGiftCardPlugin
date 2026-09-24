<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * A refunded gift card payment has given the shop's money back, so the card has to get its balance back too, and
 * exactly once however the refund came about: an admin refunding the payment by hand (the admin's "Refund" button
 * applies sylius_payment.refund), the order being cancelled (rollback refunds its gift card payments), or the
 * callback firing again. The order here is only partly covered by the card, so Sylius has a gateway payment left
 * to resolve the order's payment state from once the gift card payment is gone
 */
final class GiftCardPaymentRefundTest extends GiftCardFunctionalTestCase
{
    /** @test */
    public function refunding_a_gift_card_payment_restores_the_balance_once(): void
    {
        [$order, $giftCard, $payment] = $this->placePartiallyCoveredOrder('REFUND0000000001');

        $this->stateMachine()->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
        $this->manager->flush();

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
     * Driving the transition through Symfony Workflow itself pits the workflow subscriber against Sylius' workflow
     * listeners, the way an application with sylius_core.state_machine.default_adapter set to symfony_workflow would
     *
     * @test
     */
    public function symfony_workflow_refunding_a_gift_card_payment_restores_the_balance_once(): void
    {
        [$order, $giftCard, $payment] = $this->placePartiallyCoveredOrder('REFUND0000000002');

        $this->paymentWorkflow()->apply($payment, PaymentTransitions::TRANSITION_REFUND);
        $this->manager->flush();

        self::assertSame(PaymentInterface::STATE_REFUNDED, $payment->getState());
        $this->assertRestoredOnce($order, $giftCard, $payment);
        self::assertSame(OrderPaymentStates::STATE_PARTIALLY_REFUNDED, $order->getPaymentState());

        $this->redemptionMethod()->rollbackPayment($payment);
        $this->manager->flush();

        $this->assertRestoredOnce($order, $giftCard, $payment);
    }

    /**
     * Cancelling refunds the gift card payment, which is now the one place the balance comes back from, so a
     * cancelled order must still end up with the balance restored exactly once
     *
     * @test
     */
    public function cancelling_the_order_restores_the_balance_once(): void
    {
        [$order, $giftCard, $payment] = $this->placePartiallyCoveredOrder('CANCEL0000000001');

        $this->stateMachine()->apply($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);
        $this->manager->flush();

        self::assertSame(BaseOrderInterface::STATE_CANCELLED, $order->getState());
        self::assertSame(PaymentInterface::STATE_REFUNDED, $payment->getState(), 'rollback should have refunded the gift card payment');
        $this->assertRestoredOnce($order, $giftCard, $payment);
    }

    /** @test */
    public function symfony_workflow_cancelling_the_order_restores_the_balance_once(): void
    {
        [$order, $giftCard, $payment] = $this->placePartiallyCoveredOrder('CANCEL0000000002');

        $this->orderWorkflow()->apply($order, OrderTransitions::TRANSITION_CANCEL);
        $this->manager->flush();

        self::assertSame(BaseOrderInterface::STATE_CANCELLED, $order->getState());
        self::assertSame(PaymentInterface::STATE_REFUNDED, $payment->getState(), 'rollback should have refunded the gift card payment');
        $this->assertRestoredOnce($order, $giftCard, $payment);
    }

    /**
     * Asserts against the database rather than the identity map: the balance is read back with a scalar query and
     * the ledger rows with a repository query, so this is what was actually persisted
     */
    private function assertRestoredOnce(Order $order, GiftCardInterface $giftCard, PaymentInterface $payment): void
    {
        self::assertSame(3000, $this->persistedBalance($giftCard), 'the card should hold exactly what the payment took, once');

        /** @var RepositoryInterface<GiftCardTransactionInterface> $transactionRepository */
        $transactionRepository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card_transaction');
        $restores = $transactionRepository->findBy([
            'giftCard' => $giftCard,
            'type' => GiftCardTransactionInterface::TYPE_RESTORE,
        ]);

        self::assertCount(1, $restores, 'refunding should have written exactly one restore row');
        $restore = $restores[0];
        self::assertSame(3000, $restore->getAmount());
        self::assertSame($payment, $restore->getPayment(), 'the restore row should link the payment it gives back');
        self::assertSame($order, $restore->getOrder());
    }

    private function persistedBalance(GiftCardInterface $giftCard): int
    {
        $amount = $this->manager
            ->createQuery(sprintf('SELECT g.amount FROM %s g WHERE g.id = :id', $giftCard::class))
            ->setParameter('id', $giftCard->getId())
            ->getSingleScalarResult()
        ;

        return (int) $amount;
    }

    /**
     * A placed and paid order for a 5000 mug, 3000 of which the gift card paid and the rest a gateway payment.
     * commit() is what turns the applied card into a completed gift card payment and takes the balance, so the
     * payment carries the details the refund hook resolves the card from
     *
     * @return array{Order, GiftCardInterface, PaymentInterface}
     */
    private function placePartiallyCoveredOrder(string $code): array
    {
        $giftCard = $this->createEnabledGiftCard($code, 3000);
        $order = $this->createPaidOrder($code, $giftCard);

        $this->redemptionMethod()->commit($order);
        $this->manager->flush();

        $payment = $this->giftCardPayment($order);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState(), 'precondition: commit should have completed the gift card payment');
        self::assertSame(3000, $payment->getAmount());
        self::assertSame(0, $giftCard->getAmount(), 'precondition: the card paid everything it had into the order');
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());

        return [$order, $giftCard, $payment];
    }

    private function createPaidOrder(string $code, GiftCardInterface $giftCard): Order
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('MUG_' . $code);
        $product->setName('Mug');
        $product->setSlug('mug-' . strtolower($code));
        $this->manager->persist($product);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('MUG_VARIANT_' . $code);
        $variant->setName('Mug');
        $variant->setProduct($product);
        $variant->setShippingRequired(false);
        $this->manager->persist($variant);

        $customer = new Customer();
        $customer->setEmail(strtolower($code) . '@example.com');
        $this->manager->persist($customer);

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCustomer($customer);
        $order->setCheckoutState(OrderCheckoutStates::STATE_COMPLETED);
        // cancel transitions from new, refund on the order payment graph from paid
        $order->setState(BaseOrderInterface::STATE_NEW);
        $order->setPaymentState(OrderPaymentStates::STATE_PAID);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(5000);
        new OrderItemUnit($item);
        $order->addItem($item);

        // the gateway payment sized to what the card does not cover
        $gatewayPayment = new Payment();
        $gatewayPayment->setCurrencyCode('USD');
        $gatewayPayment->setAmount(2000);
        $gatewayPayment->setState(PaymentInterface::STATE_COMPLETED);
        $order->addPayment($gatewayPayment);

        $order->addGiftCard($giftCard);

        $this->manager->persist($order);
        $this->manager->flush();

        self::assertSame(5000, $order->getTotal());

        return $order;
    }

    private function giftCardPayment(Order $order): PaymentInterface
    {
        /** @var string $paymentMethodCode */
        $paymentMethodCode = self::getContainer()->getParameter('setono_sylius_gift_card.redemption.payment_method_code');

        foreach ($order->getPayments() as $payment) {
            if ($payment instanceof PaymentInterface && $payment->getMethod()?->getCode() === $paymentMethodCode) {
                return $payment;
            }
        }

        self::fail('the order carries no gift card payment');
    }

    private function redemptionMethod(): GiftCardRedemptionMethodInterface
    {
        /** @var GiftCardRedemptionMethodInterface $redemptionMethod */
        $redemptionMethod = self::getContainer()->get('setono_sylius_gift_card.redemption_method');

        return $redemptionMethod;
    }

    private function stateMachine(): StateMachineInterface
    {
        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');

        return $stateMachine;
    }

    private function paymentWorkflow(): WorkflowInterface
    {
        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('state_machine.' . PaymentTransitions::GRAPH);

        return $workflow;
    }

    private function orderWorkflow(): WorkflowInterface
    {
        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('state_machine.' . OrderTransitions::GRAPH);

        return $workflow;
    }
}
