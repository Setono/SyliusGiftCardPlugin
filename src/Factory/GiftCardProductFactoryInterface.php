<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\ChannelInterface;

interface GiftCardProductFactoryInterface
{
    public const DEFAULT_PRICE = 5000;

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
        int $price = self::DEFAULT_PRICE,
        bool $enabled = true,
        array $channels = [],
        array $deliveryTypes = [],
    ): ProductInterface;

    /**
     * The slug create() gives a product with the given code, in every locale. A slug is unique per locale, so whoever
     * picks the code has to know whether its slug is free as well
     */
    public function getSlug(string $code): string;
}
