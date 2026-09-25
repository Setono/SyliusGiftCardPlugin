<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\Updater\UnpaidOrdersStateUpdaterInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * An order a gift card pays only in part is placed with a completed gift card payment and a payment for the rest
 * that is still to be made. Sylius' shop only lets a customer pay for an order, or change how to pay it, while the
 * order awaits payment, and Sylius only expires unpaid orders that await payment. So the order has to keep awaiting
 * payment until the rest is paid, rather than become partially paid, and from there on paying the rest, failing to
 * and never paying it have to go the way they go for an order without a gift card.
 *
 * Both of Sylius' state machine adapters resolve the order's payment state through the same resolver service, so
 * each journey is driven through winzou (the application's default adapter) and through Symfony Workflow itself
 */
final class OrderPaidInPartByGiftCardTest extends GiftCardFunctionalTestCase
{
    private const WINZOU = 'winzou';

    private const SYMFONY_WORKFLOW = 'symfony_workflow';

    /**
     * @test
     *
     * @dataProvider adapters
     */
    public function the_order_awaits_payment_of_what_the_gift_card_does_not_cover(string $adapter): void
    {
        $giftCard = $this->createEnabledGiftCard('PARTPAID00000001', 6000);
        $order = $this->createCheckoutReadyOrder(10000, $giftCard, 4000);

        $this->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE, $adapter);

        self::assertSame(OrderCheckoutStates::STATE_COMPLETED, $order->getCheckoutState());
        self::assertSame(OrderPaymentStates::STATE_AWAITING_PAYMENT, $order->getPaymentState());

        $giftCardPayment = $this->giftCardPayment($order);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $giftCardPayment->getState());
        self::assertSame(6000, $giftCardPayment->getAmount());
        self::assertSame(0, $giftCard->getAmount());

        $rest = $this->restPayment($order);
        self::assertSame(PaymentInterface::STATE_NEW, $rest->getState());
        self::assertSame(4000, $rest->getAmount());
    }

    /**
     * Paying the rest is what pays the order, and paying the order is what issues the gift cards bought with it
     *
     * @test
     *
     * @dataProvider adapters
     */
    public function paying_the_rest_pays_the_order_and_issues_the_gift_cards_it_bought(string $adapter): void
    {
        // A gift card cannot pay for another gift card, so the redeemed card pays 3000 of the 6000 mug, and the rest,
        // the other 3000 of the mug and the 4000 gift card line, is paid by other means
        $giftCard = $this->createEnabledGiftCard('PARTPAID00000002', 3000);
        $order = $this->createCheckoutReadyOrder(6000, $giftCard, 7000);
        $giftCardLine = $this->addItem($order, 'GIFT_CARD', 4000, giftCard: true);
        $this->manager->flush();

        $this->placeOrder($order);
        self::assertSame(OrderPaymentStates::STATE_AWAITING_PAYMENT, $order->getPaymentState());

        $bought = $this->boughtGiftCard($giftCardLine);
        self::assertFalse($bought->isEnabled(), 'precondition: the gift card bought is pending until the order is paid');

        $this->apply($this->restPayment($order), PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE, $adapter);

        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
        self::assertTrue($bought->isEnabled(), 'paying the order should have issued the gift card it bought');
        self::assertSame(4000, $bought->getAmount());
    }

    /**
     * An online gateway can leave the payment processing while it waits for the payment provider to confirm it.
     * Nothing has been paid yet, so the order still waits for the money
     *
     * @test
     *
     * @dataProvider adapters
     */
    public function a_payment_for_the_rest_still_processing_leaves_the_order_awaiting_payment(string $adapter): void
    {
        $giftCard = $this->createEnabledGiftCard('PARTPAID00000003', 6000);
        $order = $this->createCheckoutReadyOrder(10000, $giftCard, 4000);
        $this->placeOrder($order);

        $this->apply($this->restPayment($order), PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS, $adapter);

        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->restPayment($order)->getState());
        self::assertSame(OrderPaymentStates::STATE_AWAITING_PAYMENT, $order->getPaymentState());
    }

    /**
     * Sylius' unpaid order expiry cancels orders that await payment, and cancelling an order refunds its gift card
     * payments, which is what gives the card its balance back
     *
     * @test
     */
    public function an_order_whose_rest_is_never_paid_expires_and_gives_the_gift_card_its_balance_back(): void
    {
        $giftCard = $this->createEnabledGiftCard('PARTPAID00000004', 6000);
        $order = $this->createCheckoutReadyOrder(10000, $giftCard, 4000);
        $this->placeOrder($order);
        self::assertSame(0, $giftCard->getAmount(), 'precondition: the card paid everything it had into the order');

        /** @var string $expirationPeriod */
        $expirationPeriod = self::getContainer()->getParameter('sylius_order.order_expiration_period');
        $order->setCheckoutCompletedAt(new \DateTime(sprintf('-%s -1 hour', $expirationPeriod)));
        $this->manager->flush();

        /** @var UnpaidOrdersStateUpdaterInterface $unpaidOrdersStateUpdater */
        $unpaidOrdersStateUpdater = self::getContainer()->get('sylius.unpaid_orders_state_updater');
        $unpaidOrdersStateUpdater->cancel();

        // the updater clears the entity manager once it has flushed, so everything is read back from the database
        $order = $this->manager->find(Order::class, $order->getId());
        self::assertInstanceOf(Order::class, $order);
        self::assertSame(BaseOrderInterface::STATE_CANCELLED, $order->getState(), 'the unpaid order should have expired');
        self::assertSame(OrderPaymentStates::STATE_CANCELLED, $order->getPaymentState());
        self::assertSame(PaymentInterface::STATE_REFUNDED, $this->giftCardPayment($order)->getState());
        self::assertSame(PaymentInterface::STATE_CANCELLED, $this->restPayment($order)->getState());

        $giftCard = $this->manager->find($giftCard::class, $giftCard->getId());
        self::assertInstanceOf(GiftCardInterface::class, $giftCard);
        self::assertSame(6000, $giftCard->getAmount(), 'cancelling the order should have given the card its balance back');
    }

    /**
     * Where the gift cards cover the whole order there is nothing left to pay, and Sylius pays the order on the spot
     *
     * @test
     *
     * @dataProvider adapters
     */
    public function an_order_the_gift_card_covers_in_full_is_paid(string $adapter): void
    {
        $giftCard = $this->createEnabledGiftCard('PARTPAID00000005', 15000);
        $order = $this->createCheckoutReadyOrder(10000, $giftCard, null);

        $this->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE, $adapter);

        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
        self::assertSame(10000, $this->giftCardPayment($order)->getAmount());
        self::assertSame(5000, $giftCard->getAmount());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public function adapters(): iterable
    {
        yield 'winzou' => [self::WINZOU];
        yield 'symfony workflow' => [self::SYMFONY_WORKFLOW];
    }

    /**
     * Applies the transition through the given adapter. Symfony Workflow is driven directly, the way Sylius drives it
     * when an application sets sylius_core.state_machine.default_adapter to symfony_workflow; the transitions Sylius
     * and the plugin cascade from there still go through the application's default adapter
     */
    private function apply(object $subject, string $graph, string $transition, string $adapter): void
    {
        if (self::SYMFONY_WORKFLOW === $adapter) {
            /** @var WorkflowInterface $workflow */
            $workflow = self::getContainer()->get('state_machine.' . $graph);
            $workflow->apply($subject, $transition);
        } else {
            $this->stateMachine()->apply($subject, $graph, $transition);
        }

        $this->manager->flush();
    }

    private function placeOrder(Order $order): void
    {
        $this->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE, self::WINZOU);
    }

    private function stateMachine(): StateMachineInterface
    {
        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');

        return $stateMachine;
    }

    /**
     * An ordinary product in a cart with the gift card applied, in the state a cart is in right before the customer
     * places the order: the customer has picked a payment method for the rest, which the checkout payment processor
     * sized to what the gift card does not cover, or the gift card covers everything and the payment step was skipped
     */
    private function createCheckoutReadyOrder(int $unitPrice, GiftCardInterface $giftCard, ?int $rest): Order
    {
        $customer = new Customer();
        $customer->setEmail(strtolower((string) $giftCard->getCode()) . '@example.com');
        $this->manager->persist($customer);

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCustomer($customer);
        $order->setCheckoutState(null === $rest ? OrderCheckoutStates::STATE_PAYMENT_SKIPPED : OrderCheckoutStates::STATE_PAYMENT_SELECTED);

        $this->addItem($order, 'MUG', $unitPrice);
        $order->addGiftCard($giftCard);

        if (null !== $rest) {
            /** @var PaymentFactoryInterface<PaymentInterface> $factory */
            $factory = self::getContainer()->get('sylius.factory.payment');

            $payment = $factory->createWithAmountAndCurrencyCode($rest, 'USD');
            self::assertInstanceOf(PaymentInterface::class, $payment);
            $payment->setMethod($this->createCashPaymentMethod());
            $order->addPayment($payment);
        }

        $this->manager->persist($order);
        $this->manager->flush();

        return $order;
    }

    private function createCashPaymentMethod(): PaymentMethodInterface
    {
        /** @var PaymentMethodFactoryInterface<PaymentMethodInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.payment_method');

        $paymentMethod = $factory->createWithGateway('offline');
        $paymentMethod->setCode('cash');
        $paymentMethod->setEnabled(true);
        $paymentMethod->getGatewayConfig()?->setGatewayName('cash');
        $paymentMethod->setCurrentLocale('en_US');
        $paymentMethod->setFallbackLocale('en_US');
        $paymentMethod->setName('Cash');
        $paymentMethod->addChannel($this->getChannel());

        $this->manager->persist($paymentMethod);

        return $paymentMethod;
    }

    private function giftCardPayment(Order $order): PaymentInterface
    {
        $payments = $this->paymentsBy($order, true);
        self::assertCount(1, $payments, 'the order should carry exactly one gift card payment');

        return $payments[0];
    }

    /**
     * The payment for what the gift card does not cover: the one that is not a gift card payment, or the replacement
     * Sylius provided for it
     */
    private function restPayment(Order $order): PaymentInterface
    {
        $payments = $this->paymentsBy($order, false);
        self::assertNotEmpty($payments, 'the order should carry a payment for the rest');

        return $payments[array_key_last($payments)];
    }

    /**
     * @return list<PaymentInterface>
     */
    private function paymentsBy(Order $order, bool $giftCard): array
    {
        /** @var string $paymentMethodCode */
        $paymentMethodCode = self::getContainer()->getParameter('setono_sylius_gift_card.redemption.payment_method_code');

        $payments = [];
        foreach ($order->getPayments() as $payment) {
            self::assertInstanceOf(PaymentInterface::class, $payment);

            if ($giftCard === ($payment->getMethod()?->getCode() === $paymentMethodCode)) {
                $payments[] = $payment;
            }
        }

        return $payments;
    }

    private function boughtGiftCard(OrderItem $item): GiftCardInterface
    {
        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);

        $giftCard = $unit->getGiftCard();
        self::assertInstanceOf(GiftCardInterface::class, $giftCard, 'placing the order should have given the unit its gift card');

        return $giftCard;
    }
}
