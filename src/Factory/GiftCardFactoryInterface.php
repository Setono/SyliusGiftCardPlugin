<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<GiftCardInterface>
 */
interface GiftCardFactoryInterface extends FactoryInterface
{
    public function createNew(): GiftCardInterface;

    /**
     * Creates a gift card scoped to a channel: a unique code, the channel, the channel's base currency
     * and an expiry derived from the configured default validity period
     */
    public function createForChannel(ChannelInterface $channel): GiftCardInterface;

    /**
     * Creates a fully populated, non-persisted example gift card used to render preview PDFs
     */
    public function createExample(ChannelInterface $channel, ?GiftCardDesignInterface $design = null): GiftCardInterface;
}
