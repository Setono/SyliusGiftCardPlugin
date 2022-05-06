<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Renderer;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ChannelInterface;

interface GiftCardPDFRendererInterface
{
    public function render(
        GiftCardInterface $giftCard,
        ChannelInterface $channel = null,
        string $localeCode = null
    ): PDFResponse;
}
