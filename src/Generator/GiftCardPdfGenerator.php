<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\GeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\PdfRenderingOptionProviderInterface;
use Twig\Environment;

class GiftCardPdfGenerator implements GiftCardPdfGeneratorInterface
{
    private Environment $twig;

    private GeneratorInterface $snappy;

    private PdfRenderingOptionProviderInterface $renderingOptionProvider;

    public function __construct(
        Environment $twig,
        GeneratorInterface $snappy,
        PdfRenderingOptionProviderInterface $renderingOptionProvider
    ) {
        $this->twig = $twig;
        $this->snappy = $snappy;
        $this->renderingOptionProvider = $renderingOptionProvider;
    }

    public function generatePdfResponse(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardChannelConfiguration
    ): PdfResponse {
        $html = $this->twig->render('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf.html.twig', [
            'giftCard' => $giftCard,
            'configuration' => $giftCardChannelConfiguration,
        ]);

        $renderingOptions = $this->renderingOptionProvider->getRenderingOptions($giftCardChannelConfiguration);

        return new PdfResponse($this->snappy->getOutputFromHtml($html, $renderingOptions), 'gift_card.pdf');
    }
}
