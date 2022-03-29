<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use Gaufrette\FilesystemInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\GeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\PdfRenderingOptionsProviderInterface;
use Twig\Environment;

class GiftCardPdfGenerator implements GiftCardPdfGeneratorInterface
{
    private Environment $twig;

    private GeneratorInterface $snappy;

    private PdfRenderingOptionsProviderInterface $renderingOptionsProvider;

    private GiftCardPdfPathGeneratorInterface $giftCardPdfPathGenerator;

    private FilesystemInterface $filesystem;

    public function __construct(
        Environment $twig,
        GeneratorInterface $snappy,
        PdfRenderingOptionsProviderInterface $renderingOptionsProvider,
        GiftCardPdfPathGeneratorInterface $giftCardPdfPathGenerator,
        FilesystemInterface $filesystem
    ) {
        $this->twig = $twig;
        $this->snappy = $snappy;
        $this->renderingOptionsProvider = $renderingOptionsProvider;
        $this->giftCardPdfPathGenerator = $giftCardPdfPathGenerator;
        $this->filesystem = $filesystem;
    }

    public function generatePdfResponse(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardChannelConfiguration
    ): PdfResponse {
        $html = $this->twig->render('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf.html.twig', [
            'giftCard' => $giftCard,
            'configuration' => $giftCardChannelConfiguration,
        ]);

        $renderingOptions = $this->renderingOptionsProvider->getRenderingOptions($giftCardChannelConfiguration);

        return new PdfResponse($this->snappy->getOutputFromHtml($html, $renderingOptions), 'gift_card.pdf');
    }

    public function generateAndGetContent(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardChannelConfiguration
    ): string {
        $html = $this->twig->render('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf.html.twig', [
            'giftCard' => $giftCard,
            'configuration' => $giftCardChannelConfiguration,
        ]);
        $renderingOptions = $this->renderingOptionsProvider->getRenderingOptions($giftCardChannelConfiguration);

        return $this->snappy->getOutputFromHtml($html, $renderingOptions);
    }

    public function generateAndSavePdf(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardChannelConfiguration
    ): string {
        $html = $this->twig->render('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf.html.twig', [
            'giftCard' => $giftCard,
            'configuration' => $giftCardChannelConfiguration,
        ]);
        $renderingOptions = $this->renderingOptionsProvider->getRenderingOptions($giftCardChannelConfiguration);

        $filePath = $this->giftCardPdfPathGenerator->generatePath($giftCardChannelConfiguration);
        $pdfContent = $this->snappy->getOutputFromHtml($html, $renderingOptions);

        $this->filesystem->write(
            $filePath,
            $pdfContent,
            true
        );

        return $filePath;
    }
}
