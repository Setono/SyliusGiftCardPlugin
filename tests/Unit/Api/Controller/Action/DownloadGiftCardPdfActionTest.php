<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\Controller\Action;

use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Api\Controller\Action\DownloadGiftCardPdfAction;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;

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
        $expectedPdfResponse = new PdfResponse('<PDF>Gift card content</PDF>', 'gc.pdf');

        $configurationProvider = $this->prophesize(GiftCardConfigurationProviderInterface::class);
        $giftCardPdfGenerator = $this->prophesize(GiftCardPdfGeneratorInterface::class);

        $configurationProvider->getConfigurationForGiftCard($giftCard)->willReturn($configuration);
        $giftCardPdfGenerator
            ->generatePdfResponse($giftCard, $configuration)
            ->willReturn($expectedPdfResponse);

        $downloadGiftCardPdfAction = new DownloadGiftCardPdfAction(
            $configurationProvider->reveal(),
            $giftCardPdfGenerator->reveal()
        );

        $response = $downloadGiftCardPdfAction($giftCard);

        $this->assertEquals($expectedPdfResponse, $response);
    }
}
