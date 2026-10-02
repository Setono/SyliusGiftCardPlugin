<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Exception\GiftCardPaymentMethodNotFoundException;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * Gift card payments are made with a payment method of their own, which the shop sets up once (the
 * setono:gift-card:create-payment-method command, or an offline payment method with the configured code created in
 * the admin). It is never created on the fly: that used to happen in the middle of placing an order, flushing whatever
 * else was pending at that moment. Until it exists the shop refuses gift cards and the admin says why
 */
interface GiftCardPaymentMethodProviderInterface
{
    /**
     * The payment method gift card payments are made with, or null while the shop has not set it up
     */
    public function findPaymentMethod(): ?PaymentMethodInterface;

    /**
     * @throws GiftCardPaymentMethodNotFoundException while the shop has not set it up
     */
    public function getPaymentMethod(): PaymentMethodInterface;
}
