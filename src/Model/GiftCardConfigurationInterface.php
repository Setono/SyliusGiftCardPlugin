<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Component\Core\Model\ImagesAwareInterface;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;

interface GiftCardConfigurationInterface extends
    ResourceInterface,
    ChannelsAwareInterface,
    CodeAwareInterface,
    ImagesAwareInterface,
    TimestampableInterface,
    ToggleableInterface
{
    public function getBackgroundImage(): ?GiftCardConfigurationImageInterface;

    public function setBackgroundImage(?GiftCardConfigurationImageInterface $image): void;
}
