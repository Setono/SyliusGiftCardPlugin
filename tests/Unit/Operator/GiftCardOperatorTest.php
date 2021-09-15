<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Operator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperator;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\ProductVariant;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\Order;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItem;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItemUnit;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\Product;

final class GiftCardOperatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_associate_gift_card_to_customer(): void
    {
        $order = new Order();
        $customer = new Customer();
        $giftCard = new GiftCard();

        $order->setCustomer($customer);

        $product = new Product();
        $product->setGiftCard(true);
        $productVariant = new ProductVariant();
        $productVariant->setProduct($product);
        $orderItemUnit = $this->prophesize(OrderItemUnit::class);
        $orderItemUnit->getGiftCard()->willReturn($giftCard);
        $orderItem = $this->prophesize(OrderItem::class);
        $orderItem->getVariant()->willReturn($productVariant);
        $orderItem->getProduct()->willReturn($product);
        $orderItem->getUnits()->willReturn(new ArrayCollection([$orderItemUnit->reveal()]));
        $orderItem->getTotal()->willReturn(0);
        $orderItem->setOrder($order)->shouldBeCalled();
        $order->addItem($orderItem->reveal());

        $giftCardManager = $this->prophesize(EntityManagerInterface::class);
        $giftCardOrderEmailManager = $this->prophesize(GiftCardEmailManagerInterface::class);

        $orderGiftCardOperator = new OrderGiftCardOperator(
            $giftCardManager->reveal(),
            $giftCardOrderEmailManager->reveal()
        );

        $orderGiftCardOperator->associateToCustomer($order);
        $this->assertEquals($customer, $giftCard->getCustomer());
    }

    /**
     * @test
     */
    public function it_returns_if_there_is_no_gift_card_item(): void
    {
        $order = $this->prophesize(Order::class);
        $order->getItems()->willReturn(new ArrayCollection());

        $giftCardManager = $this->prophesize(EntityManagerInterface::class);
        $giftCardOrderEmailManager = $this->prophesize(GiftCardEmailManagerInterface::class);

        $orderGiftCardOperator = new OrderGiftCardOperator(
            $giftCardManager->reveal(),
            $giftCardOrderEmailManager->reveal()
        );

        $orderGiftCardOperator->associateToCustomer($order->reveal());
        $order->getCustomer()->shouldNotBeCalled();
    }

    /**
     * @test
     */
    public function it_enables_gift_card(): void
    {
        $order = new Order();
        $customer = new Customer();
        $giftCard = new GiftCard();
        $giftCard->setEnabled(false);

        $order->setCustomer($customer);

        $product = new Product();
        $product->setGiftCard(true);
        $productVariant = new ProductVariant();
        $productVariant->setProduct($product);
        $orderItemUnit = $this->prophesize(OrderItemUnit::class);
        $orderItemUnit->getGiftCard()->willReturn($giftCard);
        $orderItem = $this->prophesize(OrderItem::class);
        $orderItem->getVariant()->willReturn($productVariant);
        $orderItem->getProduct()->willReturn($product);
        $orderItem->getUnits()->willReturn(new ArrayCollection([$orderItemUnit->reveal()]));
        $orderItem->getTotal()->willReturn(0);
        $orderItem->setOrder($order)->shouldBeCalled();
        $order->addItem($orderItem->reveal());

        $giftCardManager = $this->prophesize(EntityManagerInterface::class);
        $giftCardOrderEmailManager = $this->prophesize(GiftCardEmailManagerInterface::class);

        $orderGiftCardOperator = new OrderGiftCardOperator(
            $giftCardManager->reveal(),
            $giftCardOrderEmailManager->reveal()
        );

        $orderGiftCardOperator->enable($order);
        $this->assertTrue($giftCard->isEnabled());
    }

    /**
     * @test
     */
    public function it_disables_gift_card(): void
    {
        $order = new Order();
        $customer = new Customer();
        $giftCard = new GiftCard();
        $giftCard->setEnabled(true);

        $order->setCustomer($customer);

        $product = new Product();
        $product->setGiftCard(true);
        $productVariant = new ProductVariant();
        $productVariant->setProduct($product);
        $orderItemUnit = $this->prophesize(OrderItemUnit::class);
        $orderItemUnit->getGiftCard()->willReturn($giftCard);
        $orderItem = $this->prophesize(OrderItem::class);
        $orderItem->getVariant()->willReturn($productVariant);
        $orderItem->getProduct()->willReturn($product);
        $orderItem->getUnits()->willReturn(new ArrayCollection([$orderItemUnit->reveal()]));
        $orderItem->getTotal()->willReturn(0);
        $orderItem->setOrder($order)->shouldBeCalled();
        $order->addItem($orderItem->reveal());

        $giftCardManager = $this->prophesize(EntityManagerInterface::class);
        $giftCardOrderEmailManager = $this->prophesize(GiftCardEmailManagerInterface::class);

        $orderGiftCardOperator = new OrderGiftCardOperator(
            $giftCardManager->reveal(),
            $giftCardOrderEmailManager->reveal()
        );

        $orderGiftCardOperator->disable($order);
        $this->assertFalse($giftCard->isEnabled());
    }
}
