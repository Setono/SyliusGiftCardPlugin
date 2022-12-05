<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\Controller\Action;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Api\Controller\Action\DownloadGiftCardPdfAction;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Setono\SyliusGiftCardPlugin\Renderer\PdfRendererInterface;
use Setono\SyliusGiftCardPlugin\Renderer\PdfResponse;

final class DownloadGiftCardPdfActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_downloads_associated_pdf(): void
    {
        $giftCard = new GiftCard();
        $configuration = new GiftCardConfiguration();
        $expectedPdfResponse = new PdfResponse('<PDF>Gift card content</PDF>');

        $configurationProvider = $this->prophesize(GiftCardConfigurationProviderInterface::class);
        $giftCardPDFRenderer = $this->prophesize(PdfRendererInterface::class);

        $configurationProvider->getConfigurationForGiftCard($giftCard)->willReturn($configuration);
        $giftCardPDFRenderer
            ->render($giftCard, $configuration)
            ->willReturn($expectedPdfResponse);

        $downloadGiftCardPdfAction = new DownloadGiftCardPdfAction(
            $configurationProvider->reveal(),
            $giftCardPDFRenderer->reveal()
        );

        $response = $downloadGiftCardPdfAction($giftCard);

        $this->assertEquals($expectedPdfResponse, $response);
    }
}
