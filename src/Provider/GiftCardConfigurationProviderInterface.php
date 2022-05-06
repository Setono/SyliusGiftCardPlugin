<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;

interface GiftCardConfigurationProviderInterface
{
    public function getConfiguration(ChannelInterface $channel, LocaleInterface $locale): GiftCardConfigurationInterface;

    public function getConfigurationForGiftCard(GiftCardInterface $giftCard): GiftCardConfigurationInterface;
}
