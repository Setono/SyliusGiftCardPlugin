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
     * Creates a gift card product with one variant per delivery type. A product with several delivery types gets the
     * shared delivery option, which the customer chooses the variant by. A product with a single delivery type gets no
     * option, so Sylius treats it as a simple product and the shop shows no variant choice for it.
     *
     * The product is returned unmanaged: it is the caller's job to persist and flush it. The delivery option, the
     * first time one is created, is persisted, and written by that same flush. The variants, and the delivery option
     * when it is created, are named in the language of each locale of the shop.
     *
     * @param string|null $name the product's name in every locale. Left out, the product is named "Gift card" in the
     *                          language of each locale
     * @param list<ChannelInterface> $channels the channels to sell the product in, defaulting to all of them
     * @param list<GiftCardDeliveryType> $deliveryTypes the delivery types to create variants for, defaulting to all of them
     */
    public function create(
        string $code,
        ?string $name = null,
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

    /**
     * The codes create() gives the variants of a product with the given code, one per delivery type. A variant code is
     * unique across all products, so whoever picks the code has to know whether these are free as well
     *
     * @param list<GiftCardDeliveryType> $deliveryTypes the delivery types, defaulting to all of them, as in create()
     *
     * @return list<string>
     */
    public function getVariantCodes(string $code, array $deliveryTypes = []): array;
}
