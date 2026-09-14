<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Doctrine\ORM\OptimisticLockException;
use Setono\SyliusGiftCardPlugin\EventSubscriber\GiftCardRaceConditionSubscriber;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceUpdateHandlerInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Resource\Metadata\RegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Workflow\WorkflowInterface;
use Webmozart\Assert\Assert;

/**
 * GiftCard is versioned, so two orders redeeming the same card at the same moment race on its version column:
 * the loser's UPDATE matches no row and Doctrine raises an OptimisticLockException out of the flush. This
 * pins down that behaviour — the exception GiftCardRaceConditionSubscriber turns into a message is real, and
 * nothing of the losing redemption is written
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
     * Sylius' resource update handler turns the lost race into a RaceConditionException, which the resource
     * controller swallows: it redirects to the referer, and the shop's checkout complete route disables
     * flashes, so the customer is bounced back to checkout without a word. CheckoutRaceConditionUpdateHandler
     * lets the Doctrine exception through instead, which is what GiftCardRaceConditionSubscriber acts on
     *
     * @test
     */
    public function it_lets_the_lost_race_out_of_the_resource_update_handler_at_checkout_completion(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $order = $this->createOrderWithAppliedGiftCard($giftCard, 5000);

        $this->simulateConcurrentRedemption($giftCard);

        $this->orderWorkflow()->apply($order, OrderTransitions::TRANSITION_CREATE);

        $this->expectException(OptimisticLockException::class);

        $this->updateHandler()->handle(
            $order,
            $this->requestConfiguration(GiftCardRaceConditionSubscriber::CHECKOUT_COMPLETE_ROUTE),
            $this->manager,
        );
    }

    private function updateHandler(): ResourceUpdateHandlerInterface
    {
        /** @var ResourceUpdateHandlerInterface $handler */
        $handler = self::getContainer()->get(ResourceUpdateHandlerInterface::class);

        return $handler;
    }

    /**
     * The request has to be the current one, because that is how the handler knows which route it is serving
     */
    private function requestConfiguration(string $route): RequestConfiguration
    {
        $container = self::getContainer();

        $request = new Request();
        $request->attributes->set('_route', $route);

        /** @var RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

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
