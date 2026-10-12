<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Sylius\Component\Channel\Model\ChannelInterface;

interface GiftCardAmountLimitsProviderInterface
{
    /**
     * Returns the minimum and maximum purchasable gift card amount, in minor units of the given currency, for a card
     * held in that currency in the given channel. The amount the customer types is held to them in the currency it is
     * typed in, without conversion. Decorate this service to implement channel, currency or product specific limits
     */
    public function getLimits(ChannelInterface $channel, string $currencyCode): GiftCardAmountLimits;
}
