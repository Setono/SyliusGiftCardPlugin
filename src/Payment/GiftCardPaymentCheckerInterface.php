<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Payment;

use Sylius\Component\Core\Model\PaymentInterface;

interface GiftCardPaymentCheckerInterface
{
    /**
     * Returns true if the given payment is a gift card payment (i.e. it uses the configured gift card payment method)
     */
    public function isGiftCardPayment(PaymentInterface $payment): bool;
}
