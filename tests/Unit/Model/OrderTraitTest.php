<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;

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
}
