<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Cart;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Cart\CartGiftCardHandler;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariant;

/**
 * Adding a gift card to the cart is where the customer's choices turn into a card: every unit of the line gets a
 * pending (disabled) card carrying them, and the line is priced at the chosen amount instead of the variant's price
 */
final class CartGiftCardHandlerTest extends TestCase
{
    use ProphecyTrait;

    private ChannelInterface $channel;

    /** @var ObjectProphecy<GiftCardFactoryInterface> */
    private ObjectProphecy $giftCardFactory;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    protected function setUp(): void
    {
        $this->channel = new Channel();

        $this->giftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $this->giftCardFactory->createForChannel($this->channel)->will(static fn (): GiftCardInterface => new GiftCard());

        $this->manager = $this->prophesize(EntityManagerInterface::class);
    }

    /** @test */
    public function it_prices_the_line_at_the_chosen_amount_and_keeps_sylius_from_repricing_it(): void
    {
        $item = $this->item(unitPrice: 1000, units: 1);

        $this->handle($item, new GiftCardInformation(5000));

        self::assertSame(5000, $item->getUnitPrice());
        self::assertTrue($item->isImmutable(), 'the order processor would reprice the line at the variant price otherwise');
    }

    /** @test */
    public function it_issues_a_pending_card_carrying_the_customers_choices_for_every_unit(): void
    {
        $design = new GiftCardDesign();
        $information = new GiftCardInformation(5000, 'Happy birthday');
        $information->setDesign($design);

        $item = $this->item(unitPrice: 1000, units: 2);

        $this->manager->persist(Argument::type(GiftCardInterface::class))->shouldBeCalledTimes(2);

        $this->handle($item, $information);

        $giftCards = [];
        foreach ($item->getUnits() as $unit) {
            self::assertInstanceOf(OrderItemUnit::class, $unit);

            $giftCard = $unit->getGiftCard();
            self::assertInstanceOf(GiftCardInterface::class, $giftCard);
            self::assertSame($unit, $giftCard->getOrderItemUnit());

            self::assertSame(5000, $giftCard->getAmount());
            self::assertSame(5000, $giftCard->getInitialAmount());
            self::assertSame('DKK', $giftCard->getCurrencyCode(), 'the card holds money in the currency the cart is kept in');
            self::assertSame($design, $giftCard->getDesign());
            self::assertSame('Happy birthday', $giftCard->getCustomMessage());

            // nothing has been paid yet, so the card must not be spendable
            self::assertFalse($giftCard->isEnabled());
            self::assertTrue($giftCard->isPending());

            $giftCards[] = $giftCard;
        }

        self::assertCount(2, $giftCards);
        self::assertNotSame($giftCards[0], $giftCards[1], 'every unit is a card of its own');
    }

    /** @test */
    public function it_issues_a_physical_card_for_a_variant_that_is_shipped(): void
    {
        $item = $this->item(unitPrice: 1000, units: 1, shippingRequired: true);

        $this->handle($item, new GiftCardInformation(5000));

        self::assertSame(GiftCardDeliveryType::Physical, $this->onlyGiftCard($item)->getDeliveryType());
    }

    /** @test */
    public function it_issues_a_virtual_card_for_a_variant_that_is_not_shipped(): void
    {
        $item = $this->item(unitPrice: 1000, units: 1, shippingRequired: false);

        $this->handle($item, new GiftCardInformation(5000));

        self::assertSame(GiftCardDeliveryType::Virtual, $this->onlyGiftCard($item)->getDeliveryType());
    }

    /** @test */
    public function it_leaves_a_unit_that_already_carries_a_card_alone(): void
    {
        $item = $this->item(unitPrice: 1000, units: 2);

        $units = array_values($item->getUnits()->toArray());
        self::assertInstanceOf(OrderItemUnit::class, $units[0]);
        self::assertInstanceOf(OrderItemUnit::class, $units[1]);

        $existing = new GiftCard();
        $existing->setAmount(2500);
        $units[0]->setGiftCard($existing);

        $this->giftCardFactory->createForChannel($this->channel)->shouldBeCalledOnce();
        $this->manager->persist(Argument::any())->shouldBeCalledOnce();

        $this->handle($item, new GiftCardInformation(5000));

        self::assertSame($existing, $units[0]->getGiftCard());
        self::assertSame(2500, $existing->getAmount());

        $issued = $units[1]->getGiftCard();
        self::assertInstanceOf(GiftCardInterface::class, $issued);
        self::assertSame(5000, $issued->getAmount());
    }

    /**
     * The information carries a blank amount until validation has passed. Were the handler ever reached with one,
     * issuing cards worth nothing would be worse than failing loudly
     *
     * @test
     */
    public function it_refuses_to_issue_cards_without_an_amount(): void
    {
        $item = $this->item(unitPrice: 1000, units: 1);

        $this->giftCardFactory->createForChannel(Argument::any())->shouldNotBeCalled();

        try {
            $this->handle($item, new GiftCardInformation(null));
            self::fail('A blank amount should have been refused');
        } catch (\InvalidArgumentException) {
        }

        self::assertSame(1000, $item->getUnitPrice());
        self::assertFalse($item->isImmutable());
    }

    private function handle(OrderItem $item, GiftCardInformation $information): void
    {
        $cart = $item->getOrder();
        self::assertInstanceOf(Order::class, $cart);

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(GiftCard::class)->willReturn($this->manager->reveal());

        $handler = new CartGiftCardHandler($this->giftCardFactory->reveal(), $managerRegistry->reveal());
        $handler->handle(new AddToCartCommand($cart, $item, $information));
    }

    /**
     * A gift card line on a cart kept in DKK, with the given number of units, none of which carries a card yet
     */
    private function item(int $unitPrice, int $units, bool $shippingRequired = false): OrderItem
    {
        $variant = new ProductVariant();
        $variant->setShippingRequired($shippingRequired);

        $cart = new Order();
        $cart->setChannel($this->channel);
        $cart->setCurrencyCode('DKK');

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice($unitPrice);
        $cart->addItem($item);

        for ($i = 0; $i < $units; ++$i) {
            new OrderItemUnit($item);
        }

        return $item;
    }

    private function onlyGiftCard(OrderItem $item): GiftCardInterface
    {
        self::assertCount(1, $item->getUnits());

        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);

        $giftCard = $unit->getGiftCard();
        self::assertInstanceOf(GiftCardInterface::class, $giftCard);

        return $giftCard;
    }
}
