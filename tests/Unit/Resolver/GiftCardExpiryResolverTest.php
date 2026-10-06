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

    /** @test */
    public function it_accepts_any_strtotime_compatible_interval(): void
    {
        $before = (new \DateTimeImmutable('+18 months'))->format('Y-m-d');
        $expiresAt = (new GiftCardExpiryResolver('18 months'))->resolve();
        $after = (new \DateTimeImmutable('+18 months'))->format('Y-m-d');

        self::assertNotNull($expiresAt);
        self::assertContains($expiresAt->format('Y-m-d'), [$before, $after]);
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
     * @dataProvider providePeriodsStrtotimeCannotRead
     *
     * @test
     */
    public function it_refuses_a_period_strtotime_cannot_read(string $period, string $expectedMessage): void
    {
        $resolver = new GiftCardExpiryResolver($period);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $resolver->resolve();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providePeriodsStrtotimeCannotRead(): iterable
    {
        yield 'not an interval' => ['not a period', 'The default_validity_period must be a valid strtotime interval, e.g. "3 years": "not a period"'];
        yield 'empty, as a variable that is set to nothing' => ['', 'The default_validity_period must be a valid strtotime interval, e.g. "3 years": ""'];
    }
}
