<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Order\Factory;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Order\Factory\GiftCardInformationFactory;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Sylius\Component\Core\Model\OrderItem;

final class GiftCardInformationFactoryTest extends TestCase
{
    /**
     * The amount field starts out at what the line costs, so the customer changes a price rather than filling in a
     * blank, and nothing else has been chosen yet
     *
     * @test
     */
    public function it_seeds_the_information_with_the_unit_price_of_the_line(): void
    {
        $item = new OrderItem();
        $item->setUnitPrice(2500);

        $information = (new GiftCardInformationFactory(GiftCardInformation::class))->createNew($item);

        self::assertSame(2500, $information->getAmount());
        self::assertNull($information->getCustomMessage());
        self::assertNull($information->getDesign());
    }

    /**
     * The class is the setono_sylius_gift_card.order.model.gift_card_information.class parameter, which is how an
     * application carries information of its own through add to cart
     *
     * @test
     */
    public function it_creates_the_configured_class(): void
    {
        $information = (new GiftCardInformationFactory(CustomGiftCardInformation::class))->createNew(new OrderItem());

        self::assertInstanceOf(CustomGiftCardInformation::class, $information);
    }
}

final class CustomGiftCardInformation extends GiftCardInformation
{
}
