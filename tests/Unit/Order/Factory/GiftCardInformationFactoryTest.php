<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Order\Factory;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Order\Factory\GiftCardInformationFactory;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItem;

final class GiftCardInformationFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_new(): void
    {
        $orderItem = new OrderItem();
        $orderItem->setUnitPrice(2500);
        $factory = new GiftCardInformationFactory(GiftCardInformation::class);
        $giftCardInformation = $factory->createNew($orderItem);
        $this->assertEquals($orderItem->getUnitPrice(), $giftCardInformation->getAmount());
    }
}
