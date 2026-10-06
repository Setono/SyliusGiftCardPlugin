<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Order\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Order\Factory\GiftCardInformationFactory;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimits;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimitsProviderInterface;
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

    /** @var ObjectProphecy<GiftCardAmountLimitsProviderInterface> */
    private ObjectProphecy $limitsProvider;

    protected function setUp(): void
    {
        $this->calculator = $this->prophesize(ProductVariantPricesCalculatorInterface::class);

        // the plugin's defaults: at least 1.00, no maximum
        $this->limitsProvider = $this->prophesize(GiftCardAmountLimitsProviderInterface::class);
        $this->limitsProvider->getLimits(Argument::any())->willReturn(new GiftCardAmountLimits(100, null));
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
     * The shop refuses an amount outside the purchase limits, so a product priced outside them would start the
     * customer off at an error, just like one priced at zero. The limits are the ones of the cart's channel, which is
     * where an application deciding them per channel plugs in
     *
     * @test
     */
    public function it_leaves_the_amount_empty_when_the_price_is_outside_the_purchase_limits_of_the_channel(): void
    {
        $channel = new Channel();
        $this->limitsProvider->getLimits($channel)->willReturn(new GiftCardAmountLimits(1000, 20000));

        $below = new ProductVariant();
        $this->calculator->calculate($below, ['channel' => $channel])->willReturn(999);
        $above = new ProductVariant();
        $this->calculator->calculate($above, ['channel' => $channel])->willReturn(20001);

        self::assertNull($this->createFactory()->createNew($this->createCart($channel), $this->createItem($below))->getAmount());
        self::assertNull($this->createFactory()->createNew($this->createCart($channel), $this->createItem($above))->getAmount());
    }

    /** @test */
    public function it_seeds_a_price_that_is_exactly_one_of_the_limits(): void
    {
        $channel = new Channel();
        $this->limitsProvider->getLimits($channel)->willReturn(new GiftCardAmountLimits(1000, 20000));

        $minimum = new ProductVariant();
        $this->calculator->calculate($minimum, ['channel' => $channel])->willReturn(1000);
        $maximum = new ProductVariant();
        $this->calculator->calculate($maximum, ['channel' => $channel])->willReturn(20000);

        self::assertSame(1000, $this->createFactory()->createNew($this->createCart($channel), $this->createItem($minimum))->getAmount());
        self::assertSame(20000, $this->createFactory()->createNew($this->createCart($channel), $this->createItem($maximum))->getAmount());
    }

    /**
     * A maximum equal to the minimum is a shop selling gift cards of a single amount. That amount is the only one the
     * shop accepts, so the field starts out at it rather than empty, whatever the product is priced at: below it, above
     * it, at nothing, or not at all in the channel
     *
     * @test
     */
    public function it_seeds_the_single_amount_when_the_maximum_equals_the_minimum_whatever_the_price(): void
    {
        $channel = new Channel();
        $this->limitsProvider->getLimits($channel)->willReturn(new GiftCardAmountLimits(50000, 50000));

        $below = new ProductVariant();
        $this->calculator->calculate($below, ['channel' => $channel])->willReturn(5000);
        $above = new ProductVariant();
        $this->calculator->calculate($above, ['channel' => $channel])->willReturn(90000);
        $free = new ProductVariant();
        $this->calculator->calculate($free, ['channel' => $channel])->willReturn(0);
        $unpriced = new ProductVariant();
        $this->calculator
            ->calculate($unpriced, ['channel' => $channel])
            ->willThrow(new MissingChannelConfigurationException('Product variant has no price defined for channel'));

        foreach ([$below, $above, $free, $unpriced] as $variant) {
            self::assertSame(50000, $this->createFactory()->createNew($this->createCart($channel), $this->createItem($variant))->getAmount());
        }
    }

    /**
     * The class is the setono_sylius_gift_card.order.model.gift_card_information.class parameter, which is how an
     * application carries information of its own through add to cart
     *
     * @test
     */
    public function it_creates_the_configured_class(): void
    {
        $factory = new GiftCardInformationFactory(CustomGiftCardInformation::class, $this->calculator->reveal(), $this->limitsProvider->reveal());

        $information = $factory->createNew(new Order(), new OrderItem());

        self::assertInstanceOf(CustomGiftCardInformation::class, $information);
    }

    private function createFactory(): GiftCardInformationFactory
    {
        return new GiftCardInformationFactory(GiftCardInformation::class, $this->calculator->reveal(), $this->limitsProvider->reveal());
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
