<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Provider\OrderEligibleTotalProvider;
use Setono\SyliusGiftCardPlugin\Tests\TestApplication\Entity\Order;

final class OrderEligibleTotalProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_returns_order_total(): void
    {
        $order = $this->prophesize(Order::class);
        $order->getTotal()->willReturn(1500);
        $giftCard = new GiftCard();

        $orderEligibleTotalProvider = new OrderEligibleTotalProvider();
        $this->assertEquals(1500, $orderEligibleTotalProvider->getEligibleTotal($order->reveal(), $giftCard));
    }
}
