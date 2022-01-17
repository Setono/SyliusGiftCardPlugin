<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Generator;

use Knp\Snappy\Pdf;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGenerator;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Provider\PdfRenderingOptionProviderInterface;
use Twig\Environment;

final class GiftCardPdfGeneratorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_generates_pdf_response_for_gift_card(): void
    {
        $giftCard = new GiftCard();
        $giftCardChannelConfiguration = new GiftCardConfiguration();

        $twig = $this->prophesize(Environment::class);
        $snappy = $this->prophesize(Pdf::class);
        $renderingOptionProvider = $this->prophesize(PdfRenderingOptionProviderInterface::class);

        $renderingOptionProvider->getRenderingOptions($giftCardChannelConfiguration)->willReturn([]);
        $twig->render('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf.html.twig', [
            'giftCard' => $giftCard,
            'configuration' => $giftCardChannelConfiguration,
        ])->willReturn('super GiftCard template');
        $snappy->getOutputFromHtml('super GiftCard template', [])->willReturn('<PDF>super GiftCard template</PDF>');

        $giftCardPdfGenerator = new GiftCardPdfGenerator(
            $twig->reveal(),
            $snappy->reveal(),
            $renderingOptionProvider->reveal()
        );
        $response = $giftCardPdfGenerator->generatePdfResponse($giftCard, $giftCardChannelConfiguration);

        $this->assertEquals('application/pdf', $response->headers->get('Content-type'));
        $this->assertEquals('<PDF>super GiftCard template</PDF>', $response->getContent());
    }
}
