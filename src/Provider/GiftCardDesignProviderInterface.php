<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

interface GiftCardDesignProviderInterface
{
    /**
     * Returns the enabled designs available in the given channel, ordered by position.
     *
     * If the channel has no enabled designs a default "Classic" design is created on the fly so the
     * gift card design picker is never empty and physical gift cards always have artwork to render.
     *
     * @return list<GiftCardDesignInterface>
     */
    public function getDesigns(ChannelInterface $channel): array;
}
