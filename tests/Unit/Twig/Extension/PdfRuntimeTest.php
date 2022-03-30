<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Provider\PdfAvailableCssOptionProviderInterface;
use Setono\SyliusGiftCardPlugin\Twig\Extension\PdfRuntime;
use Twig\Environment;

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
        $pdfContent = 'Super pdf content';

        $cssOptionProvider = $this->prophesize(PdfAvailableCssOptionProviderInterface::class);
        $twig = $this->prophesize(Environment::class);
        $giftCardPdfGenerator = $this->prophesize(GiftCardPdfGeneratorInterface::class);
        $giftCardPdfGenerator->generateAndGetContent($giftCard, $giftCardConfiguration)->willReturn($pdfContent);
        $giftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $giftCardFactory->createExample()->willReturn($giftCard);

        $runtime = new PdfRuntime(
            $cssOptionProvider->reveal(),
            $twig->reveal(),
            $giftCardPdfGenerator->reveal(),
            $giftCardFactory->reveal()
        );
        $base64Content = $runtime->getBase64EncodedExamplePdfContent($giftCardConfiguration);

        $this->assertEquals(\base64_encode($pdfContent), $base64Content);
    }
}
