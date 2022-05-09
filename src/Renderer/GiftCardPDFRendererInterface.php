<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Renderer;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ChannelInterface;

interface GiftCardPDFRendererInterface
{
    /**
     * @param GiftCardConfigurationInterface|null $giftCardConfiguration if none provided, the matching configuration for the respective gift card will be used
     * @param ChannelInterface|null $channel if none provided, the channel context will be used
     * @param string|null $localeCode if none provided, the locale context will be used
     */
    public function render(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardConfiguration = null,
        ChannelInterface $channel = null,
        string $localeCode = null
    ): PDFResponse;
}
