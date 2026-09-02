<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\ChannelInterface;

interface GiftCardProductFactoryInterface
{
    /**
     * Creates a gift card product with the shared delivery option and one variant per delivery type.
     *
     * The product is returned unmanaged: it is the caller's job to persist it.
     *
     * @param list<ChannelInterface> $channels the channels to sell the product in, defaulting to all of them
     * @param list<GiftCardDeliveryType> $deliveryTypes the delivery types to create variants for, defaulting to all of them
     */
    public function create(
        string $code,
        string $name,
        int $price = GiftCardProductFactory::DEFAULT_PRICE,
        bool $enabled = true,
        array $channels = [],
        array $deliveryTypes = [],
    ): ProductInterface;
}
