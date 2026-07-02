<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Calculator;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Calculator\EligibleTotalCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculator;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;

final class GiftCardCoverageCalculatorTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_stacks_multiple_gift_cards_capped_at_the_eligible_total(): void
    {
        $first = $this->usableGiftCard(6000);
        $second = $this->usableGiftCard(6000);

        $order = $this->orderWith([$first, $second]);

        $calculator = new GiftCardCoverageCalculator($this->eligibleTotal(10000));
        $coverage = $calculator->calculate($order->reveal());

        self::assertSame(6000, $coverage->getAmountForGiftCard($first->reveal()));
        self::assertSame(4000, $coverage->getAmountForGiftCard($second->reveal()));
        self::assertSame(10000, $coverage->getTotal());
    }

    /** @test */
    public function it_caps_a_single_gift_card_at_the_eligible_total(): void
    {
        $giftCard = $this->usableGiftCard(9000);
        $order = $this->orderWith([$giftCard]);

        $calculator = new GiftCardCoverageCalculator($this->eligibleTotal(4000));
        $coverage = $calculator->calculate($order->reveal());

        self::assertSame(4000, $coverage->getAmountForGiftCard($giftCard->reveal()));
    }

    /** @test */
    public function it_ignores_unusable_gift_cards(): void
    {
        $unusable = $this->prophesize(GiftCardInterface::class);
        $unusable->isUsable()->willReturn(false);
        $unusable->getCurrencyCode()->willReturn('USD');
        $unusable->getAmount()->willReturn(5000);

        $order = $this->orderWith([$unusable]);

        $calculator = new GiftCardCoverageCalculator($this->eligibleTotal(10000));
        $coverage = $calculator->calculate($order->reveal());

        self::assertSame(0, $coverage->getTotal());
    }

    /** @test */
    public function it_ignores_gift_cards_with_a_mismatching_currency(): void
    {
        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCard->isUsable()->willReturn(true);
        $giftCard->getCurrencyCode()->willReturn('EUR');
        $giftCard->getAmount()->willReturn(5000);

        $order = $this->orderWith([$giftCard]);

        $calculator = new GiftCardCoverageCalculator($this->eligibleTotal(10000));
        $coverage = $calculator->calculate($order->reveal());

        self::assertSame(0, $coverage->getTotal());
    }

    /**
     * @return ObjectProphecy<GiftCardInterface>
     */
    private function usableGiftCard(int $amount): ObjectProphecy
    {
        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCard->isUsable()->willReturn(true);
        $giftCard->getCurrencyCode()->willReturn('USD');
        $giftCard->getAmount()->willReturn($amount);

        return $giftCard;
    }

    /**
     * @param list<ObjectProphecy<GiftCardInterface>> $giftCards
     *
     * @return ObjectProphecy<OrderInterface>
     */
    private function orderWith(array $giftCards): ObjectProphecy
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getCurrencyCode()->willReturn('USD');
        $order->getGiftCards()->willReturn(new ArrayCollection(array_map(static fn (ObjectProphecy $gc) => $gc->reveal(), $giftCards)));

        return $order;
    }

    private function eligibleTotal(int $amount): EligibleTotalCalculatorInterface
    {
        $calculator = $this->prophesize(EligibleTotalCalculatorInterface::class);
        $calculator->getEligibleTotal(\Prophecy\Argument::any())->willReturn($amount);

        return $calculator->reveal();
    }
}
