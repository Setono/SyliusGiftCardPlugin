<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Sylius\Component\Core\Model\PaymentMethodInterface;

interface GiftCardPaymentMethodFactoryInterface
{
    /**
     * Creates the payment method gift card payments are made with: the offline gateway, the configured
     * setono_sylius_gift_card.redemption.payment_method_code, enabled and available in every channel.
     *
     * The payment method is returned unmanaged: it is the caller's job to persist and flush it
     */
    public function create(): PaymentMethodInterface;
}
