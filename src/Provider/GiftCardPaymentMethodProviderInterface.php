<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Exception\GiftCardPaymentMethodDisabledException;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardPaymentMethodNotFoundException;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * Gift card payments are made with a payment method of their own, which the shop sets up once (the
 * setono:gift-card:create-payment-method command, or an offline payment method with the configured code created in
 * the admin). It is never created on the fly: that used to happen in the middle of placing an order, flushing whatever
 * else was pending at that moment. Until it exists the shop refuses gift cards and the admin says why.
 *
 * Its Enabled switch means what it means for any payment method, whether customers can pay with it: while an
 * administrator has disabled it, the shop refuses gift cards just as it does while the method is missing (#484).
 * Gift card payments already made keep their method, and refunding them gives the balance back all the same
 */
interface GiftCardPaymentMethodProviderInterface
{
    /**
     * The payment method gift card payments are made with, enabled or not, or null while the shop has not set it up
     */
    public function findPaymentMethod(): ?PaymentMethodInterface;

    /**
     * The payment method gift card payments are made with, while customers can pay with it: null while the shop has not
     * set it up, and while it is disabled
     */
    public function findEnabledPaymentMethod(): ?PaymentMethodInterface;

    /**
     * @throws GiftCardPaymentMethodNotFoundException while the shop has not set it up
     * @throws GiftCardPaymentMethodDisabledException while it is disabled
     */
    public function getEnabledPaymentMethod(): PaymentMethodInterface;
}
