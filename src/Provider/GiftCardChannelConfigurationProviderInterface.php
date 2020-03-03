<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Model\GiftCardChannelConfigurationInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;

interface GiftCardChannelConfigurationProviderInterface
{
    public function getChannelConfiguration(ChannelInterface $channel, LocaleInterface $locale): ?GiftCardChannelConfigurationInterface;
}
