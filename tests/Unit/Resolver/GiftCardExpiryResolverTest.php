<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Resolver\GiftCardExpiryResolver;

final class GiftCardExpiryResolverTest extends TestCase
{
    /** @test */
    public function it_expires_a_gift_card_the_configured_validity_period_from_now(): void
    {
        $before = (new \DateTimeImmutable('+3 years'))->format('Y-m-d');
        $expiresAt = (new GiftCardExpiryResolver('3 years'))->resolve();
        $after = (new \DateTimeImmutable('+3 years'))->format('Y-m-d');

        self::assertNotNull($expiresAt);
        // the day is read on both sides of the call, so a test running across midnight still passes
        self::assertContains($expiresAt->format('Y-m-d'), [$before, $after]);
    }

    /**
     * @dataProvider provideIntervals
     *
     * @test
     */
    public function it_accepts_an_interval_in_any_unit(string $period): void
    {
        $before = (new \DateTimeImmutable('+' . $period))->format('Y-m-d');
        $expiresAt = (new GiftCardExpiryResolver($period))->resolve();
        $after = (new \DateTimeImmutable('+' . $period))->format('Y-m-d');

        self::assertNotNull($expiresAt);
        self::assertContains($expiresAt->format('Y-m-d'), [$before, $after]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIntervals(): iterable
    {
        yield 'months' => ['18 months'];
        yield 'weeks' => ['2 weeks'];
        yield 'days' => ['90 days'];
        yield 'more than one unit' => ['1 year 6 months'];
    }

    /**
     * A gift card stays valid through the end of its expiry day
     *
     * @test
     */
    public function it_pins_the_expiry_to_the_end_of_the_day(): void
    {
        $expiresAt = (new GiftCardExpiryResolver('3 years'))->resolve();

        self::assertNotNull($expiresAt);
        self::assertSame('23:59:59', $expiresAt->format('H:i:s'));
    }

    /** @test */
    public function it_resolves_no_expiry_when_no_validity_period_is_configured(): void
    {
        self::assertNull((new GiftCardExpiryResolver(null))->resolve());
    }

    /**
     * The configuration refuses these periods, but not one taken from an environment variable, whose value is only
     * known at runtime. The resolver is built on every product page and for every order, so it only refuses once it is
     * asked for an expiry, with the message the configuration gives
     *
     * @dataProvider providePeriodsThatAreNotIntervals
     *
     * @test
     */
    public function it_refuses_a_period_that_is_not_an_interval(string $period): void
    {
        $resolver = new GiftCardExpiryResolver($period);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The default_validity_period must be a valid strtotime interval, e.g. "3 years": "%s"', $period));

        $resolver->resolve();
    }

    /**
     * strtotime() cannot read the first two, or the last. It reads the others, but not as an interval and nothing else,
     * and most of them would give a card that expires the day it is issued, or before
     *
     * @return iterable<string, array{string}>
     */
    public static function providePeriodsThatAreNotIntervals(): iterable
    {
        yield 'not an interval' => ['not a period'];
        yield 'empty, as a variable that is set to nothing' => [''];
        yield 'a unit strtotime() does not know, read as a timezone' => ['3 yrs'];
        yield 'a misspelled unit' => ['3 yeers'];
        yield 'another misspelled unit' => ['18 mnths'];
        yield 'a unit in another language' => ['2 jahre'];
        yield 'a number without a unit, read as a timezone' => ['3'];
        yield 'a day name, read as the third Monday from now' => ['3 mon'];
        yield 'a day of the month' => ['1 month last day of'];
        yield 'a date' => ['2030-01-01'];
        yield 'a time of day' => ['3 years noon'];
        yield 'a timezone' => ['3 years UTC'];
        yield 'an interval of nothing' => ['0 days'];
        yield 'a negative interval' => ['-1 year'];
        yield 'an interval into the past' => ['3 years ago'];
        yield 'a unit that goes back, after one that goes forward' => ['1 year -18 months'];
        yield 'a percent sign, which the message keeps as it is' => ['3 years %'];
    }
}
