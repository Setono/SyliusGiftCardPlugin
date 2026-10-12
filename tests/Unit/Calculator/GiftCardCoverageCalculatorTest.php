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
use Setono\SyliusGiftCardPlugin\Tests\Unit\Currency\FixedRateCurrencyConverter;

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
        self::assertSame(0, $coverage->getGiftCardAmountForGiftCard($giftCard->reveal()));
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

    /**
     * A card held in the currency the customer browses in is judged against the order as it is shown in that currency:
     * at 7.46 kroner to the dollar an order of $40.21 shows as DKK 299.97, which a DKK 300 card covers, giving exactly
     * the DKK 299.97 the customer was shown (#488)
     *
     * @test
     */
    public function it_covers_the_order_with_a_gift_card_in_another_currency_holding_what_the_order_shows_in_it(): void
    {
        $giftCard = $this->giftCard(30000, 'DKK');
        $order = $this->orderWith([$giftCard]);

        $coverage = $this->calculator(4021)->calculate($order->reveal());

        self::assertSame(4021, $coverage->getAmountForGiftCard($giftCard->reveal()));
        self::assertSame(29997, $coverage->getGiftCardAmountForGiftCard($giftCard->reveal()));
    }

    /**
     * A $40.21 card on an order kept in kroner and shown as $40.21 covers it, although $40.21 converted back is
     * DKK 299.97 of the DKK 300.00 the order costs: a card covering what the order shows leaves nothing to pay, whatever
     * converting back and forth does to the last minor unit
     *
     * @test
     */
    public function it_leaves_nothing_to_pay_when_a_gift_card_covers_what_the_order_shows_in_its_currency(): void
    {
        $giftCard = $this->giftCard(4021, 'USD');
        $order = $this->orderWith([$giftCard], 'DKK');

        $coverage = $this->calculator(30000)->calculate($order->reveal());

        self::assertSame(30000, $coverage->getAmountForGiftCard($giftCard->reveal()));
        self::assertSame(4021, $coverage->getGiftCardAmountForGiftCard($giftCard->reveal()));
    }

    /**
     * A card worth less than the order gives its whole balance, converted into the order's currency
     *
     * @test
     */
    public function it_converts_the_whole_balance_of_a_gift_card_in_another_currency_covering_part_of_the_order(): void
    {
        $giftCard = $this->giftCard(20000, 'DKK');
        $order = $this->orderWith([$giftCard]);

        $coverage = $this->calculator(4022)->calculate($order->reveal());

        self::assertSame(2681, $coverage->getAmountForGiftCard($giftCard->reveal()));
        self::assertSame(20000, $coverage->getGiftCardAmountForGiftCard($giftCard->reveal()));
        self::assertSame(2681, $coverage->getTotal());
    }

    /**
     * DKK 0.03 is not worth a cent, so the card pays nothing and keeps it
     *
     * @test
     */
    public function it_takes_nothing_off_a_gift_card_whose_balance_is_worth_no_minor_unit_of_the_order(): void
    {
        $giftCard = $this->giftCard(3, 'DKK');
        $order = $this->orderWith([$giftCard]);

        $coverage = $this->calculator(4021)->calculate($order->reveal());

        self::assertSame(0, $coverage->getAmountForGiftCard($giftCard->reveal()));
        self::assertSame(0, $coverage->getGiftCardAmountForGiftCard($giftCard->reveal()));
    }

    /**
     * A remainder of DKK 0.01 is not worth a cent, but the card does not pay it for nothing: it gives a cent
     *
     * @test
     */
    public function it_takes_at_least_a_minor_unit_off_a_gift_card_paying_a_remainder_too_small_to_show_in_its_currency(): void
    {
        $giftCard = $this->giftCard(500, 'USD');
        $order = $this->orderWith([$giftCard], 'DKK');

        $coverage = $this->calculator(1)->calculate($order->reveal());

        self::assertSame(1, $coverage->getAmountForGiftCard($giftCard->reveal()));
        self::assertSame(1, $coverage->getGiftCardAmountForGiftCard($giftCard->reveal()));
    }

    /**
     * Cards in different currencies stack like cards in one: each covers what the ones before it left
     *
     * @test
     */
    public function it_stacks_gift_cards_in_different_currencies(): void
    {
        $dollars = $this->giftCard(1000, 'USD');
        $kroner = $this->giftCard(30000, 'DKK');
        $order = $this->orderWith([$dollars, $kroner]);

        $coverage = $this->calculator(4021)->calculate($order->reveal());

        self::assertSame(1000, $coverage->getAmountForGiftCard($dollars->reveal()));
        self::assertSame(1000, $coverage->getGiftCardAmountForGiftCard($dollars->reveal()));
        // the $30.21 left shows as DKK 225.37
        self::assertSame(3021, $coverage->getAmountForGiftCard($kroner->reveal()));
        self::assertSame(22537, $coverage->getGiftCardAmountForGiftCard($kroner->reveal()));
        self::assertSame(4021, $coverage->getTotal());
    }

    private function calculator(int $eligibleTotal): GiftCardCoverageCalculator
    {
        $eligibleTotalCalculator = $this->prophesize(EligibleTotalCalculatorInterface::class);
        $eligibleTotalCalculator->getEligibleTotal(Argument::any())->willReturn($eligibleTotal);

        // kroner at 7.46 to the dollar
        return new GiftCardCoverageCalculator($eligibleTotalCalculator->reveal(), $this->eligibilityChecker->reveal(), new FixedRateCurrencyConverter());
    }

    /**
     * @return ObjectProphecy<GiftCardInterface>
     */
    private function giftCard(int $amount, string $currencyCode = 'USD'): ObjectProphecy
    {
        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCard->getAmount()->willReturn($amount);
        $giftCard->getCurrencyCode()->willReturn($currencyCode);

        return $giftCard;
    }

    /**
     * @param list<ObjectProphecy<GiftCardInterface>> $giftCards
     *
     * @return ObjectProphecy<OrderInterface>
     */
    private function orderWith(array $giftCards, string $currencyCode = 'USD'): ObjectProphecy
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getGiftCards()->willReturn(new ArrayCollection(array_map(static fn (ObjectProphecy $gc) => $gc->reveal(), $giftCards)));
        $order->getCurrencyCode()->willReturn($currencyCode);

        return $order;
    }
}
