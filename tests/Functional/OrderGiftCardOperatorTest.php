<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperator;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Resolver\GiftCardExpiryResolver;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * The operator drives the life of the gift cards bought on an order: reconcile when checkout completes, enable when
 * the order is paid, disable when it is cancelled. It is called directly here, against the real database, so each
 * step is checked by what it leaves behind rather than through the state machine that calls it
 */
final class OrderGiftCardOperatorTest extends GiftCardFunctionalTestCase
{
    private OrderGiftCardOperatorInterface $operator;

    private GiftCardRepositoryInterface $giftCardRepository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var OrderGiftCardOperatorInterface $operator */
        $operator = self::getContainer()->get(OrderGiftCardOperatorInterface::class);
        $this->operator = $operator;

        /** @var GiftCardRepositoryInterface $giftCardRepository */
        $giftCardRepository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        $this->giftCardRepository = $giftCardRepository;
    }

    /**
     * Add to cart creates one card for the unit it adds. Raising the quantity in the cart adds units without cards,
     * so reconcile creates theirs, made out like the card the customer did configure: same design, same message
     *
     * @test
     */
    public function reconcile_creates_the_gift_cards_of_the_units_added_by_raising_the_quantity(): void
    {
        $design = $this->createDesign();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        $template = $this->createPendingGiftCard('TEMPLATE00000001');
        $template->setDesign($design);
        $template->setCustomMessage('Happy birthday!');
        $item = $this->addLine($order, $this->createGiftCardVariant('VIRTUAL', shippingRequired: false), 3, [$template]);

        $this->operator->reconcile($order);
        $this->manager->clear();

        $giftCards = $this->giftCardsOfItem($item);
        self::assertCount(3, $giftCards, 'every unit should now carry a gift card');

        $codes = array_map(static fn (GiftCardInterface $giftCard): string => (string) $giftCard->getCode(), $giftCards);
        self::assertContains('TEMPLATE00000001', $codes, 'the card the customer configured is kept');
        self::assertCount(3, array_unique($codes));

        foreach ($giftCards as $giftCard) {
            self::assertTrue($giftCard->isPending(), 'a card is not enabled before the order is paid');
            self::assertSame('TEST_CHANNEL', $giftCard->getChannel()?->getCode());
            self::assertSame('USD', $giftCard->getCurrencyCode());
            self::assertSame('classic', $giftCard->getDesign()?->getCode());
            self::assertSame('Happy birthday!', $giftCard->getCustomMessage());
            self::assertSame(GiftCardDeliveryType::Virtual, $giftCard->getDeliveryType());
            self::assertSame('buyer@example.com', $giftCard->getCustomer()?->getEmail());
            self::assertSame(5000, $giftCard->getAmount());
            self::assertSame(5000, $giftCard->getInitialAmount());
        }
    }

    /**
     * Whether a card is shipped follows from the variant the customer bought, so the cards reconcile creates for a
     * shippable variant are physical. Without a card to copy from they get no design and no message
     *
     * @test
     */
    public function reconcile_makes_the_cards_of_a_shippable_variant_physical(): void
    {
        $order = $this->createOrder($this->createCustomer());
        $item = $this->addLine($order, $this->createGiftCardVariant('PHYSICAL', shippingRequired: true), 2);

        $this->operator->reconcile($order);
        $this->manager->clear();

        $giftCards = $this->giftCardsOfItem($item);
        self::assertCount(2, $giftCards);
        foreach ($giftCards as $giftCard) {
            self::assertSame(GiftCardDeliveryType::Physical, $giftCard->getDeliveryType());
            self::assertNull($giftCard->getDesign());
            self::assertNull($giftCard->getCustomMessage());
        }
    }

    /**
     * The balance of a card is what the customer paid for its unit, not the amount they typed in when adding it to
     * the cart: a promotion that took 10.00 off one unit leaves that card with 40.00
     *
     * @test
     */
    public function reconcile_gives_each_card_the_amount_paid_for_its_unit(): void
    {
        $order = $this->createOrder($this->createCustomer());
        $item = $this->addLine(
            $order,
            $this->createGiftCardVariant('VIRTUAL', shippingRequired: false),
            2,
            [$this->createPendingGiftCard('DISCOUNTED000001'), $this->createPendingGiftCard('FULLPRICE0000001')],
        );

        $discountedUnit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $discountedUnit);
        $promotion = new Adjustment();
        $promotion->setType(AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT);
        $promotion->setLabel('10.00 off');
        $promotion->setAmount(-1000);
        $discountedUnit->addAdjustment($promotion);
        $this->manager->flush();

        $this->operator->reconcile($order);
        $this->manager->clear();

        $discounted = $this->findGiftCard('DISCOUNTED000001');
        self::assertSame(4000, $discounted->getAmount());
        self::assertSame(4000, $discounted->getInitialAmount());

        $fullPrice = $this->findGiftCard('FULLPRICE0000001');
        self::assertSame(5000, $fullPrice->getAmount());
        self::assertSame(5000, $fullPrice->getInitialAmount());
    }

    /**
     * A card's validity counts from the purchase. The card of the unit the customer put in the cart was created, and
     * given an expiry, when it went into the cart, possibly weeks before the order is placed; the cards of the units
     * added by raising the quantity are only created now. Both are bought today, so both expire the configured period
     * from today, at the end of that day
     *
     * @test
     */
    public function reconcile_counts_the_validity_of_every_card_from_the_purchase(): void
    {
        $template = $this->createPendingGiftCard('ADDEDMONTHAGO001');
        $expiresAt = $template->getExpiresAt();
        self::assertNotNull($expiresAt, 'the test application configures a validity period');
        // as if the card went into the cart a month ago
        $template->setExpiresAt(\DateTimeImmutable::createFromInterface($expiresAt)->modify('-1 month'));

        $order = $this->createOrder($this->createCustomer());
        $item = $this->addLine($order, $this->createGiftCardVariant('VIRTUAL', shippingRequired: false), 2, [$template]);

        $period = self::getContainer()->getParameter('setono_sylius_gift_card.default_validity_period');
        self::assertIsString($period);

        $before = (new \DateTimeImmutable('+' . $period))->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        $this->operator->reconcile($order);
        $after = (new \DateTimeImmutable('+' . $period))->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        $this->manager->clear();

        $expiries = array_map(
            static fn (GiftCardInterface $giftCard): ?string => $giftCard->getExpiresAt()?->format('Y-m-d H:i:s'),
            $this->giftCardsOfItem($item),
        );
        self::assertCount(2, $expiries);
        self::assertSame($expiries[0], $expiries[1], 'the cards bought on one order expire at the same moment');
        // the day is read on both sides of the call, so a test running across midnight still passes
        self::assertContains($expiries[0], [$before, $after]);
    }

    /**
     * With default_validity_period set to null a bought card never expires. The setting in force when the order is
     * placed is the one that counts, so the card add to cart gave an expiry while a period was configured loses it
     *
     * @test
     */
    public function reconcile_leaves_every_card_without_an_expiry_when_no_validity_period_is_configured(): void
    {
        $container = self::getContainer();

        /** @var GiftCardFactoryInterface $factory */
        $factory = $container->get('setono_sylius_gift_card.factory.gift_card');

        /** @var ManagerRegistry $managerRegistry */
        $managerRegistry = $container->get('doctrine');

        /** @var GiftCardEmailManagerInterface $emailManager */
        $emailManager = $container->get(GiftCardEmailManagerInterface::class);

        /** @var GiftCardBalanceOperatorInterface $balanceOperator */
        $balanceOperator = $container->get(GiftCardBalanceOperatorInterface::class);

        $operator = new OrderGiftCardOperator($factory, $managerRegistry, $emailManager, $balanceOperator, new GiftCardExpiryResolver(null));

        $template = $this->createPendingGiftCard('ADDEDWITHPERIOD1');
        self::assertNotNull($template->getExpiresAt(), 'the test application configures a validity period');

        $order = $this->createOrder($this->createCustomer());
        $item = $this->addLine($order, $this->createGiftCardVariant('VIRTUAL', shippingRequired: false), 2, [$template]);

        $operator->reconcile($order);
        $this->manager->clear();

        $giftCards = $this->giftCardsOfItem($item);
        self::assertCount(2, $giftCards);
        foreach ($giftCards as $giftCard) {
            self::assertNull($giftCard->getExpiresAt());
        }
    }

    /** @test */
    public function reconcile_leaves_the_units_of_other_products_alone(): void
    {
        $order = $this->createOrder($this->createCustomer());
        $mug = $this->addLine($order, $this->createVariant('MUG', giftCard: false), 2);
        $this->addLine($order, $this->createGiftCardVariant('VIRTUAL', shippingRequired: false), 1);

        $this->operator->reconcile($order);
        $this->manager->clear();

        self::assertSame([], $this->giftCardsOfItem($mug));
        self::assertCount(1, $this->giftCardRepository->findAll());
    }

    /**
     * Paying the order is the moment the cards become money the shop owes, which is also when their issuance is
     * recorded: before that the amounts could still change
     *
     * @test
     */
    public function enable_enables_the_cards_bought_on_the_order_and_records_their_issuance(): void
    {
        $order = $this->createOrder($this->createCustomer());
        $this->addLine(
            $order,
            $this->createGiftCardVariant('VIRTUAL', shippingRequired: false),
            2,
            [$this->createPendingGiftCard('ENABLED000000001', 5000), $this->createPendingGiftCard('ENABLED000000002', 2500)],
        );
        $this->createPendingGiftCard('ANOTHERORDERS001');
        $this->manager->flush();

        $this->operator->enable($order);
        $this->manager->clear();

        foreach (['ENABLED000000001' => 5000, 'ENABLED000000002' => 2500] as $code => $amount) {
            $giftCard = $this->findGiftCard($code);
            self::assertTrue($giftCard->isEnabled());
            self::assertSame([[GiftCardTransactionInterface::TYPE_ISSUE, $amount]], $this->ledgerOf($giftCard));
        }

        self::assertFalse($this->findGiftCard('ANOTHERORDERS001')->isEnabled(), 'only the cards bought on the order are enabled');
    }

    /**
     * The pay callback can fire more than once for the same order, and must not record the issuance twice
     *
     * @test
     */
    public function enable_records_the_issuance_only_once(): void
    {
        $order = $this->createOrder($this->createCustomer());
        $this->addLine($order, $this->createGiftCardVariant('VIRTUAL', shippingRequired: false), 1, [$this->createPendingGiftCard('ENABLEDTWICE0001')]);

        $this->operator->enable($order);
        $this->operator->enable($order);
        $this->manager->clear();

        self::assertSame([[GiftCardTransactionInterface::TYPE_ISSUE, 5000]], $this->ledgerOf($this->findGiftCard('ENABLEDTWICE0001')));
    }

    /**
     * Cancelling the order voids the cards bought on it, but not the cards that were used to pay for it: those belong
     * to somebody who is owed their balance back
     *
     * @test
     */
    public function disable_disables_the_cards_bought_on_the_order(): void
    {
        $order = $this->createOrder($this->createCustomer());
        $this->addLine(
            $order,
            $this->createGiftCardVariant('VIRTUAL', shippingRequired: false),
            2,
            [$this->createPendingGiftCard('CANCELLED0000001'), $this->createPendingGiftCard('CANCELLED0000002')],
        );
        $this->operator->enable($order);

        $spent = $this->createPendingGiftCard('SPENTONORDER0001');
        $spent->enable();
        $order->addGiftCard($spent);
        $this->manager->flush();

        $this->operator->disable($order);
        $this->manager->clear();

        foreach (['CANCELLED0000001', 'CANCELLED0000002'] as $code) {
            $giftCard = $this->findGiftCard($code);
            self::assertFalse($giftCard->isEnabled());
            self::assertCount(1, $giftCard->getTransactions(), 'the ledger stays as it was');
        }

        self::assertTrue($this->findGiftCard('SPENTONORDER0001')->isEnabled());
    }

    private function createOrder(CustomerInterface $customer): Order
    {
        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCustomer($customer);

        $this->manager->persist($order);
        $this->manager->flush();

        return $order;
    }

    /**
     * Adds a line of $quantity units at 50.00 each, the first units carrying the given (pending) gift cards the way
     * add to cart leaves them
     *
     * @param list<GiftCardInterface> $giftCards
     */
    private function addLine(Order $order, ProductVariantInterface $variant, int $quantity, array $giftCards = []): OrderItem
    {
        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(5000);
        $order->addItem($item);

        for ($i = 0; $i < $quantity; ++$i) {
            $unit = new OrderItemUnit($item);

            $giftCard = $giftCards[$i] ?? null;
            if (null !== $giftCard) {
                $unit->setGiftCard($giftCard);
            }
        }

        $this->manager->flush();

        return $item;
    }

    private function createGiftCardVariant(string $code, bool $shippingRequired): ProductVariantInterface
    {
        $variant = $this->createVariant($code, giftCard: true);
        $variant->setShippingRequired($shippingRequired);
        $this->manager->flush();

        return $variant;
    }

    private function createVariant(string $code, bool $giftCard): ProductVariantInterface
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode($code);
        $product->setName($code);
        $product->setSlug(strtolower($code));
        $product->setGiftCard($giftCard);
        $this->manager->persist($product);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode($code . '_VARIANT');
        $variant->setProduct($product);
        $this->manager->persist($variant);
        $this->manager->flush();

        return $variant;
    }

    /**
     * A card the way add to cart creates it: disabled, holding the amount the customer asked for
     */
    private function createPendingGiftCard(string $code, int $amount = 5000): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createForChannel($this->getChannel());
        $giftCard->setCode($code);
        $giftCard->setInitialAmount($amount);
        $giftCard->setAmount($amount);
        $giftCard->disable();

        $this->manager->persist($giftCard);

        return $giftCard;
    }

    private function createDesign(): GiftCardDesignInterface
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $factory->createNew();
        $design->setCode('classic');
        $design->setName('Classic');
        $design->addChannel($this->getChannel());

        $this->manager->persist($design);
        $this->manager->flush();

        return $design;
    }

    private function createCustomer(): CustomerInterface
    {
        $customer = new Customer();
        $customer->setEmail('buyer@example.com');

        $this->manager->persist($customer);
        $this->manager->flush();

        return $customer;
    }

    /**
     * The gift cards of the item's units, reloaded from the database, in unit order
     *
     * @return list<GiftCardInterface>
     */
    private function giftCardsOfItem(OrderItem $item): array
    {
        $reloaded = $this->manager->find(OrderItem::class, $item->getId());
        self::assertInstanceOf(OrderItem::class, $reloaded);

        $giftCards = [];
        foreach ($reloaded->getUnits() as $unit) {
            self::assertInstanceOf(OrderItemUnit::class, $unit);

            $giftCard = $unit->getGiftCard();
            if (null !== $giftCard) {
                $giftCards[] = $giftCard;
            }
        }

        return $giftCards;
    }

    private function findGiftCard(string $code): GiftCardInterface
    {
        $giftCard = $this->giftCardRepository->findOneByCode($code);
        self::assertInstanceOf(GiftCardInterface::class, $giftCard);

        return $giftCard;
    }

    /**
     * @return list<array{string, int}>
     */
    private function ledgerOf(GiftCardInterface $giftCard): array
    {
        return array_values(array_map(
            static fn (GiftCardTransactionInterface $transaction): array => [$transaction->getType(), $transaction->getAmount()],
            $giftCard->getTransactions()->toArray(),
        ));
    }
}
