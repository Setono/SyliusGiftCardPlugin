<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Promotion\Checker\Rule;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Promotion\Checker\Rule\HasNoGiftCardRuleChecker;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\Order as CoreOrder;
use Sylius\Component\Core\Model\ProductVariant;

/**
 * A shop uses this rule to keep a promotion off orders that buy gift cards, since a discount on a gift card hands
 * out its face value for less. One gift card anywhere in the order is enough to make the order ineligible
 */
final class HasNoGiftCardRuleCheckerTest extends TestCase
{
    /** @test */
    public function an_order_without_gift_card_products_is_eligible(): void
    {
        $order = $this->orderWith([$this->itemOf(false), $this->itemOf(false)]);

        self::assertTrue((new HasNoGiftCardRuleChecker())->isEligible($order, []));
    }

    /** @test */
    public function an_order_with_a_gift_card_product_among_other_products_is_not_eligible(): void
    {
        $order = $this->orderWith([$this->itemOf(false), $this->itemOf(true), $this->itemOf(false)]);

        self::assertFalse((new HasNoGiftCardRuleChecker())->isEligible($order, []));
    }

    /** @test */
    public function an_empty_order_is_eligible(): void
    {
        self::assertTrue((new HasNoGiftCardRuleChecker())->isEligible(new Order(), []));
    }

    /**
     * An item whose variant has no product has nothing to ask, and is skipped rather than failing the check
     *
     * @test
     */
    public function an_item_without_a_product_does_not_make_the_order_ineligible(): void
    {
        $itemWithoutProduct = new OrderItem();
        $itemWithoutProduct->setVariant(new ProductVariant());

        $order = $this->orderWith([$itemWithoutProduct, $this->itemOf(false)]);

        self::assertTrue((new HasNoGiftCardRuleChecker())->isEligible($order, []));
    }

    /**
     * The rule can only tell gift cards apart on an order class that carries the plugin's interface
     *
     * @test
     */
    public function it_refuses_an_order_the_plugin_is_not_installed_on(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new HasNoGiftCardRuleChecker())->isEligible(new CoreOrder(), []);
    }

    /**
     * @param list<OrderItem> $items
     */
    private function orderWith(array $items): Order
    {
        $order = new Order();
        foreach ($items as $item) {
            $order->addItem($item);
        }

        return $order;
    }

    private function itemOf(bool $giftCard): OrderItem
    {
        $product = new Product();
        $product->setGiftCard($giftCard);

        $variant = new ProductVariant();
        $variant->setProduct($product);

        $item = new OrderItem();
        $item->setVariant($variant);

        return $item;
    }
}
