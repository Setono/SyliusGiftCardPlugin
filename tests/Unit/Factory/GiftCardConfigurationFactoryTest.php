<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardConfigurationFactory;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Provider\DefaultGiftCardTemplateContentProviderInterface;
use Setono\SyliusGiftCardPlugin\Provider\PdfRenderingOptionsProviderInterface;
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
        $defaultGiftCardTemplateContentProvider = $this->prophesize(DefaultGiftCardTemplateContentProviderInterface::class);
        $defaultGiftCardTemplateContentProvider->getContent()->willReturn('twig');
        $defaultOrientation = PdfRenderingOptionsProviderInterface::ORIENTATION_PORTRAIT;
        $defaultPageSize = PdfRenderingOptionsProviderInterface::PAGE_SIZE_A8;

        $decoratedFactory->createNew()->willReturn($giftCardConfiguration);

        $factory = new GiftCardConfigurationFactory(
            $decoratedFactory->reveal(),
            $defaultGiftCardTemplateContentProvider->reveal(),
            $defaultOrientation,
            $defaultPageSize
        );
        $createdGiftCardConfiguration = $factory->createNew();

        $this->assertSame($giftCardConfiguration, $createdGiftCardConfiguration);
        $this->assertSame($defaultOrientation, $createdGiftCardConfiguration->getOrientation());
        $this->assertSame($defaultPageSize, $createdGiftCardConfiguration->getPageSize());
    }
}
