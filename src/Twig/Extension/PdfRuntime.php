<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Renderer\PdfRendererInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class PdfRuntime implements RuntimeExtensionInterface
{
    private PdfRendererInterface $PDFRenderer;

    private GiftCardFactoryInterface $giftCardFactory;

    public function __construct(
        PdfRendererInterface $giftCardPDFRenderer,
        GiftCardFactoryInterface $giftCardFactory
    ) {
        $this->PDFRenderer = $giftCardPDFRenderer;
        $this->giftCardFactory = $giftCardFactory;
    }

    public function getBase64EncodedExamplePdfContent(GiftCardConfigurationInterface $giftCardChannelConfiguration): string
    {
        $giftCard = $this->giftCardFactory->createExample();

        return $this->PDFRenderer->render($giftCard, $giftCardChannelConfiguration)->getEncodedContent();
    }
}
