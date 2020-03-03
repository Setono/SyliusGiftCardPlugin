<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ImagesAwareInterface;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;

interface GiftCardConfigurationInterface extends
    ResourceInterface,
    CodeAwareInterface,
    ImagesAwareInterface,
    TimestampableInterface,
    ToggleableInterface
{
    public function getBackgroundImage(): ?GiftCardConfigurationImageInterface;

    public function setBackgroundImage(?GiftCardConfigurationImageInterface $image): void;

    /**
     * @return Collection|ChannelConfigurationInterface[]
     */
    public function getChannelConfigurations(): Collection;

    public function hasChannelConfigurations(): bool;

    public function hasChannelConfiguration(ChannelConfigurationInterface $channelConfiguration): bool;

    public function addChannelConfiguration(ChannelConfigurationInterface $channelConfiguration): void;

    public function removeChannelConfiguration(ChannelConfigurationInterface $channelConfiguration): void;
}
