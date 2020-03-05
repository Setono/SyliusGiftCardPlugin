<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Channel\Model\ChannelAwareInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface GiftCardChannelConfigurationInterface extends ResourceInterface, ChannelAwareInterface
{
    public function getLocale(): ?LocaleInterface;

    public function setLocale(LocaleInterface $locale): void;

    public function getConfiguration(): ?GiftCardConfigurationInterface;

    public function setConfiguration(GiftCardConfigurationInterface $configuration): void;
}
