<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Sylius\Component\Channel\Model\ChannelInterface;

interface GiftCardAmountLimitsProviderInterface
{
    /**
     * Returns the minimum and maximum purchasable gift card amount (in minor units) for the given channel.
     * Decorate this service to implement channel or product specific limits
     */
    public function getLimits(ChannelInterface $channel): GiftCardAmountLimits;
}
