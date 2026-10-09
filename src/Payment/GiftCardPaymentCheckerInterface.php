<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Payment;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;

interface GiftCardPaymentCheckerInterface
{
    /**
     * Returns true if the given payment is a gift card payment (i.e. it uses the configured gift card payment method)
     */
    public function isGiftCardPayment(PaymentInterface $payment): bool;

    /**
     * Returns true if the given payment method is the one gift card payments are made with, i.e. it has the configured
     * code (setono_sylius_gift_card.redemption.payment_method_code)
     */
    public function isGiftCardPaymentMethod(PaymentMethodInterface $paymentMethod): bool;
}
