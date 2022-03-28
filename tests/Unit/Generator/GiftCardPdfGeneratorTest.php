<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Generator;

use Gaufrette\Filesystem;
use Knp\Snappy\Pdf;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGenerator;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfPathGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Provider\PdfRenderingOptionsProviderInterface;
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
        $renderingOptionsProvider = $this->prophesize(PdfRenderingOptionsProviderInterface::class);
        $pathGenerator = $this->prophesize(GiftCardPdfPathGeneratorInterface::class);
        $filesystem = $this->prophesize(Filesystem::class);

        $renderingOptionsProvider->getRenderingOptions($giftCardChannelConfiguration)->willReturn([]);
        $twig->render('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf.html.twig', [
            'giftCard' => $giftCard,
            'configuration' => $giftCardChannelConfiguration,
        ])->willReturn('super GiftCard template');
        $snappy->getOutputFromHtml('super GiftCard template', [])->willReturn('<PDF>super GiftCard template</PDF>');

        $giftCardPdfGenerator = new GiftCardPdfGenerator(
            $twig->reveal(),
            $snappy->reveal(),
            $renderingOptionsProvider->reveal(),
            $pathGenerator->reveal(),
            $filesystem->reveal()
        );
        $response = $giftCardPdfGenerator->generatePdfResponse($giftCard, $giftCardChannelConfiguration);

        $this->assertEquals('application/pdf', $response->headers->get('Content-type'));
        $this->assertEquals('<PDF>super GiftCard template</PDF>', $response->getContent());
    }

    /**
     * @test
     */
    public function it_generates_pdf_and_gets_content(): void
    {
        $giftCard = new GiftCard();
        $giftCardChannelConfiguration = new GiftCardConfiguration();

        $twig = $this->prophesize(Environment::class);
        $snappy = $this->prophesize(Pdf::class);
        $renderingOptionsProvider = $this->prophesize(PdfRenderingOptionsProviderInterface::class);
        $pathGenerator = $this->prophesize(GiftCardPdfPathGeneratorInterface::class);
        $filesystem = $this->prophesize(Filesystem::class);

        $renderingOptionsProvider->getRenderingOptions($giftCardChannelConfiguration)->willReturn([]);
        $twig->render('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf.html.twig', [
            'giftCard' => $giftCard,
            'configuration' => $giftCardChannelConfiguration,
        ])->willReturn('super GiftCard template');
        $snappy->getOutputFromHtml('super GiftCard template')->willReturn('<PDF>super GiftCard template</PDF>');

        $giftCardPdfGenerator = new GiftCardPdfGenerator(
            $twig->reveal(),
            $snappy->reveal(),
            $renderingOptionsProvider->reveal(),
            $pathGenerator->reveal(),
            $filesystem->reveal()
        );
        $content = $giftCardPdfGenerator->generateAndGetContent($giftCard, $giftCardChannelConfiguration);

        $this->assertEquals('<PDF>super GiftCard template</PDF>', $content);
    }

    /**
     * @test
     */
    public function it_generates_and_saves_pdf(): void
    {
        $giftCard = new GiftCard();
        $giftCardChannelConfiguration = new GiftCardConfiguration();

        $twig = $this->prophesize(Environment::class);
        $snappy = $this->prophesize(Pdf::class);
        $renderingOptionsProvider = $this->prophesize(PdfRenderingOptionsProviderInterface::class);
        $pathGenerator = $this->prophesize(GiftCardPdfPathGeneratorInterface::class);
        $pathGenerator->generatePath($giftCardChannelConfiguration)->willReturn('super/path.pdf');
        $filesystem = $this->prophesize(Filesystem::class);

        $renderingOptionsProvider->getRenderingOptions($giftCardChannelConfiguration)->willReturn([]);
        $twig->render('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf.html.twig', [
            'giftCard' => $giftCard,
            'configuration' => $giftCardChannelConfiguration,
        ])->willReturn('super GiftCard template');
        $snappy->getOutputFromHtml('super GiftCard template', [])->willReturn('<PDF>super GiftCard template</PDF>');

        $filesystem->write(
            'super/path.pdf',
            '<PDF>super GiftCard template</PDF>',
            true
        )->shouldBeCalled();

        $giftCardPdfGenerator = new GiftCardPdfGenerator(
            $twig->reveal(),
            $snappy->reveal(),
            $renderingOptionsProvider->reveal(),
            $pathGenerator->reveal(),
            $filesystem->reveal()
        );
        $filePath = $giftCardPdfGenerator->generateAndSavePdf($giftCard, $giftCardChannelConfiguration);

        $this->assertEquals('super/path.pdf', $filePath);
    }
}
