<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimitsProvider;
use Sylius\Component\Core\Model\ChannelInterface;

final class GiftCardAmountLimitsProviderTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_provides_the_configured_limits(): void
    {
        $limits = (new GiftCardAmountLimitsProvider(100, 250000))->getLimits($this->prophesize(ChannelInterface::class)->reveal());

        self::assertSame(100, $limits->minimum);
        self::assertSame(250000, $limits->maximum);
    }

    /** @test */
    public function it_provides_no_maximum_when_none_is_configured(): void
    {
        $limits = (new GiftCardAmountLimitsProvider(100, null))->getLimits($this->prophesize(ChannelInterface::class)->reveal());

        self::assertSame(100, $limits->minimum);
        self::assertNull($limits->maximum);
    }
}
