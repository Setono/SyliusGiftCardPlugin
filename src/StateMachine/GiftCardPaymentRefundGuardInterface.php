<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\StateMachine;

use Sylius\Component\Core\Model\PaymentInterface;

/**
 * Guards refunding a single payment (sylius_payment refund), which the admin offers for every completed payment
 */
interface GiftCardPaymentRefundGuardInterface
{
    /**
     * Whether the payment may be refunded on its own. A gift card payment may not while its order is not paid yet:
     * awaiting payment, partially authorized or authorized. Sylius' order payment graph leads from none of those to a
     * refunded state, so the order would stay unpaid, or authorized, with nothing behind it (#507, review of #504).
     * Cancelling the order is how its gift cards get their balance back then, and cancelling refunds their payments
     * once the order is cancelled, which this lets through. Any other payment is none of this guard's business
     */
    public function isSatisfiedBy(PaymentInterface $payment): bool;
}
