<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\OptimisticLockException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceUpdateHandlerInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Resource\Exception\RaceConditionException;
use Sylius\Resource\Metadata\RegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Workflow\WorkflowInterface;
use Webmozart\Assert\Assert;

/**
 * GiftCard is versioned, so two orders redeeming the same card at the same moment race on its version column:
 * the loser's UPDATE matches no row and Doctrine raises an OptimisticLockException out of the flush. This
 * pins down that behaviour — the exception GiftCardRaceConditionSubscriber turns into a message is real, and
 * nothing of the losing redemption is written — and that at checkout completion the race is always lost that way,
 * never in a deadlock or a driver error Sylius does not handle
 */
final class GiftCardRedemptionRaceTest extends GiftCardFunctionalTestCase
{
    /** @test */
    public function it_fails_the_flush_when_the_gift_card_changed_while_the_order_was_placed(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $order = $this->createOrderWithAppliedGiftCard($giftCard, 5000);

        // another order redeems the same card in between: its balance is gone and its version has moved on,
        // while the card this order holds is still the one loaded before that happened
        $this->simulateConcurrentRedemption($giftCard);

        $this->orderWorkflow()->apply($order, OrderTransitions::TRANSITION_CREATE);

        self::assertSame(0, $giftCard->getAmount(), 'the redemption should have been staged in memory');

        try {
            $this->manager->flush();

            self::fail('the stale version should have made the flush fail');
        } catch (OptimisticLockException $e) {
            self::assertSame($giftCard, $e->getEntity());
        }

        // the flush rolled back, so the losing redemption left no trace
        $transactions = $this->manager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM setono_sylius_gift_card__gift_card_transaction',
        );
        Assert::numeric($transactions);
        self::assertSame(0, (int) $transactions);
    }

    /**
     * At checkout completion the flush happens inside Sylius' resource update handler, which turns the lost race
     * into a RaceConditionException. The resource controller handles that one itself by redirecting to the
     * referer, so the customer never sees an error page. The plugin leaves that handling alone; this pins it,
     * because it is what makes a concurrent redemption harmless on a stock shop
     *
     * @test
     */
    public function it_reaches_sylius_as_a_race_condition_at_checkout_completion(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $order = $this->createOrderWithAppliedGiftCard($giftCard, 5000);

        $this->simulateConcurrentRedemption($giftCard);

        $this->orderWorkflow()->apply($order, OrderTransitions::TRANSITION_CREATE);

        try {
            $this->updateHandler()->handle(
                $order,
                $this->requestConfiguration(),
                $this->manager,
            );

            self::fail('The lost race went unnoticed');
        } catch (RaceConditionException $e) {
            self::assertInstanceOf(OptimisticLockException::class, $e->getPrevious());
        }
    }

    /**
     * The race has to be lost on the version, not in a deadlock. A competing checkout's flush starts by inserting
     * its ledger row, whose foreign key takes a shared lock on the card's row; had both orders taken that before
     * either got to its versioned update, neither could have the exclusive lock the update needs. So by the time
     * this order starts writing, inside the transaction Sylius completes the checkout in, the card has to be held
     * exclusively already: the competitor then waits for this order to commit instead of taking its shared lock
     *
     * @test
     */
    public function it_holds_the_gift_card_before_writing_anything_at_checkout_completion(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $order = $this->createOrderWithAppliedGiftCard($giftCard, 5000);
        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SKIPPED);

        $giftCardId = $giftCard->getId();
        self::assertIsInt($giftCardId);

        $competitor = DriverManager::getConnection($this->manager->getConnection()->getParams());
        // the shortest wait MySQL allows, so a card that is held fails the competitor's insert quickly
        $competitor->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        $probe = new class($competitor, $giftCardId) {
            public ?bool $competitorHadToWait = null;

            public function __construct(private readonly Connection $competitor, private readonly int $giftCardId)
            {
            }

            /**
             * Runs when this order's ledger row is about to be written, before any of the flush's statements
             */
            public function onFlush(OnFlushEventArgs $args): void
            {
                if (null !== $this->competitorHadToWait || !self::writesLedgerRow($args)) {
                    return;
                }

                $this->competitor->beginTransaction();

                try {
                    $this->competitor->insert('setono_sylius_gift_card__gift_card_transaction', [
                        'gift_card_id' => $this->giftCardId,
                        'amount' => -5000,
                        'type' => GiftCardTransactionInterface::TYPE_REDEEM,
                        'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ]);

                    $this->competitorHadToWait = false;
                } catch (LockWaitTimeoutException) {
                    $this->competitorHadToWait = true;
                } finally {
                    $this->competitor->rollBack();
                }
            }

            private static function writesLedgerRow(OnFlushEventArgs $args): bool
            {
                foreach ($args->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
                    if ($entity instanceof GiftCardTransactionInterface) {
                        return true;
                    }
                }

                return false;
            }
        };
        $this->manager->getEventManager()->addEventListener([Events::onFlush], $probe);

        try {
            $this->updateHandler()->handle($order, $this->completeCheckout(), $this->manager);
        } finally {
            $competitor->close();
        }

        self::assertTrue($probe->competitorHadToWait, 'A competing redemption could take a shared lock on the gift card while this order was placed');
        self::assertSame(OrderCheckoutStates::STATE_COMPLETED, $order->getCheckoutState());
        self::assertSame(0, $giftCard->getAmount());
    }

    /**
     * The other order can also commit after this one has started reading, inside its transaction, but before it
     * gets to the card. MySQL then grants the lock straight away and the versioned update matches no row; MariaDB
     * from 11.6.2 refuses the lock itself, because the card changed after the transaction's snapshot was taken.
     * Either way what reaches Sylius has to be the OptimisticLockException it turns into a redirect, not a driver
     * error that ends on an error page
     *
     * @test
     */
    public function it_reaches_sylius_as_a_race_condition_when_the_gift_card_changes_during_checkout_completion(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $order = $this->createOrderWithAppliedGiftCard($giftCard, 5000);

        $giftCardId = $giftCard->getId();
        $orderId = $order->getId();

        // load the cart the way the checkout request does: the order up front, what it refers to lazily, inside the
        // transaction Sylius completes the checkout in
        $this->manager->clear();
        $order = $this->manager->find(Order::class, $orderId);
        self::assertInstanceOf(Order::class, $order);
        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SKIPPED);

        $competitor = DriverManager::getConnection($this->manager->getConnection()->getParams());

        $otherOrder = new class($competitor, $giftCardId) {
            public bool $committed = false;

            public function __construct(private readonly Connection $competitor, private readonly mixed $giftCardId)
            {
            }

            /**
             * Runs once this order has read something inside its transaction
             */
            public function postLoad(PostLoadEventArgs $args): void
            {
                if ($this->committed || !$args->getObjectManager()->getConnection()->isTransactionActive()) {
                    return;
                }

                $this->competitor->executeStatement(
                    'UPDATE setono_sylius_gift_card__gift_card SET amount = 0, version = version + 1 WHERE id = ?',
                    [$this->giftCardId],
                );

                $this->committed = true;
            }
        };
        $this->manager->getEventManager()->addEventListener([Events::postLoad], $otherOrder);

        try {
            $this->updateHandler()->handle($order, $this->completeCheckout(), $this->manager);

            self::fail('The lost race went unnoticed');
        } catch (RaceConditionException $e) {
            $previous = $e->getPrevious();
            self::assertInstanceOf(OptimisticLockException::class, $previous);
            self::assertInstanceOf(GiftCardInterface::class, $previous->getEntity());
        } finally {
            $competitor->close();
        }

        self::assertTrue($otherOrder->committed, 'The other order never got to redeem the card');
    }

    private function completeCheckout(): RequestConfiguration
    {
        return $this->requestConfiguration([
            'state_machine' => [
                'graph' => OrderCheckoutTransitions::GRAPH,
                'transition' => OrderCheckoutTransitions::TRANSITION_COMPLETE,
            ],
        ]);
    }

    private function updateHandler(): ResourceUpdateHandlerInterface
    {
        /** @var ResourceUpdateHandlerInterface $handler */
        $handler = self::getContainer()->get(ResourceUpdateHandlerInterface::class);

        return $handler;
    }

    /**
     * @param array<string, mixed> $parameters the route's _sylius parameters
     */
    private function requestConfiguration(array $parameters = []): RequestConfiguration
    {
        $container = self::getContainer();

        $request = new Request();
        $request->attributes->set('_sylius', $parameters);

        /** @var RegistryInterface $registry */
        $registry = $container->get('sylius.resource_registry');

        /** @var RequestConfigurationFactoryInterface $factory */
        $factory = $container->get('sylius.resource_controller.request_configuration_factory');

        return $factory->create($registry->get('sylius.order'), $request);
    }

    private function orderWorkflow(): WorkflowInterface
    {
        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('state_machine.' . OrderTransitions::GRAPH);

        return $workflow;
    }

    private function createGiftCard(int $amount): GiftCardInterface
    {
        /** @var \Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createNew();
        $giftCard->setCode('RACETEST00000001');
        $giftCard->setChannel($this->getChannel());
        $giftCard->setCurrencyCode('USD');
        $giftCard->setInitialAmount($amount);
        $giftCard->setAmount($amount);
        $giftCard->enable();

        $this->manager->persist($giftCard);

        return $giftCard;
    }

    /**
     * The card has to cover something, so the order needs an item that is not itself a gift card
     */
    private function createOrderWithAppliedGiftCard(GiftCardInterface $giftCard, int $total): Order
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('RACE_TEST_PRODUCT');
        $product->setName('Mug');
        $product->setSlug('mug');
        $this->manager->persist($product);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('RACE_TEST_VARIANT');
        $variant->setProduct($product);
        $this->manager->persist($variant);

        // Sylius' own listeners on the create transition walk the order's customer, so it has to be there
        $customer = new Customer();
        $customer->setEmail('race@example.com');
        $this->manager->persist($customer);

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCustomer($customer);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice($total);
        $order->addItem($item);

        new OrderItemUnit($item);

        $order->addGiftCard($giftCard);

        $this->manager->persist($order);
        $this->manager->flush();

        return $order;
    }

    /**
     * A DQL update writes past the identity map, which is exactly what a competing request would do
     */
    private function simulateConcurrentRedemption(GiftCardInterface $giftCard): void
    {
        $this->manager
            ->createQuery(sprintf('UPDATE %s g SET g.amount = 0, g.version = g.version + 1 WHERE g.id = :id', $giftCard::class))
            ->setParameter('id', $giftCard->getId())
            ->execute()
        ;
    }
}
