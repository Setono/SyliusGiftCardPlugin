<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;

interface GiftCardPdfGeneratorInterface
{
    public function generatePdfResponse(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardChannelConfiguration
    ): PdfResponse;

    public function generateAndSavePdf(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardChannelConfiguration
    ): void;
}
