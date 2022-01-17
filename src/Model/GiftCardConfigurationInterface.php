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
     * @return Collection|GiftCardChannelConfigurationInterface[]
     */
    public function getChannelConfigurations(): Collection;

    public function hasChannelConfigurations(): bool;

    public function hasChannelConfiguration(GiftCardChannelConfigurationInterface $channelConfiguration): bool;

    public function addChannelConfiguration(GiftCardChannelConfigurationInterface $channelConfiguration): void;

    public function removeChannelConfiguration(GiftCardChannelConfigurationInterface $channelConfiguration): void;

    public function isDefault(): bool;

    public function setDefault(bool $default): void;

    public function getDefaultValidityPeriod(): ?string;

    public function setDefaultValidityPeriod(?string $defaultValidityPeriod): void;

    public function getPageSize(): ?string;

    public function setPageSize(?string $pageSize): void;

    public function getOrientation(): ?string;

    public function setOrientation(?string $orientation): void;
}
