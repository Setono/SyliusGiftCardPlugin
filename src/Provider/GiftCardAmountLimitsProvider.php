<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Sylius\Component\Channel\Model\ChannelInterface;

final class GiftCardAmountLimitsProvider implements GiftCardAmountLimitsProviderInterface
{
    public function __construct(
        private readonly int $minimumAmount,
        private readonly ?int $maximumAmount,
    ) {
    }

    public function getLimits(ChannelInterface $channel): GiftCardAmountLimits
    {
        return new GiftCardAmountLimits($this->minimumAmount, $this->maximumAmount);
    }
}
