<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Calculator;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Calculator\EligibleTotalCalculator;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface as CoreProductInterface;

final class EligibleTotalCalculatorTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_subtracts_gift_card_line_items_from_the_order_total(): void
    {
        $giftCardProduct = $this->prophesize(ProductInterface::class);
        $giftCardProduct->isGiftCard()->willReturn(true);

        $normalProduct = $this->prophesize(CoreProductInterface::class);

        $giftCardItem = $this->prophesize(OrderItemInterface::class);
        $giftCardItem->getProduct()->willReturn($giftCardProduct->reveal());
        $giftCardItem->getTotal()->willReturn(5000);

        $normalItem = $this->prophesize(OrderItemInterface::class);
        $normalItem->getProduct()->willReturn($normalProduct->reveal());
        $normalItem->getTotal()->willReturn(3000);

        $order = $this->prophesize(OrderInterface::class);
        $order->getTotal()->willReturn(8000);
        $order->getItems()->willReturn(new ArrayCollection([$giftCardItem->reveal(), $normalItem->reveal()]));

        $calculator = new EligibleTotalCalculator();

        self::assertSame(3000, $calculator->getEligibleTotal($order->reveal()));
    }

    /**
     * Every product of an application that installed the plugin is of the plugin's product class, so being one is
     * not what makes a product a gift card
     *
     * @test
     */
    public function it_keeps_ordinary_products_and_items_without_a_product_eligible(): void
    {
        $ordinaryProduct = $this->prophesize(ProductInterface::class);
        $ordinaryProduct->isGiftCard()->willReturn(false);

        $ordinaryItem = $this->prophesize(OrderItemInterface::class);
        $ordinaryItem->getProduct()->willReturn($ordinaryProduct->reveal());
        $ordinaryItem->getTotal()->willReturn(3000);

        $itemWithoutProduct = $this->prophesize(OrderItemInterface::class);
        $itemWithoutProduct->getProduct()->willReturn(null);
        $itemWithoutProduct->getTotal()->willReturn(2000);

        $order = $this->prophesize(OrderInterface::class);
        $order->getTotal()->willReturn(5000);
        $order->getItems()->willReturn(new ArrayCollection([$ordinaryItem->reveal(), $itemWithoutProduct->reveal()]));

        $calculator = new EligibleTotalCalculator();

        self::assertSame(5000, $calculator->getEligibleTotal($order->reveal()));
    }

    /**
     * Redeeming does not change what the order costs, so the eligible total a card is measured against stays
     * put however many cards are already applied
     *
     * @test
     */
    public function it_is_unaffected_by_gift_cards_already_applied(): void
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getTotal()->willReturn(2735);
        $order->getItems()->willReturn(new ArrayCollection([]));

        $calculator = new EligibleTotalCalculator();

        self::assertSame(2735, $calculator->getEligibleTotal($order->reveal()));
    }

    /** @test */
    public function it_never_returns_a_negative_eligible_total(): void
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getTotal()->willReturn(-100);
        $order->getItems()->willReturn(new ArrayCollection([]));

        $calculator = new EligibleTotalCalculator();

        self::assertSame(0, $calculator->getEligibleTotal($order->reveal()));
    }
}
