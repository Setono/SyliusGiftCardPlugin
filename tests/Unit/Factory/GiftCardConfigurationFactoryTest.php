<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardConfigurationFactory;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationImage;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class GiftCardConfigurationFactoryTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_creates_a_new_gift_card_configuration(): void
    {
        $giftCardConfiguration = new GiftCardConfiguration();

        $decoratedFactory = $this->prophesize(FactoryInterface::class);
        $imageFactory = $this->prophesize(FactoryInterface::class);
        $defaultOrientation = 'Portrait';
        $defaultPageSize = 'A8';

        $decoratedFactory->createNew()->willReturn($giftCardConfiguration);
        $imageFactory->createNew()->willReturn(new GiftCardConfigurationImage());

        $factory = new GiftCardConfigurationFactory(
            $decoratedFactory->reveal(),
            $imageFactory->reveal(),
            $defaultOrientation,
            $defaultPageSize
        );
        $createdGiftCardConfiguration = $factory->createNew();

        $this->assertSame($giftCardConfiguration, $createdGiftCardConfiguration);
        $this->assertSame($defaultOrientation, $createdGiftCardConfiguration->getOrientation());
        $this->assertSame($defaultPageSize, $createdGiftCardConfiguration->getPageSize());
    }
}
