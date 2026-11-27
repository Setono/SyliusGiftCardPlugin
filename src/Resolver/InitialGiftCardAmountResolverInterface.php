<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

interface InitialGiftCardAmountResolverInterface
{
    /**
     * Returns the amount the gift card product page's amount field starts out at, in minor units of the given currency,
     * which is the one the field is in. Returns null to start the field out empty.
     *
     * The add to cart form starts the field out at it, and the page's live preview of the card shows it until the
     * customer types another amount. Decorate this service to suggest another amount
     */
    public function resolve(ChannelInterface $channel, string $currencyCode, ?ProductVariantInterface $variant): ?int;
}
