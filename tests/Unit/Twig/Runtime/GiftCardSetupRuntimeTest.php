<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Twig\Runtime;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardSetupCheckerInterface;
use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardSetupRuntime;
use Sylius\Component\Core\Model\ChannelInterface;

final class GiftCardSetupRuntimeTest extends TestCase
{
    use ProphecyTrait;

    /**
     * The top bar and the index message both ask on the same page, so the checker is consulted once per request
     *
     * @test
     */
    public function it_asks_the_checker_once_per_request(): void
    {
        $channel = $this->prophesize(ChannelInterface::class)->reveal();

        $checker = $this->prophesize(GiftCardSetupCheckerInterface::class);
        $checker->getChannelsWithoutDesign()->willReturn([$channel])->shouldBeCalledOnce();

        $runtime = new GiftCardSetupRuntime($checker->reveal());

        self::assertSame([$channel], $runtime->getChannelsWithoutDesign());
        self::assertSame([$channel], $runtime->getChannelsWithoutDesign());
    }

    /** @test */
    public function it_remembers_an_empty_answer_as_well(): void
    {
        $checker = $this->prophesize(GiftCardSetupCheckerInterface::class);
        $checker->getChannelsWithoutDesign()->willReturn([])->shouldBeCalledOnce();

        $runtime = new GiftCardSetupRuntime($checker->reveal());

        self::assertSame([], $runtime->getChannelsWithoutDesign());
        self::assertSame([], $runtime->getChannelsWithoutDesign());
    }

    /**
     * Under a worker runtime the shared runtime outlives the request, and the kernel resets it before the next one,
     * which must ask the checker again rather than show the previous request's answer
     *
     * @test
     */
    public function it_forgets_the_answer_when_reset(): void
    {
        $channel = $this->prophesize(ChannelInterface::class)->reveal();

        $checker = $this->prophesize(GiftCardSetupCheckerInterface::class);
        $checker->getChannelsWithoutDesign()->willReturn([$channel], [])->shouldBeCalledTimes(2);

        $runtime = new GiftCardSetupRuntime($checker->reveal());

        self::assertSame([$channel], $runtime->getChannelsWithoutDesign());

        $runtime->reset();

        self::assertSame([], $runtime->getChannelsWithoutDesign());
        self::assertSame([], $runtime->getChannelsWithoutDesign());
    }
}
