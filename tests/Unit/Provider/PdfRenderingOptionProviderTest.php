<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Provider\PdfOrientationProviderInterface;
use Setono\SyliusGiftCardPlugin\Provider\PdfPageSizeProviderInterface;
use Setono\SyliusGiftCardPlugin\Provider\PdfRenderingOptionProvider;

final class PdfRenderingOptionProviderTest extends TestCase
{
    /**
     * @test
     */
    public function it_provides_rendering_options(): void
    {
        $provider = new PdfRenderingOptionProvider();
        $giftCardConfiguration = new GiftCardConfiguration();

        $renderingOptions = $provider->getRenderingOptions($giftCardConfiguration);
        $this->assertIsArray($renderingOptions);
    }

    /**
     * @test
     */
    public function it_provides_page_size_if_not_null(): void
    {
        $provider = new PdfRenderingOptionProvider();
        $giftCardConfiguration = new GiftCardConfiguration();
        $giftCardConfiguration->setPageSize(PdfPageSizeProviderInterface::FORMAT_A8);

        $renderingOptions = $provider->getRenderingOptions($giftCardConfiguration);
        $this->assertArrayHasKey('page-size', $renderingOptions);
        $this->assertEquals(PdfPageSizeProviderInterface::FORMAT_A8, $renderingOptions['page-size']);
    }

    /**
     * @test
     */
    public function it_provides_orientation_if_not_null(): void
    {
        $provider = new PdfRenderingOptionProvider();
        $giftCardConfiguration = new GiftCardConfiguration();
        $giftCardConfiguration->setOrientation(PdfOrientationProviderInterface::ORIENTATION_LANDSCAPE);

        $renderingOptions = $provider->getRenderingOptions($giftCardConfiguration);
        $this->assertArrayHasKey('orientation', $renderingOptions);
        $this->assertEquals(PdfOrientationProviderInterface::ORIENTATION_LANDSCAPE, $renderingOptions['orientation']);
    }
}
