<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Renderer\PDFRendererInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class PdfRuntime implements RuntimeExtensionInterface
{
    private PDFRendererInterface $giftCardPDFRenderer;

    private GiftCardFactoryInterface $giftCardFactory;

    public function __construct(
        PDFRendererInterface $giftCardPDFRenderer,
        GiftCardFactoryInterface $giftCardFactory
    ) {
        $this->giftCardPDFRenderer = $giftCardPDFRenderer;
        $this->giftCardFactory = $giftCardFactory;
    }

    public function getBase64EncodedExamplePdfContent(GiftCardConfigurationInterface $giftCardChannelConfiguration): string
    {
        $giftCard = $this->giftCardFactory->createExample();

        return (string) $this->giftCardPDFRenderer->render($giftCard, $giftCardChannelConfiguration)->encode();
    }
}
