<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use SM\Event\SMEvents;
use SM\Event\TransitionEvent;
use Sylius\Abstraction\StateMachine\CompositeStateMachine;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * Takes orders with gift cards through Sylius' state machines: placing, paying and cancelling them.
 *
 * The plugin hooks those transitions twice, as winzou callbacks prepended in the extension and as the Symfony Workflow
 * subscribers in EventSubscriber/Workflow, and only the adapter that applies a transition runs its own set: the one
 * sylius_state_machine_abstraction.default_adapter names. A test that applies a transition through Symfony Workflow
 * directly reaches the subscribers of that one transition only, because Sylius applies the transitions it cascades
 * from there through the application's adapter, which is winzou in the test application. useStateMachineAdapter()
 * makes the given adapter the application's only one instead, so the cascaded transitions go through it as well, and
 * the test fails if the other adapter applied, or was asked about, a single transition
 */
abstract class OrderLifecycleTestCase extends GiftCardFunctionalTestCase
{
    protected const WINZOU = 'winzou_state_machine';

    protected const SYMFONY_WORKFLOW = 'symfony_workflow';

    private ?string $adapter = null;

    /** @var array<string, list<string>> the transitions applied during the test, by the adapter that applied them */
    private array $appliedTransitions = [];

    /**
     * @var array<string, list<string>> the transitions an adapter was asked about during the test, by that adapter. It
     *                                  checks every transition before it applies it, and only checks one it refuses
     */
    private array $checkedTransitions = [];

    protected function setUp(): void
    {
        parent::setUp();

        // the gift card payments are made with it, and the shop refuses gift cards until it is set up
        $this->createGiftCardPaymentMethod();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function adapters(): iterable
    {
        yield 'winzou' => [self::WINZOU];
        yield 'symfony workflow' => [self::SYMFONY_WORKFLOW];
    }

    /**
     * Configures the application the way sylius_state_machine_abstraction.default_adapter does, by swapping the state
     * machine every Sylius and plugin service applies transitions through for one that knows the given adapter only.
     * The container refuses to swap a service it has already handed out, so this fails loudly rather than leave some
     * service on the old adapter
     */
    protected function useStateMachineAdapter(string $adapter): void
    {
        $container = self::getContainer();

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = $container->get('sylius_abstraction.state_machine.adapter.' . $adapter);
        $container->set('sylius_abstraction.state_machine.composite', new CompositeStateMachine([$adapter => $stateMachine], $adapter, []));

        $this->adapter = $adapter;

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $container->get('event_dispatcher');
        $dispatcher->addListener(SMEvents::POST_TRANSITION, function (TransitionEvent $event): void {
            $this->appliedTransitions[self::WINZOU][] = sprintf('%s.%s', $event->getStateMachine()->getGraph(), $event->getTransition());
        });
        $dispatcher->addListener('workflow.completed', function (CompletedEvent $event): void {
            $this->appliedTransitions[self::SYMFONY_WORKFLOW][] = sprintf('%s.%s', $event->getWorkflowName(), (string) $event->getTransition()?->getName());
        });
        $dispatcher->addListener(SMEvents::TEST_TRANSITION, function (TransitionEvent $event): void {
            $this->checkedTransitions[self::WINZOU][] = sprintf('%s.%s', $event->getStateMachine()->getGraph(), $event->getTransition());
        });
        $dispatcher->addListener('workflow.guard', function (GuardEvent $event): void {
            $this->checkedTransitions[self::SYMFONY_WORKFLOW][] = sprintf('%s.%s', $event->getWorkflowName(), $event->getTransition()->getName());
        });
    }

    protected function assertPostConditions(): void
    {
        parent::assertPostConditions();

        if (null === $this->adapter) {
            return;
        }

        $applied = $this->appliedTransitions;
        unset($applied[$this->adapter]);

        $checked = $this->checkedTransitions;
        unset($checked[$this->adapter]);

        // a test may only refuse a transition, which the adapter checks and does not apply
        self::assertNotEmpty($this->checkedTransitions[$this->adapter] ?? [], sprintf('%s was not asked about a single transition', $this->adapter));
        self::assertSame([], $applied, sprintf('every transition should have gone through %s', $this->adapter));
        self::assertSame([], $checked, sprintf('every transition should have been checked by %s', $this->adapter));
    }

    /**
     * Applies the transition the way Sylius does, and flushes afterwards the way whatever applies a transition does
     */
    protected function apply(object $subject, string $graph, string $transition): void
    {
        $this->stateMachine()->apply($subject, $graph, $transition);

        $this->manager->flush();
    }

    /**
     * The state machine Sylius and the plugin apply every transition through
     */
    protected function stateMachine(): StateMachineInterface
    {
        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');

        return $stateMachine;
    }

    protected function placeOrder(Order $order): void
    {
        $this->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
    }

    /**
     * A cart in the state the checkout leaves it in right before the customer places the order. Without a payment
     * selected for the rest (selectPayment()) the payment step was skipped, as it is when the gift cards cover
     * everything
     */
    protected function createCart(): Order
    {
        $customer = new Customer();
        $customer->setEmail('buyer@example.com');
        $this->manager->persist($customer);

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCustomer($customer);
        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SKIPPED);

        $this->manager->persist($order);

        return $order;
    }

    /**
     * A card the way add to cart creates it for a unit: disabled, holding the amount the customer asked for
     */
    protected function createPendingGiftCard(string $code, int $amount = 5000): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get(GiftCardFactoryInterface::class);

        $giftCard = $factory->createForChannel($this->getChannel());
        $giftCard->setCode($code);
        $giftCard->setInitialAmount($amount);
        $giftCard->setAmount($amount);
        $giftCard->disable();

        $this->manager->persist($giftCard);

        return $giftCard;
    }

    /**
     * The customer picks the cash payment method for what the gift cards do not cover, in a payment the checkout
     * payment processor sized to that amount
     */
    protected function selectPayment(Order $order, int $amount): PaymentInterface
    {
        /** @var PaymentFactoryInterface<PaymentInterface> $paymentFactory */
        $paymentFactory = self::getContainer()->get('sylius.factory.payment');

        $payment = $paymentFactory->createWithAmountAndCurrencyCode($amount, 'USD');
        self::assertInstanceOf(PaymentInterface::class, $payment);
        $payment->setMethod($this->createCashPaymentMethod());
        $order->addPayment($payment);

        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);

        return $payment;
    }

    /**
     * The shipment the checkout gives an order with something to ship, with the shipping method the customer picked.
     * An order is only open for cancelling until it is fulfilled, and one with nothing to ship is fulfilled as soon as
     * it is paid, so a shipment still waiting keeps a paid order open
     */
    protected function createShipment(): ShipmentInterface
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

    /**
     * @return list<PaymentInterface>
     */
    protected function giftCardPayments(Order $order): array
    {
        /** @var string $paymentMethodCode */
        $paymentMethodCode = self::getContainer()->getParameter('setono_sylius_gift_card.redemption.payment_method_code');

        $payments = [];
        foreach ($order->getPayments() as $payment) {
            self::assertInstanceOf(PaymentInterface::class, $payment);

            if ($payment->getMethod()?->getCode() === $paymentMethodCode) {
                $payments[] = $payment;
            }
        }

        return $payments;
    }

    /**
     * Forgets everything the entity manager holds and loads the order again, the way the next request finds it
     */
    protected function reload(Order $order): Order
    {
        $id = $order->getId();
        $this->manager->clear();

        $reloaded = $this->manager->find(Order::class, $id);
        self::assertInstanceOf(Order::class, $reloaded);

        return $reloaded;
    }

    /**
     * The balance as it is in the database, rather than as the entity manager holds it
     */
    protected function persistedBalanceOf(GiftCardInterface $giftCard): int
    {
        $amount = $this->manager
            ->createQuery(sprintf('SELECT g.amount FROM %s g WHERE g.id = :id', $this->manager->getClassMetadata(GiftCardInterface::class)->getName()))
            ->setParameter('id', $giftCard->getId())
            ->getSingleScalarResult()
        ;

        return (int) $amount;
    }

    /**
     * The card's ledger as it is in the database, oldest row first
     *
     * @return list<array{type: string, amount: int, idempotencyKey: string|null, order: int|null, payment: int|null, createdBy: string|null}>
     */
    protected function persistedLedgerOf(GiftCardInterface $giftCard): array
    {
        /** @var list<array{type: string, amount: int, idempotencyKey: string|null, orderId: int|string|null, paymentId: int|string|null, createdBy: string|null}> $rows */
        $rows = $this->manager
            ->createQuery(sprintf(
                'SELECT t.type, t.amount, t.idempotencyKey, IDENTITY(t.order) AS orderId, IDENTITY(t.payment) AS paymentId, t.createdBy FROM %s t WHERE t.giftCard = :giftCard ORDER BY t.id',
                $this->manager->getClassMetadata(GiftCardTransactionInterface::class)->getName(),
            ))
            ->setParameter('giftCard', $giftCard->getId())
            ->getArrayResult()
        ;

        return array_map(static fn (array $row): array => [
            'type' => $row['type'],
            'amount' => $row['amount'],
            'idempotencyKey' => $row['idempotencyKey'],
            'order' => null === $row['orderId'] ? null : (int) $row['orderId'],
            'payment' => null === $row['paymentId'] ? null : (int) $row['paymentId'],
            'createdBy' => $row['createdBy'],
        ], $rows);
    }
}
