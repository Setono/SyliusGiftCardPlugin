<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProvider;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class GiftCardDesignProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * The provider is called while the product page renders, so it only reads what the channel offers
     *
     * @test
     */
    public function it_provides_the_enabled_designs_of_the_channel(): void
    {
        $channel = $this->prophesize(ChannelInterface::class)->reveal();
        $designs = [new GiftCardDesign(), new GiftCardDesign()];

        $repository = $this->prophesize(GiftCardDesignRepositoryInterface::class);
        $repository->findEnabledByChannel($channel)->willReturn($designs)->shouldBeCalledOnce();

        self::assertSame($designs, (new GiftCardDesignProvider($repository->reveal()))->getDesigns($channel));
    }

    /** @test */
    public function it_provides_nothing_for_a_channel_without_designs_rather_than_creating_one(): void
    {
        $channel = $this->prophesize(ChannelInterface::class)->reveal();

        $repository = $this->prophesize(GiftCardDesignRepositoryInterface::class);
        $repository->findEnabledByChannel($channel)->willReturn([]);
        $repository->add(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], (new GiftCardDesignProvider($repository->reveal()))->getDesigns($channel));
    }
}
