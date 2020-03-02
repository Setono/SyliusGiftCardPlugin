<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\ChannelInterface as BaseChannelInterface;

interface ChannelInterface extends BaseChannelInterface
{
    public function setGiftCardConfiguration(?GiftCardConfigurationInterface $giftCardConfiguration): void;

    public function getGiftCardConfiguration(): ?GiftCardConfigurationInterface;
}
