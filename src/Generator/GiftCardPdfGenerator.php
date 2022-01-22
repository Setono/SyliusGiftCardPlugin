<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

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

    private string $publicDir;

    public function __construct(
        Environment $twig,
        GeneratorInterface $snappy,
        PdfRenderingOptionsProviderInterface $renderingOptionsProvider,
        string $publicDir
    ) {
        $this->twig = $twig;
        $this->snappy = $snappy;
        $this->renderingOptionsProvider = $renderingOptionsProvider;
        $this->publicDir = $publicDir;
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

    public function generateAndSavePdf(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardChannelConfiguration
    ): void {
        $html = $this->twig->render('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf.html.twig', [
            'giftCard' => $giftCard,
            'configuration' => $giftCardChannelConfiguration,
        ]);

        $filePath = \sprintf(
            '%s/gift_card_configuration_pdf_%d.pdf',
            $this->publicDir,
            $giftCardChannelConfiguration->getId()
        );
        $this->snappy->generateFromHtml($html, $filePath, [], true);
    }
}
