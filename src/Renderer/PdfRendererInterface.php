<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Renderer;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

interface PdfRendererInterface
{
    /**
     * @param GiftCardConfigurationInterface|null $giftCardConfiguration if none provided, the matching configuration for the respective gift card will be used
     * @param ChannelInterface|null $channel if none provided, it will try to infer the channel from the gift card order (if any) or else the channel context will be used
     * @param string|null $localeCode if none provided, it will try to infer the locale code from the gift card order (if any) or else the locale context will be used
     */
    public function render(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardConfiguration = null,
        ChannelInterface $channel = null,
        string $localeCode = null
    ): PdfResponse;
}
