<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;

/**
 * The unit and its gift card point at each other, and either side may be the one that is set. Each setter sets the
 * other side, which only terminates because setting what is already set does nothing
 */
final class OrderItemUnitTraitTest extends TestCase
{
    /** @test */
    public function it_links_the_gift_card_back_to_the_unit(): void
    {
        $unit = new OrderItemUnit(new OrderItem());
        $giftCard = new GiftCard();

        $unit->setGiftCard($giftCard);

        self::assertSame($giftCard, $unit->getGiftCard());
        self::assertSame($unit, $giftCard->getOrderItemUnit());
    }

    /** @test */
    public function it_links_a_replacing_gift_card_back_to_the_unit(): void
    {
        $unit = new OrderItemUnit(new OrderItem());
        $unit->setGiftCard(new GiftCard());

        $replacement = new GiftCard();
        $unit->setGiftCard($replacement);

        self::assertSame($replacement, $unit->getGiftCard());
        self::assertSame($unit, $replacement->getOrderItemUnit());
    }

    /** @test */
    public function it_can_be_unlinked_from_its_gift_card(): void
    {
        $unit = new OrderItemUnit(new OrderItem());
        $unit->setGiftCard(new GiftCard());

        $unit->setGiftCard(null);

        self::assertNull($unit->getGiftCard());
    }
}
