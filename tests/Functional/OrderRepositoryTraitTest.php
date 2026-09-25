<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\OrderRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\OrderCheckoutStates;

/**
 * Applications add these queries to their order repository by using the trait, as the test application does, so
 * they are run through that repository against the real database
 */
final class OrderRepositoryTraitTest extends GiftCardFunctionalTestCase
{
    private OrderRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var OrderRepositoryInterface $repository */
        $repository = self::getContainer()->get('sylius.repository.order');
        $this->repository = $repository;
    }

    /** @test */
    public function it_finds_the_latest_order_of_a_customer(): void
    {
        $customer = $this->createCustomer('latest@example.com');
        $otherCustomer = $this->createCustomer('other@example.com');

        $this->createOrder('OLDEST', $customer, createdAt: '-3 days');
        $this->createOrder('LATEST', $customer, createdAt: '-1 day');
        $this->createOrder('MIDDLE', $customer, createdAt: '-2 days');
        // newer than all of the above, but somebody else's
        $this->createOrder('SOMEBODYELSES', $otherCustomer, createdAt: '-1 hour');
        $this->manager->clear();

        self::assertSame('LATEST', $this->repository->findLatestByCustomer($this->reload($customer))?->getNumber());
    }

    /** @test */
    public function it_finds_no_latest_order_for_a_customer_without_orders(): void
    {
        $this->createOrder('SOMEBODYELSES', $this->createCustomer('other@example.com'));

        self::assertNull($this->repository->findLatestByCustomer($this->createCustomer('new@example.com')));
    }

    /**
     * These builders look at the gift cards applied to (redeemed on) an order, not at the ones bought with it
     *
     * @test
     */
    public function it_builds_a_query_for_the_orders_a_gift_card_was_applied_to(): void
    {
        $giftCard = $this->createGiftCard('APPLIEDCARD');
        $otherGiftCard = $this->createGiftCard('OTHERCARD');

        $this->createOrder('CART', giftCards: [$giftCard], checkoutState: OrderCheckoutStates::STATE_CART);
        $this->createOrder('COMPLETED', giftCards: [$giftCard, $otherGiftCard]);
        $this->createOrder('OTHER', giftCards: [$otherGiftCard]);
        $this->createOrder('NONE');

        self::assertEqualsCanonicalizing(
            ['CART', 'COMPLETED'],
            $this->numbersOf($this->repository->createQueryBuilderByGiftCard((string) $giftCard->getId())),
        );
        self::assertSame([], $this->numbersOf($this->repository->createQueryBuilderByGiftCard('0')));
    }

    /** @test */
    public function it_builds_a_query_for_the_completed_orders_a_gift_card_was_applied_to(): void
    {
        $giftCard = $this->createGiftCard('APPLIEDCARD');

        $this->createOrder('CART', giftCards: [$giftCard], checkoutState: OrderCheckoutStates::STATE_CART);
        $this->createOrder('PAYMENT_SELECTED', giftCards: [$giftCard], checkoutState: OrderCheckoutStates::STATE_PAYMENT_SELECTED);
        $this->createOrder('COMPLETED', giftCards: [$giftCard]);
        $this->createOrder('COMPLETED_WITHOUT_CARD');

        self::assertSame(
            ['COMPLETED'],
            $this->numbersOf($this->repository->createCompletedQueryBuilderByGiftCard((string) $giftCard->getId())),
        );
    }

    /**
     * @param list<GiftCardInterface> $giftCards
     */
    private function createOrder(
        string $number,
        ?CustomerInterface $customer = null,
        string $createdAt = 'now',
        array $giftCards = [],
        string $checkoutState = OrderCheckoutStates::STATE_COMPLETED,
    ): Order {
        $order = new Order();
        $order->setNumber($number);
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCustomer($customer);
        $order->setCheckoutState($checkoutState);
        // the timestampable listener leaves a date that is already set alone
        $order->setCreatedAt(new \DateTime($createdAt));

        foreach ($giftCards as $giftCard) {
            $order->addGiftCard($giftCard);
        }

        $this->manager->persist($order);
        $this->manager->flush();

        return $order;
    }

    private function createCustomer(string $email): CustomerInterface
    {
        $customer = new Customer();
        $customer->setEmail($email);

        $this->manager->persist($customer);
        $this->manager->flush();

        return $customer;
    }

    private function createGiftCard(string $code): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createForChannel($this->getChannel());
        $giftCard->setCode($code);
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);

        $this->manager->persist($giftCard);
        $this->manager->flush();

        return $giftCard;
    }

    private function reload(CustomerInterface $customer): CustomerInterface
    {
        $reloaded = $this->manager->find(Customer::class, $customer->getId());
        self::assertInstanceOf(CustomerInterface::class, $reloaded);

        return $reloaded;
    }

    /**
     * @return list<string>
     */
    private function numbersOf(QueryBuilder $queryBuilder): array
    {
        $orders = $queryBuilder->getQuery()->getResult();
        self::assertIsArray($orders);

        $numbers = [];
        foreach ($orders as $order) {
            self::assertInstanceOf(Order::class, $order);
            $numbers[] = (string) $order->getNumber();
        }

        return $numbers;
    }
}
