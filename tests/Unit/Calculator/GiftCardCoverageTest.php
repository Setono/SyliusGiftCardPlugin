<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Calculator;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverage;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;

/**
 * The coverage is what the cart's figures, the payment processors and the redemption at order placement read
 * from: the total says how much of the order the gift cards settle, the per card amount what each one is charged
 */
final class GiftCardCoverageTest extends TestCase
{
    /** @test */
    public function it_totals_what_the_gift_cards_cover(): void
    {
        $coverage = new GiftCardCoverage([
            ['giftCard' => new GiftCard(), 'amount' => 3000],
            ['giftCard' => new GiftCard(), 'amount' => 0],
            ['giftCard' => new GiftCard(), 'amount' => 4500],
        ]);

        self::assertSame(7500, $coverage->getTotal());
    }

    /** @test */
    public function it_covers_nothing_without_gift_cards(): void
    {
        $coverage = new GiftCardCoverage([]);

        self::assertSame(0, $coverage->getTotal());
        self::assertSame([], $coverage->getEntries());
    }

    /** @test */
    public function it_tells_what_each_gift_card_covers(): void
    {
        $first = new GiftCard();
        $second = new GiftCard();

        $coverage = new GiftCardCoverage([
            ['giftCard' => $first, 'amount' => 3000],
            ['giftCard' => $second, 'amount' => 4500],
        ]);

        self::assertSame(3000, $coverage->getAmountForGiftCard($first));
        self::assertSame(4500, $coverage->getAmountForGiftCard($second));
    }

    /**
     * A card the calculator did not see (e.g. one applied to another order) covers nothing on this one
     *
     * @test
     */
    public function it_says_a_gift_card_outside_the_coverage_covers_nothing(): void
    {
        $coverage = new GiftCardCoverage([
            ['giftCard' => new GiftCard(), 'amount' => 3000],
        ]);

        self::assertSame(0, $coverage->getAmountForGiftCard(new GiftCard()));
    }

    /**
     * The redemption creates one payment per entry in this order, so the entries must come back as they were given
     *
     * @test
     */
    public function it_keeps_the_entries_in_the_order_they_were_given(): void
    {
        $entries = [
            ['giftCard' => new GiftCard(), 'amount' => 4500],
            ['giftCard' => new GiftCard(), 'amount' => 3000],
        ];

        self::assertSame($entries, (new GiftCardCoverage($entries))->getEntries());
    }
}
