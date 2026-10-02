<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Order\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Order\Factory\GiftCardInformationFactory;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Exception\MissingChannelConfigurationException;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\ProductVariant;

final class GiftCardInformationFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductVariantPricesCalculatorInterface> */
    private ObjectProphecy $calculator;

    protected function setUp(): void
    {
        $this->calculator = $this->prophesize(ProductVariantPricesCalculatorInterface::class);
    }

    /**
     * The amount field starts out at the price the product page shows next to it, so the customer adjusts a suggested
     * amount rather than filling in a blank, and nothing else has been chosen yet. The line itself is not priced
     * before it is in the cart, so its unit price is no guide
     *
     * @test
     */
    public function it_seeds_the_amount_with_the_price_of_the_variant_in_the_channel_of_the_cart(): void
    {
        $channel = new Channel();
        $variant = new ProductVariant();
        $this->calculator->calculate($variant, ['channel' => $channel])->willReturn(5000);

        // what the line costs on the product page, where Sylius has not priced it yet
        $item = $this->createItem($variant);
        $item->setUnitPrice(0);

        $information = $this->createFactory()->createNew($this->createCart($channel), $item);

        self::assertSame(5000, $information->getAmount());
        self::assertNull($information->getCustomMessage());
        self::assertNull($information->getDesign());
    }

    /** @test */
    public function it_leaves_the_amount_empty_when_the_line_has_no_variant(): void
    {
        $this->calculator->calculate(Argument::cetera())->shouldNotBeCalled();

        $information = $this->createFactory()->createNew($this->createCart(new Channel()), new OrderItem());

        self::assertNull($information->getAmount());
    }

    /** @test */
    public function it_leaves_the_amount_empty_when_the_cart_has_no_channel(): void
    {
        $this->calculator->calculate(Argument::cetera())->shouldNotBeCalled();

        $information = $this->createFactory()->createNew(new Order(), $this->createItem(new ProductVariant()));

        self::assertNull($information->getAmount());
    }

    /**
     * Sylius' calculator throws rather than returning a price for a variant that is not priced in the channel, and
     * the product page would then have nothing to show either
     *
     * @test
     */
    public function it_leaves_the_amount_empty_when_the_variant_has_no_price_in_the_channel(): void
    {
        $channel = new Channel();
        $variant = new ProductVariant();
        $this->calculator
            ->calculate($variant, ['channel' => $channel])
            ->willThrow(new MissingChannelConfigurationException('Product variant has no price defined for channel'));

        $information = $this->createFactory()->createNew($this->createCart($channel), $this->createItem($variant));

        self::assertNull($information->getAmount());
    }

    /**
     * A gift card product may well be priced at zero, as the customer chooses the amount anyway. The shop never sells
     * a gift card worth nothing, so starting there would only have the customer told off for an amount they did not
     * choose
     *
     * @test
     */
    public function it_never_seeds_an_amount_of_zero(): void
    {
        $channel = new Channel();
        $variant = new ProductVariant();
        $this->calculator->calculate($variant, ['channel' => $channel])->willReturn(0);

        $information = $this->createFactory()->createNew($this->createCart($channel), $this->createItem($variant));

        self::assertNull($information->getAmount());
    }

    /**
     * The class is the setono_sylius_gift_card.order.model.gift_card_information.class parameter, which is how an
     * application carries information of its own through add to cart
     *
     * @test
     */
    public function it_creates_the_configured_class(): void
    {
        $factory = new GiftCardInformationFactory(CustomGiftCardInformation::class, $this->calculator->reveal());

        $information = $factory->createNew(new Order(), new OrderItem());

        self::assertInstanceOf(CustomGiftCardInformation::class, $information);
    }

    private function createFactory(): GiftCardInformationFactory
    {
        return new GiftCardInformationFactory(GiftCardInformation::class, $this->calculator->reveal());
    }

    private function createCart(Channel $channel): Order
    {
        $cart = new Order();
        $cart->setChannel($channel);

        return $cart;
    }

    private function createItem(ProductVariant $variant): OrderItem
    {
        $item = new OrderItem();
        $item->setVariant($variant);

        return $item;
    }
}

final class CustomGiftCardInformation extends GiftCardInformation
{
}
