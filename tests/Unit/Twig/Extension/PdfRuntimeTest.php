<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Renderer\PdfRendererInterface;
use Setono\SyliusGiftCardPlugin\Renderer\PdfResponse;
use Setono\SyliusGiftCardPlugin\Twig\Extension\PdfRuntime;

final class PdfRuntimeTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_gets_base64_encoded_pdf_content(): void
    {
        $giftCard = new GiftCard();
        $giftCardConfiguration = new GiftCardConfiguration();
        $pdfResponse = new PdfResponse('Super pdf content');

        $giftCardPDFRenderer = $this->prophesize(PdfRendererInterface::class);
        $giftCardPDFRenderer->render($giftCard, $giftCardConfiguration)->willReturn($pdfResponse);
        $giftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $giftCardFactory->createExample()->willReturn($giftCard);

        $runtime = new PdfRuntime(
            $giftCardPDFRenderer->reveal(),
            $giftCardFactory->reveal()
        );
        $base64Content = $runtime->getBase64EncodedExamplePdfContent($giftCardConfiguration);

        $this->assertEquals($pdfResponse->getEncodedContent(), $base64Content);
    }
}
