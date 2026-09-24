<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Doctrine\Persistence\Proxy;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Channel\Factory\ChannelFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Currency\Model\Currency;

/**
 * Every query the plugin runs against the gift card table, run against the real database: the admin list, the
 * lookups behind code entry and the order lifecycle, and the outstanding balance report
 */
final class GiftCardRepositoryTest extends GiftCardFunctionalTestCase
{
    private GiftCardRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        $this->repository = $repository;
    }

    /**
     * A card waiting in somebody's cart is not a liability yet and most of them are never bought, so the admin list
     * leaves them out. Everything else stays: cards issued in the admin (enabled or not), cards that were paid for,
     * and a paid card that was disabled afterwards, which the ledger rows tell apart from a pending one
     *
     * @test
     */
    public function its_list_hides_the_pending_gift_cards_in_carts(): void
    {
        $this->createGiftCard('ADMINENABLED');
        $this->createGiftCard('ADMINDISABLED', enabled: false);
        $this->createGiftCardBoughtOnAnOrder('PENDING', enabled: false);
        $this->createGiftCardBoughtOnAnOrder('PAID', enabled: true);

        $cancelled = $this->createGiftCardBoughtOnAnOrder('CANCELLED', enabled: true);
        $this->balanceOperator()->issue($cancelled);
        $cancelled->disable();
        $this->manager->flush();
        $this->manager->clear();

        self::assertEqualsCanonicalizing(
            ['ADMINENABLED', 'ADMINDISABLED', 'PAID', 'CANCELLED'],
            $this->codesOf($this->repository->createListQueryBuilder()->getQuery()->getResult()),
        );
    }

    /**
     * The grid renders the customer of every row, so the list fetches them in the same query instead of lazy
     * loading one customer per row. It is a left join, so cards without a customer are listed too
     *
     * @test
     */
    public function its_list_fetches_the_customers_along_with_the_gift_cards(): void
    {
        $customer = new Customer();
        $customer->setEmail('list@example.com');
        $this->manager->persist($customer);

        $this->createGiftCard('WITHCUSTOMER')->setCustomer($customer);
        $this->createGiftCard('WITHOUTCUSTOMER');
        $this->manager->flush();
        $this->manager->clear();

        $giftCards = $this->repository->createListQueryBuilder()->getQuery()->getResult();
        self::assertIsArray($giftCards);
        self::assertEqualsCanonicalizing(['WITHCUSTOMER', 'WITHOUTCUSTOMER'], $this->codesOf($giftCards));

        foreach ($giftCards as $giftCard) {
            self::assertInstanceOf(GiftCardInterface::class, $giftCard);

            $loadedCustomer = $giftCard->getCustomer();
            if (null === $loadedCustomer) {
                continue;
            }

            self::assertFalse(
                $loadedCustomer instanceof Proxy && !$loadedCustomer->__isInitialized(),
                'the customer should have been fetched with the gift card rather than left as a lazy proxy',
            );
            self::assertSame('list@example.com', $loadedCustomer->getEmail());
        }
    }

    /** @test */
    public function it_finds_a_gift_card_by_its_code(): void
    {
        $this->createGiftCard('FINDBYCODE01');
        $this->createGiftCard('FINDBYCODE02');
        $this->manager->clear();

        self::assertSame('FINDBYCODE02', $this->repository->findOneByCode('FINDBYCODE02')?->getCode());
        self::assertNull($this->repository->findOneByCode('UNKNOWN'));
    }

    /**
     * This is the lookup behind the code a customer enters in the cart, so it must only ever hand out a card that
     * may be spent in the channel the customer is shopping in
     *
     * @test
     */
    public function it_finds_an_enabled_gift_card_by_code_only_in_its_own_channel(): void
    {
        $channel = $this->getChannel();
        $otherChannel = $this->createChannel('OTHER_CHANNEL');

        $this->createGiftCard('ENABLEDHERE');
        $this->createGiftCard('DISABLEDHERE', enabled: false);
        $this->createGiftCard('ENABLEDTHERE', channel: $otherChannel);

        self::assertSame(
            'ENABLEDHERE',
            $this->repository->findOneEnabledByCodeAndChannel('ENABLEDHERE', $channel)?->getCode(),
        );
        self::assertNull($this->repository->findOneEnabledByCodeAndChannel('DISABLEDHERE', $channel));
        self::assertNull($this->repository->findOneEnabledByCodeAndChannel('ENABLEDTHERE', $channel));
        self::assertSame(
            'ENABLEDTHERE',
            $this->repository->findOneEnabledByCodeAndChannel('ENABLEDTHERE', $otherChannel)?->getCode(),
        );
        self::assertNull($this->repository->findOneEnabledByCodeAndChannel('UNKNOWN', $channel));
    }

    /** @test */
    public function it_finds_the_gift_card_bought_with_an_order_item_unit(): void
    {
        $giftCard = $this->createGiftCardBoughtOnAnOrder('BOUGHTWITHUNIT', enabled: false);

        $unit = $giftCard->getOrderItemUnit();
        self::assertInstanceOf(OrderItemUnit::class, $unit);

        // a second unit on the same item that has no card (yet), like one added by bumping the quantity
        $item = $unit->getOrderItem();
        self::assertInstanceOf(OrderItem::class, $item);
        $unitWithoutCard = new OrderItemUnit($item);
        $this->manager->flush();

        self::assertSame('BOUGHTWITHUNIT', $this->repository->findOneByOrderItemUnit($unit)?->getCode());
        self::assertNull($this->repository->findOneByOrderItemUnit($unitWithoutCard));
    }

    /**
     * The balance report shows what the shop owes its customers: the balance on every card that can still be spent,
     * per currency. A disabled card, a spent card and an expired card owe nothing
     *
     * @test
     */
    public function it_aggregates_the_outstanding_balance_per_currency(): void
    {
        $euroChannel = $this->createEuroChannel();

        $this->createGiftCard('USD01', amount: 5000);
        $this->createGiftCard('USD02', amount: 2500, expiresAt: new \DateTimeImmutable('+1 month'));
        $this->createGiftCard('EUR01', amount: 1000, channel: $euroChannel, currencyCode: 'EUR');

        $this->createGiftCard('DISABLED', amount: 700, enabled: false);
        $this->createGiftCard('SPENT', amount: 0);
        $this->createGiftCard('EXPIRED', amount: 300, expiresAt: new \DateTimeImmutable('-1 day'));
        $this->manager->clear();

        // the grouping has no ORDER BY, so the rows are keyed by currency rather than compared in database order
        $balances = [];
        foreach ($this->repository->findBalance() as $balance) {
            $balances[$balance['currencyCode']] = $balance;
        }

        self::assertSame([
            'EUR' => ['currencyCode' => 'EUR', 'count' => 1, 'amount' => 1000],
            'USD' => ['currencyCode' => 'USD', 'count' => 2, 'amount' => 7500],
        ], $this->sortedByKey($balances));
    }

    /**
     * Expiry is judged against the date asked about, so the report can tell what will still be outstanding later on
     *
     * @test
     */
    public function it_judges_expiry_against_the_date_it_is_asked_about(): void
    {
        $this->createGiftCard('NEVEREXPIRES', amount: 1000);
        $this->createGiftCard('EXPIRESSOON', amount: 2000, expiresAt: new \DateTimeImmutable('+10 days'));
        $this->manager->clear();

        self::assertSame(
            [['currencyCode' => 'USD', 'count' => 2, 'amount' => 3000]],
            $this->repository->findBalance(new \DateTimeImmutable('+5 days')),
        );
        self::assertSame(
            [['currencyCode' => 'USD', 'count' => 1, 'amount' => 1000]],
            $this->repository->findBalance(new \DateTimeImmutable('+20 days')),
        );
    }

    /** @test */
    public function it_reports_no_balance_when_nothing_is_outstanding(): void
    {
        $this->createGiftCard('ONLYSPENT', amount: 0);

        self::assertSame([], $this->repository->findBalance());
    }

    private function createGiftCard(
        string $code,
        int $amount = 5000,
        bool $enabled = true,
        ?ChannelInterface $channel = null,
        string $currencyCode = 'USD',
        ?\DateTimeImmutable $expiresAt = null,
    ): GiftCardInterface {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createNew();
        $giftCard->setCode($code);
        $giftCard->setChannel($channel ?? $this->getChannel());
        $giftCard->setCurrencyCode($currencyCode);
        $giftCard->setInitialAmount($amount);
        $giftCard->setAmount($amount);
        $giftCard->setExpiresAt($expiresAt);
        $enabled ? $giftCard->enable() : $giftCard->disable();

        $this->manager->persist($giftCard);
        $this->manager->flush();

        return $giftCard;
    }

    /**
     * A card created for a unit in an order, the way add to cart creates one: it stays disabled until the order is
     * paid, which is what makes it pending
     */
    private function createGiftCardBoughtOnAnOrder(string $code, bool $enabled): GiftCardInterface
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('GIFT_CARD_' . $code);
        $product->setName('Gift card');
        $product->setSlug('gift-card-' . strtolower($code));
        $product->setGiftCard(true);
        $this->manager->persist($product);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('GIFT_CARD_VARIANT_' . $code);
        $variant->setProduct($product);
        $this->manager->persist($variant);

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(5000);
        $order->addItem($item);

        $unit = new OrderItemUnit($item);
        $this->manager->persist($order);

        $giftCard = $this->createGiftCard($code, enabled: $enabled);
        $unit->setGiftCard($giftCard);
        $this->manager->flush();

        return $giftCard;
    }

    private function createEuroChannel(): ChannelInterface
    {
        $euro = new Currency();
        $euro->setCode('EUR');
        $this->manager->persist($euro);

        $base = $this->getChannel();

        /** @var ChannelFactoryInterface<ChannelInterface> $channelFactory */
        $channelFactory = self::getContainer()->get('sylius.factory.channel');
        /** @var ChannelInterface $channel */
        $channel = $channelFactory->createNamed('Euro channel');
        $channel->setCode('EURO_CHANNEL');
        $channel->setBaseCurrency($euro);
        $channel->addCurrency($euro);
        $channel->setDefaultLocale($base->getDefaultLocale());
        foreach ($base->getLocales() as $locale) {
            $channel->addLocale($locale);
        }

        $this->manager->persist($channel);
        $this->manager->flush();

        return $channel;
    }

    private function balanceOperator(): GiftCardBalanceOperatorInterface
    {
        /** @var GiftCardBalanceOperatorInterface $balanceOperator */
        $balanceOperator = self::getContainer()->get(GiftCardBalanceOperatorInterface::class);

        return $balanceOperator;
    }

    /**
     * @return list<string>
     */
    private function codesOf(mixed $giftCards): array
    {
        self::assertIsArray($giftCards);

        $codes = [];
        foreach ($giftCards as $giftCard) {
            self::assertInstanceOf(GiftCardInterface::class, $giftCard);
            $codes[] = (string) $giftCard->getCode();
        }

        return $codes;
    }

    /**
     * @template T
     *
     * @param array<string, T> $array
     *
     * @return array<string, T>
     */
    private function sortedByKey(array $array): array
    {
        ksort($array);

        return $array;
    }
}
