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
     * This is called while rendering the product page, so it only reads: a channel without designs gets an empty
     * list and the design picker is skipped. The default design is seeded by the fixture or by running the
     * setono:gift-card:create-default-design command.
     *
     * @return list<GiftCardDesignInterface>
     */
    public function getDesigns(ChannelInterface $channel): array;
}
