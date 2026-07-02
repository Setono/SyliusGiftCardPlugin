<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

interface GiftCardPaymentMethodProviderInterface
{
    /**
     * Returns the payment method used for gift card payments in the given channel, creating it (with the offline
     * gateway) if it does not exist yet so the developer does not have to set it up manually
     */
    public function getPaymentMethod(ChannelInterface $channel): PaymentMethodInterface;
}
