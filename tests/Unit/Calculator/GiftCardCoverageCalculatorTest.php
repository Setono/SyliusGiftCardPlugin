<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Calculator;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Calculator\EligibleTotalCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculator;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardEligibilityCheckerInterface;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardIneligibilityReason;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;

final class GiftCardCoverageCalculatorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<GiftCardEligibilityCheckerInterface> */
    private ObjectProphecy $eligibilityChecker;

    protected function setUp(): void
    {
        $this->eligibilityChecker = $this->prophesize(GiftCardEligibilityCheckerInterface::class);
        $this->eligibilityChecker->getIneligibilityReason(Argument::cetera())->willReturn(null);
    }

    /** @test */
    public function it_stacks_multiple_gift_cards_capped_at_the_eligible_total(): void
    {
        $first = $this->giftCard(6000);
        $second = $this->giftCard(6000);

        $order = $this->orderWith([$first, $second]);

        $coverage = $this->calculator(10000)->calculate($order->reveal());

        self::assertSame(6000, $coverage->getAmountForGiftCard($first->reveal()));
        self::assertSame(4000, $coverage->getAmountForGiftCard($second->reveal()));
        self::assertSame(10000, $coverage->getTotal());
    }

    /** @test */
    public function it_caps_a_single_gift_card_at_the_eligible_total(): void
    {
        $giftCard = $this->giftCard(9000);
        $order = $this->orderWith([$giftCard]);

        $coverage = $this->calculator(4000)->calculate($order->reveal());

        self::assertSame(4000, $coverage->getAmountForGiftCard($giftCard->reveal()));
    }

    /**
     * @test
     *
     * @dataProvider provideIneligibilityReasons
     */
    public function it_ignores_gift_cards_the_eligibility_checker_rejects_for_the_order(GiftCardIneligibilityReason $reason): void
    {
        $giftCard = $this->giftCard(5000);
        $order = $this->orderWith([$giftCard]);

        $this->eligibilityChecker->getIneligibilityReason($giftCard->reveal(), $order->reveal())->willReturn($reason);

        $coverage = $this->calculator(10000)->calculate($order->reveal());

        self::assertSame(0, $coverage->getAmountForGiftCard($giftCard->reveal()));
        self::assertSame(0, $coverage->getTotal());
    }

    /**
     * @return iterable<string, array{GiftCardIneligibilityReason}>
     */
    public static function provideIneligibilityReasons(): iterable
    {
        foreach (GiftCardIneligibilityReason::cases() as $reason) {
            yield $reason->value => [$reason];
        }
    }

    /** @test */
    public function it_leaves_what_a_rejected_gift_card_would_have_covered_to_the_next_one(): void
    {
        $rejected = $this->giftCard(6000);
        $eligible = $this->giftCard(6000);
        $order = $this->orderWith([$rejected, $eligible]);

        $this->eligibilityChecker->getIneligibilityReason($rejected->reveal(), $order->reveal())
            ->willReturn(GiftCardIneligibilityReason::ChannelMismatch);

        $coverage = $this->calculator(5000)->calculate($order->reveal());

        self::assertSame(0, $coverage->getAmountForGiftCard($rejected->reveal()));
        self::assertSame(5000, $coverage->getAmountForGiftCard($eligible->reveal()));
        self::assertSame(5000, $coverage->getTotal());
    }

    private function calculator(int $eligibleTotal): GiftCardCoverageCalculator
    {
        $eligibleTotalCalculator = $this->prophesize(EligibleTotalCalculatorInterface::class);
        $eligibleTotalCalculator->getEligibleTotal(Argument::any())->willReturn($eligibleTotal);

        return new GiftCardCoverageCalculator($eligibleTotalCalculator->reveal(), $this->eligibilityChecker->reveal());
    }

    /**
     * @return ObjectProphecy<GiftCardInterface>
     */
    private function giftCard(int $amount): ObjectProphecy
    {
        $giftCard = $this->prophesize(GiftCardInterface::class);
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
        $order->getGiftCards()->willReturn(new ArrayCollection(array_map(static fn (ObjectProphecy $gc) => $gc->reveal(), $giftCards)));

        return $order;
    }
}
