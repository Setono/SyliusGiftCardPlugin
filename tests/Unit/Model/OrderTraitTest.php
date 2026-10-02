<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\CatalogPromotion;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductVariant;

/**
 * An application applies OrderTrait to its own Order class the way the test application does. The order owns the
 * many-to-many relation, while the gift card keeps the inverse side, which is how the plugin finds the orders a card
 * is applied to. Only the owning side is persisted, so both have to be kept in step in memory
 */
final class OrderTraitTest extends TestCase
{
    /** @test */
    public function a_new_order_has_no_gift_cards(): void
    {
        $order = new Order();

        self::assertFalse($order->hasGiftCards());
        self::assertCount(0, $order->getGiftCards());
    }

    /** @test */
    public function applying_a_gift_card_links_the_order_and_the_card(): void
    {
        $order = new Order();
        $giftCard = new GiftCard();

        $order->addGiftCard($giftCard);

        self::assertTrue($order->hasGiftCards());
        self::assertTrue($order->hasGiftCard($giftCard));
        self::assertSame([$giftCard], $order->getGiftCards()->toArray());
        self::assertSame([$order], $giftCard->getAppliedOrders()->toArray());
    }

    /**
     * Applying the same code twice must not make the card pay twice
     *
     * @test
     */
    public function applying_a_gift_card_twice_keeps_one(): void
    {
        $order = new Order();
        $giftCard = new GiftCard();

        $order->addGiftCard($giftCard);
        $order->addGiftCard($giftCard);

        self::assertCount(1, $order->getGiftCards());
        self::assertCount(1, $giftCard->getAppliedOrders());
    }

    /** @test */
    public function removing_a_gift_card_unlinks_the_order_and_the_card(): void
    {
        $order = new Order();
        $giftCard = new GiftCard();
        $other = new GiftCard();
        $order->addGiftCard($giftCard);
        $order->addGiftCard($other);

        $order->removeGiftCard($giftCard);

        self::assertFalse($order->hasGiftCard($giftCard));
        self::assertSame([$other], array_values($order->getGiftCards()->toArray()));
        self::assertCount(0, $giftCard->getAppliedOrders());
        self::assertSame([$order], $other->getAppliedOrders()->toArray());
    }

    /**
     * A card applied to another order keeps that order when someone tries to remove it from this one
     *
     * @test
     */
    public function removing_a_gift_card_that_is_not_applied_leaves_the_card_alone(): void
    {
        $order = new Order();
        $otherOrder = new Order();
        $giftCard = new GiftCard();
        $otherOrder->addGiftCard($giftCard);

        $order->removeGiftCard($giftCard);

        self::assertFalse($order->hasGiftCards());
        self::assertSame([$otherOrder], $giftCard->getAppliedOrders()->toArray());
        self::assertTrue($otherOrder->hasGiftCard($giftCard));
    }

    /**
     * Promotions take their percentage of, and spread their discount over, what these return, so the gift cards being
     * bought are left out: a gift card is worth the amount the customer chose and takes no share of a discount
     *
     * @test
     */
    public function the_gift_cards_being_bought_are_left_out_of_what_promotions_look_at(): void
    {
        $channel = self::channel();
        $order = new Order();
        $order->setChannel($channel);
        $order->addItem(self::item(5000, giftCard: true));
        $order->addItem(self::item(3000, giftCard: false));

        self::assertSame(8000, $order->getItemsTotal());
        self::assertSame(3000, $order->getPromotionSubjectTotal());
        self::assertSame(3000, $order->getNonDiscountedItemsTotal());
    }

    /**
     * Sylius already leaves an item whose variant has a catalog promotion applied out of the non discounted total, so
     * a gift card line like that must not be taken off a second time
     *
     * @test
     */
    public function a_gift_card_line_sylius_already_leaves_out_is_not_left_out_twice(): void
    {
        $channel = self::channel();
        $order = new Order();
        $order->setChannel($channel);
        $order->addItem(self::item(5000, giftCard: true, catalogPromotionIn: $channel));
        $order->addItem(self::item(3000, giftCard: false));

        self::assertSame(3000, $order->getPromotionSubjectTotal());
        self::assertSame(3000, $order->getNonDiscountedItemsTotal());
    }

    private static function channel(): Channel
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return $channel;
    }

    private static function item(int $unitPrice, bool $giftCard, ?Channel $catalogPromotionIn = null): OrderItem
    {
        $product = new Product();
        $product->setGiftCard($giftCard);

        $variant = new ProductVariant();
        $variant->setProduct($product);

        if (null !== $catalogPromotionIn) {
            $channelPricing = new ChannelPricing();
            $channelPricing->setChannelCode($catalogPromotionIn->getCode());
            $channelPricing->addAppliedPromotion(new CatalogPromotion());
            $variant->addChannelPricing($channelPricing);
        }

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice($unitPrice);
        new OrderItemUnit($item);

        return $item;
    }
}
