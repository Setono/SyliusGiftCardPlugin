<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class PdfRuntime implements RuntimeExtensionInterface
{
    private GiftCardPdfGeneratorInterface $giftCardPdfGenerator;

    private GiftCardFactoryInterface $giftCardFactory;

    public function __construct(
        GiftCardPdfGeneratorInterface $giftCardPdfGenerator,
        GiftCardFactoryInterface $giftCardFactory
    ) {
        $this->giftCardPdfGenerator = $giftCardPdfGenerator;
        $this->giftCardFactory = $giftCardFactory;
    }

    public function getBase64EncodedExamplePdfContent(GiftCardConfigurationInterface $giftCardChannelConfiguration): string
    {
        $giftCard = $this->giftCardFactory->createExample();

        $content = $this->giftCardPdfGenerator->generateAndGetContent($giftCard, $giftCardChannelConfiguration);

        return \base64_encode($content);
    }
}
