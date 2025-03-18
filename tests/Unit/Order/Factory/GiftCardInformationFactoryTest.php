<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Order\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Order\Factory\GiftCardInformationFactory;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;

final class GiftCardInformationFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_new(): void
    {
        $orderItem = new OrderItem();
        $orderItem->setUnitPrice(2500);
        $factory = new GiftCardInformationFactory(GiftCardInformation::class);
        $giftCardInformation = $factory->createNew($orderItem);
        $this->assertEquals($orderItem->getUnitPrice(), $giftCardInformation->getAmount());
    }
}
