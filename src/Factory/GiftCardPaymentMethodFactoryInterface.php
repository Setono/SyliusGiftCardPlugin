<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Sylius\Component\Core\Model\PaymentMethodInterface;

interface GiftCardPaymentMethodFactoryInterface
{
    /**
     * Creates the payment method gift card payments are made with: the offline gateway, the configured
     * setono_sylius_gift_card.redemption.payment_method_code, enabled, in no channel and named in the language of every
     * locale of the shop. Its channels make no difference to gift card payments, which are made with it in every
     * channel.
     *
     * The payment method is returned unmanaged: it is the caller's job to persist and flush it
     */
    public function create(): PaymentMethodInterface;
}
