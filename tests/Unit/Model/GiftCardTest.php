<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Order;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItem;

final class GiftCardTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_has_properties(): void
    {
        $orderItemUnit = $this->prophesize(OrderItemUnitInterface::class);
        $customer = new Customer();
        $giftCard = new GiftCard();
        $channel = new Channel();

        $giftCard->setCode('test-code');
        $this->assertSame('test-code', $giftCard->getCode());

        $giftCard->setOrderItemUnit($orderItemUnit->reveal());
        $this->assertSame($orderItemUnit->reveal(), $giftCard->getOrderItemUnit());

        $giftCard->setCustomer($customer);
        $this->assertSame($customer, $giftCard->getCustomer());

        $giftCard->setInitialAmount(25000);
        $this->assertSame(25000, $giftCard->getInitialAmount());

        $giftCard->setAmount(25000);
        $this->assertSame(25000, $giftCard->getAmount());

        $giftCard->addAppliedOrder(new Order());
        $giftCard->addAppliedOrder(new Order());
        $this->assertSame(2, $giftCard->getAppliedOrders()->count());

        $giftCard->setCurrencyCode('EUR');
        $this->assertSame('EUR', $giftCard->getCurrencyCode());

        $giftCard->setChannel($channel);
        $this->assertSame($channel, $giftCard->getChannel());

        $giftCard->setCustomMessage('custom message');
        $this->assertSame('custom message', $giftCard->getCustomMessage());

        $giftCard->setOrigin('My origin');
        $this->assertSame('My origin', $giftCard->getOrigin());
    }

    /**
     * @test
     */
    public function it_can_be_converted_to_string(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('test-code');
        $this->assertSame('test-code', $giftCard->__toString());
    }

    /**
     * @test
     */
    public function it_can_be_deletable(): void
    {
        $giftCard = new GiftCard();
        $this->assertSame(true, $giftCard->isDeletable());
    }

    /**
     * @test
     */
    public function it_is_not_deletable_if_it_has_order_item_unit(): void
    {
        $orderItemUnit = $this->prophesize(OrderItemUnitInterface::class);
        $giftCard = new GiftCard();
        $giftCard->setOrderItemUnit($orderItemUnit->reveal());
        $this->assertSame(false, $giftCard->isDeletable());
    }

    /**
     * @test
     */
    public function it_has_order_from_order_item_unit(): void
    {
        $giftCard = new GiftCard();
        $this->assertNull($giftCard->getOrder());

        $orderItemUnit = $this->prophesize(OrderItemUnitInterface::class);
        $orderItem = new OrderItem();
        $orderItemUnit->getOrderItem()->willReturn($orderItem);
        $giftCard->setOrderItemUnit($orderItemUnit->reveal());
        $orderItemUnit->setGiftCard($giftCard)->shouldBeCalled();

        $this->assertNull($giftCard->getOrder());

        $order = new Order();
        $orderItem->setOrder($order);

        $this->assertSame($order, $giftCard->getOrder());
    }

    /**
     * @test
     */
    public function it_has_applied_orders(): void
    {
        $giftCard = new GiftCard();

        $this->assertFalse($giftCard->hasAppliedOrders());

        $order1 = new Order();
        $giftCard->addAppliedOrder($order1);
        $this->assertSame(1, $giftCard->getAppliedOrders()->count());
        $this->assertTrue($giftCard->hasAppliedOrder($order1));

        $order2 = new Order();
        $giftCard->addAppliedOrder($order2);
        $this->assertSame(2, $giftCard->getAppliedOrders()->count());
        $this->assertTrue($giftCard->hasAppliedOrder($order2));

        $giftCard->removeAppliedOrder($order1);
        $this->assertSame(1, $giftCard->getAppliedOrders()->count());
        $this->assertFalse($giftCard->hasAppliedOrder($order1));
    }

    public function it_has_null_origin_by_default(): void
    {
        $giftCard = new GiftCard();
        $this->assertSame(null, $giftCard->getOrigin());
    }
}
