<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Renderer\GiftCardPDFRendererInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class PdfRuntime implements RuntimeExtensionInterface
{
    private GiftCardPDFRendererInterface $giftCardPDFRenderer;

    private GiftCardFactoryInterface $giftCardFactory;

    public function __construct(
        GiftCardPDFRendererInterface $giftCardPDFRenderer,
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
