<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\StateMachine;

use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;

final class GiftCardPaymentRefundGuard implements GiftCardPaymentRefundGuardInterface
{
    /**
     * The order payment states Sylius' graph leads to no refund from
     */
    private const UNPAID = [
        OrderPaymentStates::STATE_AWAITING_PAYMENT,
        OrderPaymentStates::STATE_PARTIALLY_AUTHORIZED,
        OrderPaymentStates::STATE_AUTHORIZED,
    ];

    public function __construct(
        private readonly GiftCardPaymentCheckerInterface $paymentChecker,
    ) {
    }

    public function isSatisfiedBy(PaymentInterface $payment): bool
    {
        if (!$this->paymentChecker->isGiftCardPayment($payment)) {
            return true;
        }

        $order = $payment->getOrder();
        if (null === $order || OrderInterface::STATE_CANCELLED === $order->getState()) {
            return true;
        }

        return !in_array($order->getPaymentState(), self::UNPAID, true);
    }
}
