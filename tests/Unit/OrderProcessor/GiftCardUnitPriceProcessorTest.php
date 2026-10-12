<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\OrderProcessor;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\OrderProcessor\GiftCardUnitPriceProcessor;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Setono\SyliusGiftCardPlugin\Tests\Unit\Currency\FixedRateCurrencyConverter;
use Sylius\Component\Core\Model\Product as PlainProduct;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Order\Model\Order as BaseOrder;

/**
 * A gift card held in another currency than the cart's is worth its amount in that currency, so its line costs that
 * amount converted at the exchange rate of the day, every time the cart is processed (#488)
 */
final class GiftCardUnitPriceProcessorTest extends TestCase
{
    /**
     * $50.00 at 7.46 kroner to the dollar
     *
     * @test
     */
    public function it_prices_the_line_of_a_card_in_another_currency_at_its_amount_converted(): void
    {
        $order = $this->cart();
        $item = $this->giftCardLine($order, 'USD', 5000, units: 2);

        $this->processor()->process($order);

        self::assertSame(37300, $item->getUnitPrice());
        self::assertSame(74600, $item->getTotal());
    }

    /**
     * The rate the shop keeps changes, and the line follows it, the way every price shown in that currency does
     *
     * @test
     */
    public function it_reprices_the_line_at_the_rate_of_the_day(): void
    {
        $order = $this->cart();
        $item = $this->giftCardLine($order, 'USD', 5000);

        $this->processor()->process($order);
        (new GiftCardUnitPriceProcessor(new FixedRateCurrencyConverter(['USD' => 1.0, 'DKK' => 7.0])))->process($order);

        self::assertSame(35000, $item->getUnitPrice());
    }

    /**
     * A card in the cart's currency is priced at its amount when it is added, so there is nothing to convert
     *
     * @test
     */
    public function it_leaves_the_line_of_a_card_in_the_carts_currency_alone(): void
    {
        $order = $this->cart();
        $item = $this->giftCardLine($order, 'DKK', 5000);
        $item->setUnitPrice(4900);

        $this->processor()->process($order);

        self::assertSame(4900, $item->getUnitPrice());
    }

    /** @test */
    public function it_leaves_other_lines_alone(): void
    {
        $order = $this->cart();

        $item = new OrderItem();
        $item->setVariant($this->variant(giftCard: false));
        $item->setUnitPrice(1234);
        new OrderItemUnit($item);
        $order->addItem($item);

        // a gift card line without a card yet, the way add to cart is before the handler has run
        $withoutCard = new OrderItem();
        $withoutCard->setVariant($this->variant(giftCard: true));
        $withoutCard->setUnitPrice(4321);
        new OrderItemUnit($withoutCard);
        $order->addItem($withoutCard);

        $this->processor()->process($order);

        self::assertSame(1234, $item->getUnitPrice());
        self::assertSame(4321, $withoutCard->getUnitPrice());
    }

    /**
     * Every line is looked at, whatever comes before it
     *
     * @test
     */
    public function it_prices_every_line_of_a_card_in_another_currency_whatever_lines_come_before_it(): void
    {
        $order = $this->cart();

        $ordinary = new OrderItem();
        $ordinary->setVariant($this->variant(giftCard: false));
        $ordinary->setUnitPrice(1234);
        new OrderItemUnit($ordinary);
        $order->addItem($ordinary);

        $sameCurrency = $this->giftCardLine($order, 'DKK', 5000);
        $sameCurrency->setUnitPrice(5000);

        $item = $this->giftCardLine($order, 'USD', 5000);

        $this->processor()->process($order);

        self::assertSame(37300, $item->getUnitPrice());
    }

    /**
     * Sylius makes a line with the product's first enabled variant, and a product may have none enabled
     *
     * @test
     */
    public function it_leaves_a_line_without_a_variant_alone(): void
    {
        $order = $this->cart();

        $item = new OrderItem();
        $item->setUnitPrice(1234);
        new OrderItemUnit($item);
        $order->addItem($item);

        $this->processor()->process($order);

        self::assertSame(1234, $item->getUnitPrice());
    }

    /**
     * An application's product class need not be gift card aware at all
     *
     * @test
     */
    public function it_leaves_a_line_of_a_product_that_knows_nothing_of_gift_cards_alone(): void
    {
        $order = $this->cart();

        $variant = new ProductVariant();
        (new PlainProduct())->addVariant($variant);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(1234);
        new OrderItemUnit($item);
        $order->addItem($item);

        $this->processor()->process($order);

        self::assertSame(1234, $item->getUnitPrice());
    }

    /**
     * A placed order costs what it cost when it was placed
     *
     * @test
     */
    public function it_leaves_an_order_that_is_no_longer_a_cart_alone(): void
    {
        $order = $this->cart();
        $item = $this->giftCardLine($order, 'USD', 5000);
        $item->setUnitPrice(37000);
        $order->setState(Order::STATE_NEW);

        $this->processor()->process($order);

        self::assertSame(37000, $item->getUnitPrice());
    }

    /** @test */
    public function it_leaves_an_order_that_is_not_a_shop_order_alone(): void
    {
        $this->processor()->process(new BaseOrder());

        $this->addToAssertionCount(1);
    }

    private function processor(): GiftCardUnitPriceProcessor
    {
        // kroner at 7.46 to the dollar
        return new GiftCardUnitPriceProcessor(new FixedRateCurrencyConverter());
    }

    private function cart(): Order
    {
        $order = new Order();
        $order->setCurrencyCode('DKK');

        return $order;
    }

    /**
     * A gift card line as add to cart leaves it: every unit carries a pending card held in the currency and the amount
     * the customer chose
     */
    private function giftCardLine(Order $order, string $currencyCode, int $amount, int $units = 1): OrderItem
    {
        $item = new OrderItem();
        $item->setVariant($this->variant(giftCard: true));
        $item->setImmutable(true);
        $order->addItem($item);

        for ($i = 0; $i < $units; ++$i) {
            $giftCard = new GiftCard();
            $giftCard->setCurrencyCode($currencyCode);
            $giftCard->setInitialAmount($amount);
            $giftCard->setAmount($amount);

            $unit = new OrderItemUnit($item);
            $giftCard->setOrderItemUnit($unit);
        }

        return $item;
    }

    private function variant(bool $giftCard): ProductVariant
    {
        $product = new Product();
        $product->setGiftCard($giftCard);

        $variant = new ProductVariant();
        $product->addVariant($variant);

        return $variant;
    }
}
